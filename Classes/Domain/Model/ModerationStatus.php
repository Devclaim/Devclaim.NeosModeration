<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\Model;

enum ModerationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
