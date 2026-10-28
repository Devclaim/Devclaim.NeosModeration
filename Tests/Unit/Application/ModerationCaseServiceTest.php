<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Tests\Unit\Application;

use DateTimeImmutable;
use Devclaim\NeosModeration\Application\ModerationCaseService;
use Devclaim\NeosModeration\Domain\Exception\AccessDenied;
use Devclaim\NeosModeration\Domain\Exception\CaseAlreadyDecided;
use Devclaim\NeosModeration\Domain\Exception\InvalidModerationData;
use Devclaim\NeosModeration\Domain\Model\ModerationCase;
use Devclaim\NeosModeration\Domain\Model\ModerationStatus;
use Devclaim\NeosModeration\Domain\Service\ModerationTypeRegistry;
use Devclaim\NeosModeration\Domain\ValueObject\ModerationCaseId;
use Devclaim\NeosModeration\Tests\Unit\Fixtures\FixedClock;
use Devclaim\NeosModeration\Tests\Unit\Fixtures\InMemoryModerationCaseStore;
use Devclaim\NeosModeration\Tests\Unit\Fixtures\TestActorProvider;
use Devclaim\NeosModeration\Tests\Unit\Fixtures\TestAuthorization;
use Devclaim\NeosModeration\Tests\Unit\Fixtures\TestTypeHandler;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class ModerationCaseServiceTest extends TestCase
{
    private TestTypeHandler $handler;
    private InMemoryModerationCaseStore $store;
    private TestAuthorization $authorization;
    private ModerationCaseService $service;
    private ModerationCase $case;

    protected function setUp(): void
    {
        $this->handler = new TestTypeHandler();
        $this->store = new InMemoryModerationCaseStore();
        $this->authorization = new TestAuthorization();
        $this->service = new ModerationCaseService(
            $this->registry($this->handler),
            $this->store,
            $this->authorization,
            new TestActorProvider(),
            new FixedClock(new DateTimeImmutable('2026-10-27T11:00:00+00:00')),
        );
        $this->case = new ModerationCase(
            ModerationCaseId::fromString('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'),
            'test.example',
            'de',
            ['title' => 'Test'],
            ['email' => 'private@example.test'],
            new DateTimeImmutable('2026-10-27T10:00:00+00:00'),
        );
        $this->store->add($this->case);
    }

    public function testRepeatedApprovalIsIdempotentAndLocksBeforeDecision(): void
    {
        $firstResult = $this->service->approve($this->case->getId(), 'Accepted');
        $secondResult = $this->service->approve($this->case->getId(), 'Ignored retry reason');

        self::assertSame($firstResult, $secondResult);
        self::assertSame(1, $this->handler->approvalCalls);
        self::assertSame(2, $this->store->lockedReads);
        self::assertSame(ModerationStatus::Approved, $this->case->getStatus());
        self::assertCount(2, $this->case->getAuditEntries());
        self::assertSame('Accepted', $this->case->getDecisionReason());
    }

    public function testDecisionsAreHandedBackToTheStoreForExplicitChangeTracking(): void
    {
        $this->service->approve($this->case->getId());
        $this->service->approve($this->case->getId());

        self::assertSame(1, $this->store->updates);
    }

    public function testCorrectionsKeepThePayloadAndReachTheApproval(): void
    {
        $this->service->correct($this->case->getId(), ['title' => '  Corrected  ']);

        self::assertSame(['title' => 'Test'], $this->case->getPayload());
        self::assertSame(['title' => 'Corrected'], $this->case->getCorrections());
        self::assertSame('corrected', $this->case->getAuditEntries()[1]->getEvent()->value);

        $this->service->approve($this->case->getId());
        self::assertSame(['title' => 'Corrected'], $this->handler->approvedPayload);
    }

    public function testCorrectingBackToTheSubmittedValueRemovesTheCorrection(): void
    {
        $this->service->correct($this->case->getId(), ['title' => 'Corrected']);
        $this->service->correct($this->case->getId(), ['title' => 'Test']);

        self::assertSame([], $this->case->getCorrections());
        self::assertCount(3, $this->case->getAuditEntries());
    }

    public function testInvalidOrUnknownCorrectionsAreRefused(): void
    {
        try {
            $this->service->correct($this->case->getId(), ['title' => ' ']);
            self::fail('An empty title must be refused.');
        } catch (InvalidModerationData $exception) {
            self::assertSame(['title' => 'required'], $exception->errors);
        }

        $this->expectException(InvalidModerationData::class);
        $this->service->correct($this->case->getId(), ['secret' => 'x']);
    }

    public function testDecidedCasesCannotBeCorrected(): void
    {
        $this->service->reject($this->case->getId());

        $this->expectException(CaseAlreadyDecided::class);
        $this->service->correct($this->case->getId(), ['title' => 'Late']);
    }

    public function testOppositeDecisionAfterApprovalIsRejected(): void
    {
        $this->service->approve($this->case->getId());

        $this->expectException(CaseAlreadyDecided::class);
        $this->service->reject($this->case->getId());
    }

    public function testApplicationLayerDeniesDirectDecisionForUnauthorizedType(): void
    {
        $this->authorization->allowed = false;

        $this->expectException(AccessDenied::class);
        $this->service->approve($this->case->getId());
    }

    public function testUnauthorizedTypesAreExcludedFromListsAndPrivateDataIsSeparate(): void
    {
        $this->authorization->allowed = false;
        self::assertSame([], $this->service->list());

        $this->authorization->allowed = true;
        self::assertFalse($this->service->canViewPrivateMetadata($this->case));
        $this->authorization->privateAllowed = true;
        self::assertTrue($this->service->canViewPrivateMetadata($this->case));
    }

    private function registry(TestTypeHandler $handler): ModerationTypeRegistry
    {
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->method('get')->with(TestTypeHandler::class)->willReturn($handler);
        $registry = new ModerationTypeRegistry($objectManager);
        $configuration = new ReflectionProperty($registry, 'typeConfiguration');
        $configuration->setValue($registry, ['test.example' => ['handler' => TestTypeHandler::class]]);
        return $registry;
    }
}
