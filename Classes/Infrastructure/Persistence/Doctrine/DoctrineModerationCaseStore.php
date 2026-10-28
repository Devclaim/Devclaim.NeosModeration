<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Infrastructure\Persistence\Doctrine;

use Devclaim\NeosModeration\Domain\Model\ModerationCase;
use Devclaim\NeosModeration\Domain\Model\ModerationStatus;
use Devclaim\NeosModeration\Domain\Repository\ModerationCaseQuery;
use Devclaim\NeosModeration\Domain\Repository\ModerationCaseStoreInterface;
use Devclaim\NeosModeration\Domain\ValueObject\ModerationCaseId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;
use Neos\Flow\Annotations as Flow;

#[Flow\Scope('singleton')]
final class DoctrineModerationCaseStore implements ModerationCaseStoreInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function transactional(callable $operation): mixed
    {
        return $this->entityManager->wrapInTransaction(static fn(): mixed => $operation());
    }

    public function add(ModerationCase $case): void
    {
        $this->entityManager->persist($case);
    }

    public function update(ModerationCase $case): void
    {
        // Flow maps entities with DEFERRED_EXPLICIT change tracking, so a changed case must be persisted again.
        $this->entityManager->persist($case);
    }

    public function find(ModerationCaseId $id): ?ModerationCase
    {
        return $this->entityManager->find(ModerationCase::class, $id->value);
    }

    public function findForUpdate(ModerationCaseId $id): ?ModerationCase
    {
        $case = $this->entityManager->createQueryBuilder()
            ->select('moderationCase')
            ->from(ModerationCase::class, 'moderationCase')
            ->where('moderationCase.identifier = :identifier')
            ->setParameter('identifier', $id->value)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
        return $case instanceof ModerationCase ? $case : null;
    }

    public function findByQuery(ModerationCaseQuery $query): array
    {
        if ($query->allowedTypeKeys === []) {
            return [];
        }

        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('moderationCase')
            ->from(ModerationCase::class, 'moderationCase')
            ->where('moderationCase.typeKey IN (:allowedTypeKeys)')
            ->setParameter('allowedTypeKeys', $query->allowedTypeKeys)
            ->orderBy('moderationCase.submittedAt', 'DESC')
            ->setMaxResults(max(1, min(250, $query->limit)))
            ->setFirstResult(max(0, $query->offset));

        if ($query->status !== null) {
            $queryBuilder
                ->andWhere('moderationCase.status = :status')
                ->setParameter('status', $query->status->value);
        }
        if ($query->typeKey !== null) {
            $queryBuilder
                ->andWhere('moderationCase.typeKey = :typeKey')
                ->setParameter('typeKey', $query->typeKey);
        }

        /** @var list<ModerationCase> $cases */
        $cases = $queryBuilder->getQuery()->getResult();
        return $cases;
    }

    public function countByStatus(array $allowedTypeKeys): array
    {
        if ($allowedTypeKeys === []) {
            return [];
        }
        /** @var list<array{status: ModerationStatus|string, number: int|string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('moderationCase.status AS status', 'COUNT(moderationCase.identifier) AS number')
            ->from(ModerationCase::class, 'moderationCase')
            ->where('moderationCase.typeKey IN (:allowedTypeKeys)')
            ->setParameter('allowedTypeKeys', $allowedTypeKeys)
            ->groupBy('moderationCase.status')
            ->getQuery()
            ->getArrayResult();
        $counts = [];
        foreach ($rows as $row) {
            $status = $row['status'] instanceof ModerationStatus ? $row['status']->value : (string)$row['status'];
            $counts[$status] = (int)$row['number'];
        }
        return $counts;
    }
}
