<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Tests\Unit\Fixtures;

use Devclaim\NeosModeration\Domain\Service\ActorProviderInterface;

final readonly class TestActorProvider implements ActorProviderInterface
{
    public function requireActorIdentifier(): string
    {
        return 'moderator@example.test';
    }
}
