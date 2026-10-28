<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Tests\Unit\Fixtures;

use Devclaim\NeosModeration\Domain\Model\ModerationCase;
use Devclaim\NeosModeration\Domain\Exception\InvalidModerationData;
use Devclaim\NeosModeration\Domain\Service\CorrectableTypeHandlerInterface;
use Devclaim\NeosModeration\Domain\ValueObject\CorrectableField;
use Devclaim\NeosModeration\Domain\ValueObject\ApprovalResult;
use Devclaim\NeosModeration\Domain\ValueObject\CasePresentation;
use Devclaim\NeosModeration\Domain\ValueObject\PresentationField;
use Devclaim\NeosModeration\Domain\ValueObject\ValidatedSubmission;

final class TestTypeHandler implements CorrectableTypeHandlerInterface
{
    public int $approvalCalls = 0;
    /** @var array<string, mixed> */
    public array $approvedPayload = [];

    public function getTypeKey(): string
    {
        return 'test.example';
    }

    public function getLabel(): string
    {
        return 'Test example';
    }

    public function getIcon(): string
    {
        return 'fas fa-test';
    }

    public function getPermissionIdentifier(): string
    {
        return 'Test.Package:Moderation.Example';
    }

    public function getPrivateMetadataPermissionIdentifier(): ?string
    {
        return 'Test.Package:Moderation.Example.Private';
    }

    public function validateSubmission(string $locale, array $payload, ?array $privateMetadata): ValidatedSubmission
    {
        return new ValidatedSubmission(['title' => trim((string)($payload['title'] ?? ''))], $privateMetadata);
    }

    public function present(ModerationCase $case): CasePresentation
    {
        return new CasePresentation('Test', [
            new PresentationField('Title', (string)$case->getPayload()['title']),
        ]);
    }

    public function getCorrectableFields(ModerationCase $case): array
    {
        return [new CorrectableField('title', 'Title', 'text', required: true)];
    }

    public function validateCorrections(ModerationCase $case, array $corrections): array
    {
        $title = trim((string)($corrections['title'] ?? ''));
        if ($title === '') {
            throw new InvalidModerationData(['title' => 'required']);
        }
        return ['title' => $title];
    }

    public function approve(ModerationCase $case): ApprovalResult
    {
        $this->approvalCalls++;
        $this->approvedPayload = $case->getEffectivePayload();
        return new ApprovalResult('test:' . $case->getId()->value, ['handler' => 'test']);
    }
}
