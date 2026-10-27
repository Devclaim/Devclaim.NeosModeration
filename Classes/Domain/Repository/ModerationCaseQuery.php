<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\Repository;

use Devclaim\NeosModeration\Domain\Model\ModerationStatus;

final readonly class ModerationCaseQuery
{
    /** @param list<string> $allowedTypeKeys */
    public function __construct(
        public array $allowedTypeKeys,
        public ?ModerationStatus $status = ModerationStatus::Pending,
        public ?string $typeKey = null,
        public int $limit = 100,
        public int $offset = 0,
    ) {
    }
}
