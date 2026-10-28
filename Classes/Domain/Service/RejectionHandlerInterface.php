<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\Service;

use Devclaim\NeosModeration\Domain\Model\ModerationCase;
use Devclaim\NeosModeration\Domain\ValueObject\RejectionResult;

/**
 * A type that undoes what it prepared when a case is rejected (e.g. discards the content it created on submission).
 * Like an approval, it runs outside of a transaction and must be idempotent by the case id: a repeated or concurrent
 * rejection runs it again.
 */
interface RejectionHandlerInterface
{
    public function reject(ModerationCase $case, ?string $reason): RejectionResult;
}
