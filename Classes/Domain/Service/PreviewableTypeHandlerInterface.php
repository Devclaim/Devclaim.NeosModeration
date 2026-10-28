<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\Service;

use Devclaim\NeosModeration\Domain\Model\ModerationCase;

/**
 * A type the moderation app can show as the website would: a page of the frontend, in an iframe, that receives the
 * data by postMessage ({"type": "moderation:preview", "data": …}) and can send corrections back
 * ({"type": "moderation:correct", "field": …, "value": …}) for correctable fields it lets moderators edit in place.
 */
interface PreviewableTypeHandlerInterface extends ModerationTypeHandlerInterface
{
    /** an absolute path or URL of the preview page */
    public function getPreviewUri(): string;

    /**
     * @param bool $asSubmitted from the payload as submitted instead of the effective payload (with corrections)
     * @return array<string, mixed> what the preview page renders
     */
    public function getPreviewData(ModerationCase $case, bool $asSubmitted = false): array;
}
