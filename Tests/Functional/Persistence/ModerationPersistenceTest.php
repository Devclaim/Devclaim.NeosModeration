<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Tests\Functional\Persistence;

use DateTimeImmutable;
use Devclaim\NeosModeration\Domain\Model\ModerationCase;
use Devclaim\NeosModeration\Domain\Model\ModerationStatus;
use Devclaim\NeosModeration\Domain\Repository\ModerationCaseStoreInterface;
use Devclaim\NeosModeration\Domain\ValueObject\ModerationCaseId;
use Doctrine\ORM\EntityManagerInterface;
use Neos\Flow\Tests\FunctionalTestCase;

final class ModerationPersistenceTest extends FunctionalTestCase
{
    protected static $testablePersistenceEnabled = true;

    public function testCaseAndAuditHistoryRoundTripThroughDoctrine(): void
    {
        $store = $this->objectManager->get(ModerationCaseStoreInterface::class);
        self::assertInstanceOf(ModerationCaseStoreInterface::class, $store);
        $case = new ModerationCase(
            ModerationCaseId::create(),
            'test.persistence',
            'ar',
            ['title' => 'Immutable'],
            ['email' => 'private@example.test'],
            new DateTimeImmutable('2026-10-27T10:00:00+00:00'),
        );
        $case->approve(
            'persistence-test',
            new DateTimeImmutable('2026-10-27T11:00:00+00:00'),
            'accepted',
            'external-123',
        );
        $store->transactional(static fn() => $store->add($case));

        $entityManager = $this->objectManager->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->clear();
        $reloaded = $store->find($case->getId());

        self::assertInstanceOf(ModerationCase::class, $reloaded);
        self::assertSame(ModerationStatus::Approved, $reloaded->getStatus());
        self::assertSame(['title' => 'Immutable'], $reloaded->getPayload());
        self::assertSame(['email' => 'private@example.test'], $reloaded->getPrivateMetadata());
        self::assertCount(2, $reloaded->getAuditEntries());
        self::assertSame($reloaded, $reloaded->getAuditEntries()[0]->getModerationCase());

        $entityManager->remove($reloaded);
        $entityManager->flush();
    }
}
