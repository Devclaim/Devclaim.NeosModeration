<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\Service;

use DateTimeImmutable;

interface ClockInterface
{
    public function now(): DateTimeImmutable;
}
