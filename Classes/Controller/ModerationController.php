<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Controller;

use Neos\Flow\Annotations as Flow;
use Neos\Neos\Controller\Module\AbstractModuleController;

/**
 * The backend module "moderation" (the entry of the Neos menu and of preferredStartModules). Its address
 * /neos/moderation is routed to the moderation app before Neos (ModerationAppController); should a request still
 * reach the module frame, it goes to the app.
 */
#[Flow\Scope('singleton')]
final class ModerationController extends AbstractModuleController
{
    public function indexAction(): void
    {
        $this->redirectToUri('/neos/moderation');
    }
}
