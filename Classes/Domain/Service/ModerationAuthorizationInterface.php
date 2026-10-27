<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\Service;

interface ModerationAuthorizationInterface
{
    public function canAccessType(ModerationTypeHandlerInterface $handler): bool;

    public function assertCanAccessType(ModerationTypeHandlerInterface $handler): void;

    public function canViewPrivateMetadata(ModerationTypeHandlerInterface $handler): bool;
}
