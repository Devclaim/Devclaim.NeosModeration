<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\Exception;

use InvalidArgumentException;

final class UnknownModerationType extends InvalidArgumentException
{
    public static function withKey(string $typeKey): self
    {
        return new self(sprintf('Moderation type "%s" is not registered.', $typeKey), 1793103603);
    }
}
