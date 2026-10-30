<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Application;

use Devclaim\NeosModeration\Domain\Exception\AccessDenied;
use Devclaim\NeosModeration\Domain\Exception\CaseAlreadyDecided;
use Devclaim\NeosModeration\Domain\Exception\InvalidModerationData;
use Devclaim\NeosModeration\Domain\Exception\ModerationCaseNotFound;
use Devclaim\NeosModeration\Domain\Model\ModerationCase;
use Devclaim\NeosModeration\Domain\Model\ModerationStatus;
use Devclaim\NeosModeration\Domain\Repository\ModerationCaseQuery;
use Devclaim\NeosModeration\Domain\Repository\ModerationCaseStoreInterface;
use Devclaim\NeosModeration\Domain\Service\ActorProviderInterface;
use Devclaim\NeosModeration\Domain\Service\ClockInterface;
use Devclaim\NeosModeration\Domain\Service\CorrectableTypeHandlerInterface;
use Devclaim\NeosModeration\Domain\Service\ModerationAuthorizationInterface;
use Devclaim\NeosModeration\Domain\Service\ModerationTypeHandlerInterface;
use Devclaim\NeosModeration\Domain\Service\ModerationTypeRegistry;
use Devclaim\NeosModeration\Domain\Service\RejectionHandlerInterface;
use Devclaim\NeosModeration\Domain\ValueObject\ModerationCaseId;
use Devclaim\NeosModeration\Domain\ValueObject\RejectionResult;
use Neos\Flow\Annotations as Flow;

#[Flow\Scope('singleton')]
final class ModerationCaseService
{
    public function __construct(
        private readonly ModerationTypeRegistry $typeRegistry,
        private readonly ModerationCaseStoreInterface $caseStore,
        private readonly ModerationAuthorizationInterface $authorization,
        private readonly ActorProviderInterface $actorProvider,
        private readonly ClockInterface $clock,
    ) {
    }

    /** @return array<string, ModerationTypeHandlerInterface> */
    public function getAccessibleTypes(): array
    {
        return array_filter(
            $this->typeRegistry->all(),
            fn(ModerationTypeHandlerInterface $handler): bool => $this->authorization->canAccessType($handler),
        );
    }

    /** @return list<ModerationCase> */
    public function list(?ModerationStatus $status = ModerationStatus::Pending, ?string $typeKey = null): array
    {
        $accessibleTypes = $this->getAccessibleTypes();
        if ($typeKey !== null) {
            if (!isset($accessibleTypes[$typeKey])) {
                throw AccessDenied::forType($typeKey);
            }
        }
        return $this->caseStore->findByQuery(new ModerationCaseQuery(
            array_keys($accessibleTypes),
            $status,
            $typeKey,
        ));
    }

    public function get(ModerationCaseId $id): ModerationCase
    {
        $case = $this->caseStore->find($id) ?? throw ModerationCaseNotFound::withId($id);
        $this->authorization->assertCanAccessType($this->typeRegistry->get($case->getTypeKey()));
        return $case;
    }

    /** @return array<string, int> status value => number of cases of the accessible types */
    public function counts(): array
    {
        return $this->caseStore->countByStatus(array_keys($this->getAccessibleTypes()));
    }

    /**
     * Replaces the moderator's corrections of a pending case; the submitted payload stays unchanged
     *
     * @param array<string, mixed> $corrections
     * @throws InvalidModerationData
     */
    public function correct(ModerationCaseId $id, array $corrections): ModerationCase
    {
        return $this->caseStore->transactional(function () use ($id, $corrections): ModerationCase {
            $case = $this->caseStore->findForUpdate($id) ?? throw ModerationCaseNotFound::withId($id);
            $handler = $this->typeRegistry->get($case->getTypeKey());
            $this->authorization->assertCanAccessType($handler);
            if ($case->getStatus() !== ModerationStatus::Pending) {
                throw CaseAlreadyDecided::withStatus($case->getStatus());
            }
            if (!$handler instanceof CorrectableTypeHandlerInterface) {
                throw new InvalidModerationData(['__general' => 'notCorrectable']);
            }
            $allowed = array_map(static fn($field): string => $field->name, $handler->getCorrectableFields($case));
            $unknown = array_diff(array_keys($corrections), $allowed);
            if ($unknown !== []) {
                throw new InvalidModerationData(array_fill_keys(array_values($unknown), 'notCorrectable'));
            }
            $case->correct(
                $handler->validateCorrections($case, $corrections),
                $this->actorProvider->requireActorIdentifier(),
                $this->clock->now(),
            );
            $this->caseStore->update($case);
            return $case;
        });
    }

    public function canViewPrivateMetadata(ModerationCase $case): bool
    {
        return $this->authorization->canViewPrivateMetadata($this->typeRegistry->get($case->getTypeKey()));
    }

    /**
     * Approves a pending case in three steps: checks it (locked), runs the handler outside of a transaction (it may
     * write to another store, e.g. the event-sourced content repository, which cannot commit inside one), and records
     * the decision (locked again). Handlers must be idempotent by the case id: a repeated or concurrent approval runs
     * them again and they find what they created before. An approval that is already recorded returns at once.
     */
    public function approve(ModerationCaseId $id, ?string $reason = null): ModerationCase
    {
        $checked = $this->caseStore->transactional(function () use ($id): ModerationCase {
            $case = $this->caseStore->findForUpdate($id) ?? throw ModerationCaseNotFound::withId($id);
            $this->authorization->assertCanAccessType($this->typeRegistry->get($case->getTypeKey()));
            if ($case->getStatus() === ModerationStatus::Rejected) {
                throw CaseAlreadyDecided::withStatus($case->getStatus());
            }
            return $case;
        });
        if ($checked->getStatus() === ModerationStatus::Approved) {
            return $checked;
        }

        $result = $this->typeRegistry->get($checked->getTypeKey())->approve($checked);

        return $this->caseStore->transactional(function () use ($id, $reason, $result): ModerationCase {
            $case = $this->caseStore->findForUpdate($id) ?? throw ModerationCaseNotFound::withId($id);
            if ($case->getStatus() === ModerationStatus::Approved) {
                return $case;
            }
            if ($case->getStatus() !== ModerationStatus::Pending) {
                throw CaseAlreadyDecided::withStatus($case->getStatus());
            }
            $case->approve(
                $this->actorProvider->requireActorIdentifier(),
                $this->clock->now(),
                $reason,
                $result->externalReference,
                $result->auditContext,
            );
            $this->caseStore->update($case);
            return $case;
        });
    }

    /**
     * Rejects a pending case in the same three steps as an approval: checks it (locked), runs the handler, if the
     * type has one ({@see RejectionHandlerInterface}), outside of a transaction, and records the decision (locked).
     */
    public function reject(ModerationCaseId $id, ?string $reason = null): ModerationCase
    {
        $checked = $this->caseStore->transactional(function () use ($id): ModerationCase {
            $case = $this->caseStore->findForUpdate($id) ?? throw ModerationCaseNotFound::withId($id);
            $this->authorization->assertCanAccessType($this->typeRegistry->get($case->getTypeKey()));
            if ($case->getStatus() === ModerationStatus::Approved) {
                throw CaseAlreadyDecided::withStatus($case->getStatus());
            }
            return $case;
        });
        if ($checked->getStatus() === ModerationStatus::Rejected) {
            return $checked;
        }

        $handler = $this->typeRegistry->get($checked->getTypeKey());
        $result = $handler instanceof RejectionHandlerInterface
            ? $handler->reject($checked, $reason)
            : new RejectionResult();

        return $this->caseStore->transactional(function () use ($id, $reason, $result): ModerationCase {
            $case = $this->caseStore->findForUpdate($id) ?? throw ModerationCaseNotFound::withId($id);
            if ($case->getStatus() === ModerationStatus::Rejected) {
                return $case;
            }
            if ($case->getStatus() !== ModerationStatus::Pending) {
                throw CaseAlreadyDecided::withStatus($case->getStatus());
            }
            $case->reject(
                $this->actorProvider->requireActorIdentifier(),
                $this->clock->now(),
                $reason,
                $result->auditContext,
            );
            $this->caseStore->update($case);
            return $case;
        });
    }

    /**
     * Deletes a case completely: the submission, the private metadata and the audit trail. A pending case is undone
     * first like a rejection (the type's {@see RejectionHandlerInterface}, outside of a transaction), so nothing it
     * prepared stays behind; a decided case leaves what its decision created (e.g. the published content of an
     * approval). Deleting a case that is gone already does nothing, so a repeated delete is no error.
     */
    public function delete(ModerationCaseId $id): void
    {
        $checked = $this->caseStore->transactional(function () use ($id): ?ModerationCase {
            $case = $this->caseStore->findForUpdate($id);
            if ($case !== null) {
                $this->authorization->assertCanAccessType($this->typeRegistry->get($case->getTypeKey()));
            }
            return $case;
        });
        if ($checked === null) {
            return;
        }

        $handler = $this->typeRegistry->get($checked->getTypeKey());
        if ($checked->getStatus() === ModerationStatus::Pending && $handler instanceof RejectionHandlerInterface) {
            $handler->reject($checked, null);
        }

        $this->caseStore->transactional(function () use ($id, $checked): void {
            $case = $this->caseStore->findForUpdate($id);
            if ($case === null) {
                return;
            }
            // approved in the meantime: its decision stands, the moderator can delete it again knowing that
            if ($checked->getStatus() === ModerationStatus::Pending && $case->getStatus() === ModerationStatus::Approved) {
                throw CaseAlreadyDecided::withStatus($case->getStatus());
            }
            $this->caseStore->remove($case);
        });
    }
}
