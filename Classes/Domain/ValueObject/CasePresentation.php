<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\ValueObject;

final readonly class CasePresentation
{
    /**
     * @param list<PresentationField> $fields
     * @param string $summary one line for lists, e.g. the date and the kind of a case
     */
    public function __construct(
        public string $title,
        public array $fields,
        public string $summary = '',
    ) {
    }
}
