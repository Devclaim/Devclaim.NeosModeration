<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\ValueObject;

final readonly class ValidatedSubmission
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $privateMetadata
     */
    public function __construct(
        public array $payload,
        public ?array $privateMetadata = null,
    ) {
    }
}
