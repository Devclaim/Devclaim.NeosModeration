<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\ValueObject;

final readonly class RejectionResult
{
    /** @param array<string, scalar|null> $auditContext */
    public function __construct(public array $auditContext = [])
    {
    }
}
