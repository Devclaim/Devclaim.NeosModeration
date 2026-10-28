<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\Exception;

use InvalidArgumentException;

/**
 * Submitted or corrected data a type handler refuses: the errors per field as codes (e.g. "required", "tooLong",
 * "invalid") that a client turns into messages
 */
class InvalidModerationData extends InvalidArgumentException
{
    /** @param array<string, string> $errors */
    public function __construct(
        public readonly array $errors,
        string $message = 'Invalid moderation data',
        int $code = 1793150001,
    ) {
        parent::__construct($message . ': ' . json_encode($errors), $code);
    }
}
