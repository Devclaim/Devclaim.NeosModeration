<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\Model;

enum AuditEvent: string
{
    case Submitted = 'submitted';
    case Corrected = 'corrected';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
