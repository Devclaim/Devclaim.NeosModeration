<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Application;

use Devclaim\NeosModeration\Domain\Model\ModerationCase;
use Devclaim\NeosModeration\Domain\Repository\ModerationCaseStoreInterface;
use Devclaim\NeosModeration\Domain\Service\ClockInterface;
use Devclaim\NeosModeration\Domain\Service\ModerationTypeRegistry;
use Devclaim\NeosModeration\Domain\Service\SubmissionAwareTypeHandlerInterface;
use Devclaim\NeosModeration\Domain\ValueObject\ModerationCaseId;
use Neos\Flow\Annotations as Flow;
use Psr\Log\LoggerInterface;

#[Flow\Scope('singleton')]
final class SubmitModerationCase
{
    #[Flow\Inject(name: 'Neos.Flow:SystemLogger')]
    protected ?LoggerInterface $logger = null;

    public function __construct(
        private readonly ModerationTypeRegistry $typeRegistry,
        private readonly ModerationCaseStoreInterface $caseStore,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * This service is intentionally not exposed as a public controller action. Callers must authenticate and
     * validate public traffic before invoking it; the registered handler performs type-specific data validation.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $privateMetadata
     */
    public function submit(
        string $typeKey,
        string $locale,
        array $payload,
        ?array $privateMetadata = null,
    ): ModerationCase {
        $handler = $this->typeRegistry->get($typeKey);
        $validatedSubmission = $handler->validateSubmission($locale, $payload, $privateMetadata);
        $case = new ModerationCase(
            ModerationCaseId::create(),
            $typeKey,
            $locale,
            $validatedSubmission->payload,
            $validatedSubmission->privateMetadata,
            $this->clock->now(),
        );

        $this->caseStore->transactional(function () use ($case): void {
            $this->caseStore->add($case);
        });
        if ($handler instanceof SubmissionAwareTypeHandlerInterface) {
            try {
                $handler->submitted($case);
            } catch (\Throwable $throwable) {
                // the case is stored: the handler catches up when it is decided
                $this->logger?->error(sprintf(
                    'Moderation: preparing case %s of type "%s" failed: %s',
                    $case->getId()->value,
                    $typeKey,
                    $throwable->getMessage(),
                ), ['exception' => $throwable]);
            }
        }

        return $case;
    }
}
