<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\ValueObject;

final readonly class CasePresentation
{
    /**
     * @param list<PresentationField> $fields
     * @param string $summary one line for lists, e.g. the date and the kind of a case
     * @param list<array{label: string, uri: string, note?: string}> $links e.g. to where the case is edited, to what
     *     an approval created
     * @param array{approve?: string, reject?: string} $decisionHints what a decision does, for the confirmation
     * @param \DateTimeImmutable|null $editedAt when what the case created was last edited after the submission (e.g. in
     *     Neos), null if it is as submitted: the app then shows the submission next to the current state
     * @param list<array{by: string, at: \DateTimeImmutable}> $edits who edited it since, each with their last
     *     change, the latest first (shown in the history)
     */
    public function __construct(
        public string $title,
        public array $fields,
        public string $summary = '',
        public array $links = [],
        public array $decisionHints = [],
        public ?\DateTimeImmutable $editedAt = null,
        public array $edits = [],
    ) {
    }
}
