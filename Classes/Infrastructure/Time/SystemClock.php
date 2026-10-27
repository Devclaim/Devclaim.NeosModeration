<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Infrastructure\Time;

use DateTimeImmutable;
use DateTimeZone;
use Devclaim\NeosModeration\Domain\Service\ClockInterface;
use Neos\Flow\Annotations as Flow;

#[Flow\Scope('singleton')]
final class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
