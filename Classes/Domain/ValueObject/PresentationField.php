<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\ValueObject;

final readonly class PresentationField
{
    public function __construct(
        public string $label,
        public string $value,
    ) {
    }
}
