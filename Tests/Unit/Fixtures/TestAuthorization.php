<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Tests\Unit\Fixtures;

use Devclaim\NeosModeration\Domain\Exception\AccessDenied;
use Devclaim\NeosModeration\Domain\Service\ModerationAuthorizationInterface;
use Devclaim\NeosModeration\Domain\Service\ModerationTypeHandlerInterface;

final class TestAuthorization implements ModerationAuthorizationInterface
{
    public function __construct(
        public bool $allowed = true,
        public bool $privateAllowed = false,
    ) {
    }

    public function canAccessType(ModerationTypeHandlerInterface $handler): bool
    {
        return $this->allowed;
    }

    public function assertCanAccessType(ModerationTypeHandlerInterface $handler): void
    {
        if (!$this->allowed) {
            throw AccessDenied::forType($handler->getTypeKey());
        }
    }

    public function canViewPrivateMetadata(ModerationTypeHandlerInterface $handler): bool
    {
        return $this->privateAllowed;
    }
}
