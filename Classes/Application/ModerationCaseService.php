<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Application;

use Devclaim\NeosModeration\Domain\Exception\AccessDenied;
use Devclaim\NeosModeration\Domain\Exception\CaseAlreadyDecided;
use Devclaim\NeosModeration\Domain\Exception\ModerationCaseNotFound;
use Devclaim\NeosModeration\Domain\Model\ModerationCase;
use Devclaim\NeosModeration\Domain\Model\ModerationStatus;
use Devclaim\NeosModeration\Domain\Repository\ModerationCaseQuery;
use Devclaim\NeosModeration\Domain\Repository\ModerationCaseStoreInterface;
use Devclaim\NeosModeration\Domain\Service\ActorProviderInterface;
use Devclaim\NeosModeration\Domain\Service\ClockInterface;
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

    public function canViewPrivateMetadata(ModerationCase $case): bool
    {
        return $this->authorization->canViewPrivateMetadata($this->typeRegistry->get($case->getTypeKey()));
    }

    public function approve(ModerationCaseId $id, ?string $reason = null): ModerationCase
    {
        return $this->caseStore->transactional(function () use ($id, $reason): ModerationCase {
            $case = $this->caseStore->findForUpdate($id) ?? throw ModerationCaseNotFound::withId($id);
            $handler = $this->typeRegistry->get($case->getTypeKey());
            $this->authorization->assertCanAccessType($handler);

            if ($case->getStatus() === ModerationStatus::Approved) {
                return $case;
            }
            if ($case->getStatus() !== ModerationStatus::Pending) {
                throw CaseAlreadyDecided::withStatus($case->getStatus());
            }

            $result = $handler->approve($case);
            $case->approve(
                $this->actorProvider->requireActorIdentifier(),
                $this->clock->now(),
                $reason,
                $result->externalReference,
                $result->auditContext,
            );
            return $case;
        });
    }

    public function reject(ModerationCaseId $id, ?string $reason = null): ModerationCase
    {
        return $this->caseStore->transactional(function () use ($id, $reason): ModerationCase {
            $case = $this->caseStore->findForUpdate($id) ?? throw ModerationCaseNotFound::withId($id);
            $handler = $this->typeRegistry->get($case->getTypeKey());
            $this->authorization->assertCanAccessType($handler);

            if ($case->getStatus() === ModerationStatus::Rejected) {
                return $case;
            }
            if ($case->getStatus() !== ModerationStatus::Pending) {
                throw CaseAlreadyDecided::withStatus($case->getStatus());
            }

            $result = $handler instanceof RejectionHandlerInterface
                ? $handler->reject($case, $reason)
                : new RejectionResult();
            $case->reject(
                $this->actorProvider->requireActorIdentifier(),
                $this->clock->now(),
                $reason,
                $result->auditContext,
            );
            return $case;
        });
    }
}
