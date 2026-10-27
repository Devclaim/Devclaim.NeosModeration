<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\Service;

interface ActorProviderInterface
{
    public function requireActorIdentifier(): string;
}
