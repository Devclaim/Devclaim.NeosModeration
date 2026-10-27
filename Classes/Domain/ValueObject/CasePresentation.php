<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\ValueObject;

final readonly class CasePresentation
{
    /** @param list<PresentationField> $fields */
    public function __construct(
        public string $title,
        public array $fields,
    ) {
    }
}
