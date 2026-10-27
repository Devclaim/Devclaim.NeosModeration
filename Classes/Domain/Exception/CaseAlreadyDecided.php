<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\Exception;

use Devclaim\NeosModeration\Domain\Model\ModerationStatus;
use DomainException;

final class CaseAlreadyDecided extends DomainException
{
    public static function withStatus(ModerationStatus $status): self
    {
        return new self(sprintf('The moderation case was already %s.', $status->value), 1793103601);
    }
}
