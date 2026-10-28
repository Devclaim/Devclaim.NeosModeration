<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\ValueObject;

final readonly class CasePresentation
{
    /**
     * @param list<PresentationField> $fields
     * @param string $summary one line for lists, e.g. the date and the kind of a case
     * @param list<array{label: string, uri: string, note?: string}> $links e.g. to what an approval created
     */
    public function __construct(
        public string $title,
        public array $fields,
        public string $summary = '',
        public array $links = [],
    ) {
    }
}
