<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\Service;

use Devclaim\NeosModeration\Domain\Model\ModerationCase;
use Devclaim\NeosModeration\Domain\ValueObject\RejectionResult;

interface RejectionHandlerInterface
{
    public function reject(ModerationCase $case, ?string $reason): RejectionResult;
}
