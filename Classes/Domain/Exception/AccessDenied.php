<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\Exception;

use Neos\Flow\Security\Exception\AccessDeniedException;

final class AccessDenied extends AccessDeniedException
{
    public static function forType(string $typeKey): self
    {
        return new self(sprintf('Access to moderation type "%s" is denied.', $typeKey), 1793103604);
    }
}
