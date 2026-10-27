<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Infrastructure\Security;

use Devclaim\NeosModeration\Domain\Service\ActorProviderInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Context;
use Neos\Flow\Security\Exception\AuthenticationRequiredException;

#[Flow\Scope('singleton')]
final class FlowActorProvider implements ActorProviderInterface
{
    public function __construct(private readonly Context $securityContext)
    {
    }

    public function requireActorIdentifier(): string
    {
        $account = $this->securityContext->getAccount();
        if ($account === null) {
            throw new AuthenticationRequiredException(
                'A moderation decision requires an authenticated account.',
                1793103605,
            );
        }
        return $account->getAccountIdentifier();
    }
}
