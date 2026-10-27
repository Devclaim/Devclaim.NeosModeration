<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Tests\Unit\Application;

use DateTimeImmutable;
use Devclaim\NeosModeration\Application\SubmitModerationCase;
use Devclaim\NeosModeration\Domain\Model\AuditEvent;
use Devclaim\NeosModeration\Domain\Service\ModerationTypeRegistry;
use Devclaim\NeosModeration\Tests\Unit\Fixtures\FixedClock;
use Devclaim\NeosModeration\Tests\Unit\Fixtures\InMemoryModerationCaseStore;
use Devclaim\NeosModeration\Tests\Unit\Fixtures\TestTypeHandler;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class SubmitModerationCaseTest extends TestCase
{
    public function testSubmissionStoresOnlyHandlerValidatedPayloadAndInitialAuditEntry(): void
    {
        $handler = new TestTypeHandler();
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->method('get')->willReturn($handler);
        $registry = new ModerationTypeRegistry($objectManager);
        (new ReflectionProperty($registry, 'typeConfiguration'))->setValue(
            $registry,
            ['test.example' => ['handler' => TestTypeHandler::class]],
        );
        $store = new InMemoryModerationCaseStore();
        $service = new SubmitModerationCase(
            $registry,
            $store,
            new FixedClock(new DateTimeImmutable('2026-10-27T10:00:00+00:00')),
        );

        $case = $service->submit(
            'test.example',
            'tr',
            ['title' => '  Validated  ', 'unknown' => 'discarded'],
            ['email' => 'private@example.test'],
        );

        self::assertSame(['title' => 'Validated'], $case->getPayload());
        self::assertSame('tr', $case->getLocale());
        self::assertSame(AuditEvent::Submitted, $case->getAuditEntries()[0]->getEvent());
        self::assertSame($case, $store->find($case->getId()));
        self::assertSame(1, $store->transactions);
    }
}
