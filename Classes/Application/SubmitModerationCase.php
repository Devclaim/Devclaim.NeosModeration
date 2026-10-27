<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Application;

use Devclaim\NeosModeration\Domain\Model\ModerationCase;
use Devclaim\NeosModeration\Domain\Repository\ModerationCaseStoreInterface;
use Devclaim\NeosModeration\Domain\Service\ClockInterface;
use Devclaim\NeosModeration\Domain\Service\ModerationTypeRegistry;
use Devclaim\NeosModeration\Domain\ValueObject\ModerationCaseId;
use Neos\Flow\Annotations as Flow;

#[Flow\Scope('singleton')]
final class SubmitModerationCase
{
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

        return $this->caseStore->transactional(function () use ($case): ModerationCase {
            $this->caseStore->add($case);
            return $case;
        });
    }
}
