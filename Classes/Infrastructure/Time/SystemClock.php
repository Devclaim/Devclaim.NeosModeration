<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Infrastructure\Time;

use DateTimeImmutable;
use Devclaim\NeosModeration\Domain\Service\ClockInterface;
use Neos\Flow\Annotations as Flow;

#[Flow\Scope('singleton')]
final class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        // Doctrine's datetime_immutable stores no zone and reads values back in the PHP default zone.
        return new DateTimeImmutable('now');
    }
}
