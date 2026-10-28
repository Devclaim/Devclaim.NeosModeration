<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Infrastructure\Security;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Flow\Security\Context as SecurityContext;

/**
 * Moderators who may not publish to live land in the moderation app after the login, also when they may open the
 * content module (e.g. to edit what they moderate): moderating is their main task, often on a phone. Everybody else
 * keeps the start module of the setting Neos.Neos.moduleConfiguration.preferredStartModules.
 */
#[Flow\Aspect]
#[Flow\Scope('singleton')]
class ModeratorStartAspect
{
    #[Flow\Inject]
    protected SecurityContext $securityContext;

    #[Flow\Around('method(Neos\Neos\Service\BackendRedirectionService->getAfterLoginRedirectionUri())')]
    public function startModeratorsInTheApp(JoinPointInterface $joinPoint): ?string
    {
        if (
            $this->securityContext->hasRole('Devclaim.NeosModeration:Moderator')
            && !$this->securityContext->hasRole('Neos.Neos:LivePublisher')
        ) {
            return '/neos/moderation';
        }

        return $joinPoint->getAdviceChain()->proceed($joinPoint);
    }
}
