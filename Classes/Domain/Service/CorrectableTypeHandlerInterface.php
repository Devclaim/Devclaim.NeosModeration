<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\Service;

use Devclaim\NeosModeration\Domain\Exception\InvalidModerationData;
use Devclaim\NeosModeration\Domain\Model\ModerationCase;
use Devclaim\NeosModeration\Domain\ValueObject\CorrectableField;

/**
 * A type whose cases moderators can correct before deciding. The submitted payload stays unchanged; approval works
 * with the payload and the corrections ({@see ModerationCase::getEffectivePayload()}).
 */
interface CorrectableTypeHandlerInterface extends ModerationTypeHandlerInterface
{
    /** @return list<CorrectableField> */
    public function getCorrectableFields(ModerationCase $case): array;

    /**
     * Checks the corrected values like submitted ones; returns them normalized
     *
     * @param array<string, mixed> $corrections field => value, only correctable fields
     * @return array<string, mixed>
     * @throws InvalidModerationData
     */
    public function validateCorrections(ModerationCase $case, array $corrections): array;
}
