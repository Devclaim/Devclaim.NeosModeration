<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\ValueObject;

use InvalidArgumentException;
use Neos\Flow\Utility\Algorithms;

final readonly class ModerationCaseId implements \Stringable
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    private function __construct(public string $value)
    {
        if (preg_match(self::UUID_PATTERN, $value) !== 1) {
            throw new InvalidArgumentException(sprintf(
                '"%s" is not a valid UUID v4 moderation case identifier.',
                $value,
            ));
        }
    }

    public static function create(): self
    {
        return new self(Algorithms::generateUUID());
    }

    public static function fromString(string $value): self
    {
        return new self(strtolower($value));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
