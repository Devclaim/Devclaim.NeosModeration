<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\Repository;

use Devclaim\NeosModeration\Domain\Model\ModerationCase;
use Devclaim\NeosModeration\Domain\ValueObject\ModerationCaseId;

interface ModerationCaseStoreInterface
{
    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function transactional(callable $operation): mixed;

    public function add(ModerationCase $case): void;

    /** Schedules a changed case for writing; persistence may track changes explicitly only. */
    public function update(ModerationCase $case): void;

    /** Removes a case with its audit entries */
    public function remove(ModerationCase $case): void;

    public function find(ModerationCaseId $id): ?ModerationCase;

    public function findForUpdate(ModerationCaseId $id): ?ModerationCase;

    /** @return list<ModerationCase> */
    public function findByQuery(ModerationCaseQuery $query): array;

    /**
     * @param list<string> $allowedTypeKeys
     * @return array<string, int> status value => number of cases
     */
    public function countByStatus(array $allowedTypeKeys): array;
}
