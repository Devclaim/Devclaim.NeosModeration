<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\Service;

use Devclaim\NeosModeration\Domain\Model\ModerationCase;
use Devclaim\NeosModeration\Domain\ValueObject\ApprovalResult;
use Devclaim\NeosModeration\Domain\ValueObject\CasePresentation;
use Devclaim\NeosModeration\Domain\ValueObject\ValidatedSubmission;

interface ModerationTypeHandlerInterface
{
    public function getTypeKey(): string;

    public function getLabel(): string;

    public function getIcon(): string;

    public function getPermissionIdentifier(): string;

    /**
     * Returns the privilege target for viewing private submitter metadata.
     * A null value intentionally restricts private metadata to administrators.
     */
    public function getPrivateMetadataPermissionIdentifier(): ?string;

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $privateMetadata
     */
    public function validateSubmission(string $locale, array $payload, ?array $privateMetadata): ValidatedSubmission;

    public function present(ModerationCase $case): CasePresentation;

    /**
     * Runs outside of a transaction, before the approval is recorded: implementations must be idempotent by the case
     * identifier (a repeated or concurrent approval runs them again).
     */
    public function approve(ModerationCase $case): ApprovalResult;
}
