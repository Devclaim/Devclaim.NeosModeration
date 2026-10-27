<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Tests\Unit\Domain\Model;

use DateTimeImmutable;
use Devclaim\NeosModeration\Domain\Exception\CaseAlreadyDecided;
use Devclaim\NeosModeration\Domain\Model\AuditEvent;
use Devclaim\NeosModeration\Domain\Model\ModerationCase;
use Devclaim\NeosModeration\Domain\Model\ModerationStatus;
use Devclaim\NeosModeration\Domain\ValueObject\ModerationCaseId;
use PHPUnit\Framework\TestCase;

final class ModerationCaseTest extends TestCase
{
    public function testApprovalPreservesSubmissionAndAppendsAuditEntry(): void
    {
        $submittedAt = new DateTimeImmutable('2026-10-27T10:00:00+00:00');
        $decidedAt = new DateTimeImmutable('2026-10-27T11:00:00+00:00');
        $payload = ['title' => 'Original'];
        $privateMetadata = ['email' => 'private@example.test'];
        $case = new ModerationCase(
            ModerationCaseId::fromString('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'),
            'test.example',
            'de',
            $payload,
            $privateMetadata,
            $submittedAt,
        );

        $case->approve('moderator', $decidedAt, 'Looks good', 'node-123', ['source' => 'unit-test']);

        self::assertSame(ModerationStatus::Approved, $case->getStatus());
        self::assertSame($payload, $case->getPayload());
        self::assertSame($privateMetadata, $case->getPrivateMetadata());
        self::assertSame('node-123', $case->getExternalReference());
        self::assertSame('moderator', $case->getModeratorIdentifier());
        self::assertSame($decidedAt, $case->getDecidedAt());
        self::assertSame([AuditEvent::Submitted, AuditEvent::Approved], array_map(
            static fn($entry): AuditEvent => $entry->getEvent(),
            $case->getAuditEntries(),
        ));
        self::assertSame(['source' => 'unit-test'], $case->getAuditEntries()[1]->getContext());
    }

    public function testDomainRejectsSecondTransition(): void
    {
        $case = $this->newCase();
        $case->reject('moderator', new DateTimeImmutable('2026-10-27T11:00:00+00:00'), null);

        $this->expectException(CaseAlreadyDecided::class);
        $case->approve('moderator', new DateTimeImmutable('2026-10-27T12:00:00+00:00'), null, null);
    }

    private function newCase(): ModerationCase
    {
        return new ModerationCase(
            ModerationCaseId::fromString('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'),
            'test.example',
            'en',
            ['title' => 'Test'],
            null,
            new DateTimeImmutable('2026-10-27T10:00:00+00:00'),
        );
    }
}
