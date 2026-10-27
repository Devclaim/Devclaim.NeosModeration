<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Tests\Unit\Fixtures;

use DateTimeImmutable;
use Devclaim\NeosModeration\Domain\Service\ClockInterface;

final readonly class FixedClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $time)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->time;
    }
}
