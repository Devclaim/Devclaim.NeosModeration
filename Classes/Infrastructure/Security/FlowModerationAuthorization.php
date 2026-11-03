<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Infrastructure\Security;

use Devclaim\NeosModeration\Domain\Exception\AccessDenied;
use Devclaim\NeosModeration\Domain\Service\ModerationAuthorizationInterface;
use Devclaim\NeosModeration\Domain\Service\ModerationTypeHandlerInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Authorization\PrivilegeManagerInterface;

/**
 * Who may moderate a type and see its private metadata: whoever the policy grants the privilege targets of its handler,
 * nothing else (administrators too: a project grants them its types, e.g. as a parent role, README "Registering a type")
 */
#[Flow\Scope('singleton')]
final class FlowModerationAuthorization implements ModerationAuthorizationInterface
{
    public function __construct(
        private readonly PrivilegeManagerInterface $privilegeManager,
    ) {
    }

    public function canAccessType(ModerationTypeHandlerInterface $handler): bool
    {
        return $this->privilegeManager->isPrivilegeTargetGranted($handler->getPermissionIdentifier());
    }

    public function assertCanAccessType(ModerationTypeHandlerInterface $handler): void
    {
        if (!$this->canAccessType($handler)) {
            throw AccessDenied::forType($handler->getTypeKey());
        }
    }

    public function canViewPrivateMetadata(ModerationTypeHandlerInterface $handler): bool
    {
        $permissionIdentifier = $handler->getPrivateMetadataPermissionIdentifier();
        return $permissionIdentifier !== null
            && $this->privilegeManager->isPrivilegeTargetGranted($permissionIdentifier);
    }
}
