<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Tests\Unit\Fixtures;

use Devclaim\NeosModeration\Domain\Model\ModerationCase;
use Devclaim\NeosModeration\Domain\Repository\ModerationCaseQuery;
use Devclaim\NeosModeration\Domain\Repository\ModerationCaseStoreInterface;
use Devclaim\NeosModeration\Domain\ValueObject\ModerationCaseId;

final class InMemoryModerationCaseStore implements ModerationCaseStoreInterface
{
    /** @var array<string, ModerationCase> */
    public array $cases = [];
    public int $lockedReads = 0;
    public int $transactions = 0;
    public int $updates = 0;
    public bool $inTransaction = false;

    public function transactional(callable $operation): mixed
    {
        $this->transactions++;
        $this->inTransaction = true;
        try {
            return $operation();
        } finally {
            $this->inTransaction = false;
        }
    }

    public function add(ModerationCase $case): void
    {
        $this->cases[$case->getId()->value] = $case;
    }

    public function update(ModerationCase $case): void
    {
        $this->updates++;
        $this->cases[$case->getId()->value] = $case;
    }

    public function find(ModerationCaseId $id): ?ModerationCase
    {
        return $this->cases[$id->value] ?? null;
    }

    public function findForUpdate(ModerationCaseId $id): ?ModerationCase
    {
        $this->lockedReads++;
        return $this->find($id);
    }

    public function countByStatus(array $allowedTypeKeys): array
    {
        $counts = [];
        foreach ($this->cases as $case) {
            if (in_array($case->getTypeKey(), $allowedTypeKeys, true)) {
                $counts[$case->getStatus()->value] = ($counts[$case->getStatus()->value] ?? 0) + 1;
            }
        }
        return $counts;
    }

    public function findByQuery(ModerationCaseQuery $query): array
    {
        return array_values(array_filter(
            $this->cases,
            static fn(ModerationCase $case): bool => in_array($case->getTypeKey(), $query->allowedTypeKeys, true)
                && ($query->status === null || $case->getStatus() === $query->status)
                && ($query->typeKey === null || $case->getTypeKey() === $query->typeKey),
        ));
    }
}
