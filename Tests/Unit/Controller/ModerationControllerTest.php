<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Tests\Unit\Controller;

use DateTimeImmutable;
use Devclaim\NeosModeration\Application\ModerationCaseService;
use Devclaim\NeosModeration\Controller\ModerationController;
use Devclaim\NeosModeration\Domain\Service\ModerationTypeRegistry;
use Devclaim\NeosModeration\Tests\Unit\Fixtures\FixedClock;
use Devclaim\NeosModeration\Tests\Unit\Fixtures\InMemoryModerationCaseStore;
use Devclaim\NeosModeration\Tests\Unit\Fixtures\TestActorProvider;
use Devclaim\NeosModeration\Tests\Unit\Fixtures\TestAuthorization;
use GuzzleHttp\Psr7\ServerRequest;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Security\Context;
use Neos\Utility\ObjectAccess;
use PHPUnit\Framework\TestCase;

final class ModerationControllerTest extends TestCase
{
    public function testDecisionActionsRejectGetRequestsBeforeCallingApplicationService(): void
    {
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $registry = new ModerationTypeRegistry($objectManager);
        ObjectAccess::setProperty($registry, 'typeConfiguration', [], true);
        $service = new ModerationCaseService(
            $registry,
            new InMemoryModerationCaseStore(),
            new TestAuthorization(),
            new TestActorProvider(),
            new FixedClock(new DateTimeImmutable('2026-10-27T11:00:00+00:00')),
        );
        $controller = new ModerationController($service, $this->createMock(Context::class));
        ObjectAccess::setProperty(
            $controller,
            'request',
            ActionRequest::fromHttpRequest(new ServerRequest('GET', 'https://example.test/approve')),
            true,
        );

        $approveResponse = $controller->approveAction('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
        $rejectResponse = $controller->rejectAction('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');

        self::assertSame(405, $approveResponse->getStatusCode());
        self::assertSame('POST', $approveResponse->getHeaderLine('Allow'));
        self::assertSame(405, $rejectResponse->getStatusCode());
    }
}
