<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Infrastructure\Security;

use Devclaim\NeosModeration\Domain\Exception\AccessDenied;
use Devclaim\NeosModeration\Domain\Service\ModerationAuthorizationInterface;
use Devclaim\NeosModeration\Domain\Service\ModerationTypeHandlerInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Authorization\PrivilegeManagerInterface;
use Neos\Flow\Security\Context;

#[Flow\Scope('singleton')]
final class FlowModerationAuthorization implements ModerationAuthorizationInterface
{
    private const ADMINISTRATOR_ROLE = 'Neos.Neos:Administrator';

    public function __construct(
        private readonly Context $securityContext,
        private readonly PrivilegeManagerInterface $privilegeManager,
    ) {
    }

    public function canAccessType(ModerationTypeHandlerInterface $handler): bool
    {
        return $this->isAdministrator()
            || $this->privilegeManager->isPrivilegeTargetGranted($handler->getPermissionIdentifier());
    }

    public function assertCanAccessType(ModerationTypeHandlerInterface $handler): void
    {
        if (!$this->canAccessType($handler)) {
            throw AccessDenied::forType($handler->getTypeKey());
        }
    }

    public function canViewPrivateMetadata(ModerationTypeHandlerInterface $handler): bool
    {
        if ($this->isAdministrator()) {
            return true;
        }
        $permissionIdentifier = $handler->getPrivateMetadataPermissionIdentifier();
        return $permissionIdentifier !== null
            && $this->privilegeManager->isPrivilegeTargetGranted($permissionIdentifier);
    }

    private function isAdministrator(): bool
    {
        return $this->securityContext->hasRole(self::ADMINISTRATOR_ROLE);
    }
}
