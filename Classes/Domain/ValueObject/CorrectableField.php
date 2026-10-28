<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\ValueObject;

/**
 * A payload field moderators can correct, as the moderation app shows it: its editor ("text", "textarea", "richText",
 * "date", "time" or "select" with options) in a group of the details
 */
final readonly class CorrectableField
{
    /** @param list<array{value: string, label: string}> $options */
    public function __construct(
        public string $name,
        public string $label,
        public string $editor,
        public string $group = '',
        public array $options = [],
        public ?int $maxLength = null,
        public bool $required = false,
    ) {
    }
}
