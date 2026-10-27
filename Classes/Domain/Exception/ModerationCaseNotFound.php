<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\Exception;

use Devclaim\NeosModeration\Domain\ValueObject\ModerationCaseId;
use RuntimeException;

final class ModerationCaseNotFound extends RuntimeException
{
    public static function withId(ModerationCaseId $id): self
    {
        return new self(sprintf('Moderation case %s was not found.', $id->value), 1793103602);
    }
}
