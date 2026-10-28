<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\Service;

use Devclaim\NeosModeration\Domain\Model\ModerationCase;

/**
 * A type that prepares something as soon as a case arrives, e.g. the content moderators review and edit before
 * deciding. It runs after the case is stored, outside of a transaction (it may write to another store, like the
 * event-sourced content repository). A failure does not undo the submission: it is logged, and the handler must cope
 * with a missing result when the case is decided (e.g. create it then). Repeated calls must find what they created.
 */
interface SubmissionAwareTypeHandlerInterface extends ModerationTypeHandlerInterface
{
    public function submitted(ModerationCase $case): void;
}
