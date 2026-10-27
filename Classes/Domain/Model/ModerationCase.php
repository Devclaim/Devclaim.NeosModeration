<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\Model;

use DateTimeImmutable;
use Devclaim\NeosModeration\Domain\Exception\CaseAlreadyDecided;
use Devclaim\NeosModeration\Domain\ValueObject\ModerationCaseId;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Neos\Flow\Annotations as Flow;

#[Flow\Entity]
#[ORM\Table(
    name: 'devclaim_moderation_case',
    indexes: [
        new ORM\Index(name: 'moderation_status_submitted_idx', columns: ['status', 'submitted_at']),
        new ORM\Index(name: 'moderation_type_status_idx', columns: ['type_key', 'status']),
    ],
)]
class ModerationCase
{
    #[ORM\Id]
    #[ORM\Column(name: 'identifier', type: 'string', length: 36)]
    protected string $identifier;

    #[ORM\Column(name: 'type_key', type: 'string', length: 150)]
    protected string $typeKey;

    #[ORM\Column(name: 'status', type: 'string', length: 16, enumType: ModerationStatus::class)]
    protected ModerationStatus $status;

    #[ORM\Column(name: 'locale', type: 'string', length: 35)]
    protected string $locale;

    /**
     * @var array
     * @phpstan-var array<string, mixed>
     */
    #[ORM\Column(name: 'payload', type: 'json')]
    protected array $payload;

    /**
     * @var array|null
     * @phpstan-var array<string, mixed>|null
     */
    #[ORM\Column(name: 'private_metadata', type: 'json', nullable: true)]
    protected ?array $privateMetadata;

    #[ORM\Column(name: 'submitted_at', type: 'datetime_immutable')]
    protected DateTimeImmutable $submittedAt;

    #[ORM\Column(name: 'decided_at', type: 'datetime_immutable', nullable: true)]
    protected ?DateTimeImmutable $decidedAt = null;

    #[ORM\Column(name: 'moderator_identifier', type: 'string', length: 255, nullable: true)]
    protected ?string $moderatorIdentifier = null;

    #[ORM\Column(name: 'decision_reason', type: 'text', nullable: true)]
    protected ?string $decisionReason = null;

    #[ORM\Column(name: 'external_reference', type: 'string', length: 500, nullable: true)]
    protected ?string $externalReference = null;

    #[ORM\Version]
    #[ORM\Column(name: 'version', type: 'integer')]
    protected int $version = 1;

    /**
     * @var Collection<ModerationAuditEntry>
     * @phpstan-var Collection<int, ModerationAuditEntry>
     */
    #[ORM\OneToMany(
        mappedBy: 'moderationCase',
        targetEntity: ModerationAuditEntry::class,
        cascade: ['persist'],
        fetch: 'EAGER',
    )]
    #[ORM\OrderBy(['recordedAt' => 'ASC'])]
    protected Collection $auditEntries;

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $privateMetadata
     */
    public function __construct(
        ModerationCaseId $identifier,
        string $typeKey,
        string $locale,
        array $payload,
        ?array $privateMetadata,
        DateTimeImmutable $submittedAt,
    ) {
        if ($typeKey === '' || strlen($typeKey) > 150) {
            throw new InvalidArgumentException('The moderation type key must contain between 1 and 150 characters.');
        }
        if ($locale === '' || strlen($locale) > 35) {
            throw new InvalidArgumentException('The submitted locale must contain between 1 and 35 characters.');
        }

        $this->identifier = $identifier->value;
        $this->typeKey = $typeKey;
        $this->status = ModerationStatus::Pending;
        $this->locale = $locale;
        $this->payload = $payload;
        $this->privateMetadata = $privateMetadata;
        $this->submittedAt = $submittedAt;
        $this->auditEntries = new ArrayCollection();
        $this->appendAudit(AuditEvent::Submitted, null, $submittedAt, ['typeKey' => $typeKey, 'locale' => $locale]);
    }

    /** @param array<string, scalar|null> $auditContext */
    public function approve(
        string $moderatorIdentifier,
        DateTimeImmutable $decidedAt,
        ?string $reason,
        ?string $externalReference,
        array $auditContext = [],
    ): void {
        $this->assertPending();
        $this->status = ModerationStatus::Approved;
        $this->recordDecision($moderatorIdentifier, $decidedAt, $reason);
        $this->externalReference = self::normalizeOptionalString($externalReference, 500, 'external reference');
        $this->appendAudit(AuditEvent::Approved, $moderatorIdentifier, $decidedAt, $auditContext);
    }

    /** @param array<string, scalar|null> $auditContext */
    public function reject(
        string $moderatorIdentifier,
        DateTimeImmutable $decidedAt,
        ?string $reason,
        array $auditContext = [],
    ): void {
        $this->assertPending();
        $this->status = ModerationStatus::Rejected;
        $this->recordDecision($moderatorIdentifier, $decidedAt, $reason);
        $this->appendAudit(AuditEvent::Rejected, $moderatorIdentifier, $decidedAt, $auditContext);
    }

    private function assertPending(): void
    {
        if ($this->status !== ModerationStatus::Pending) {
            throw CaseAlreadyDecided::withStatus($this->status);
        }
    }

    private function recordDecision(string $moderatorIdentifier, DateTimeImmutable $decidedAt, ?string $reason): void
    {
        if ($moderatorIdentifier === '' || strlen($moderatorIdentifier) > 255) {
            throw new InvalidArgumentException('The moderator identifier must contain between 1 and 255 characters.');
        }
        $this->moderatorIdentifier = $moderatorIdentifier;
        $this->decidedAt = $decidedAt;
        $this->decisionReason = self::normalizeOptionalString($reason, 4000, 'decision reason');
    }

    private static function normalizeOptionalString(?string $value, int $maximumLength, string $label): ?string
    {
        $value = $value === null ? null : trim($value);
        if ($value === '') {
            return null;
        }
        if ($value !== null && strlen($value) > $maximumLength) {
            throw new InvalidArgumentException(sprintf(
                'The %s must not exceed %d characters.',
                $label,
                $maximumLength,
            ));
        }
        return $value;
    }

    /** @param array<string, scalar|null> $context */
    private function appendAudit(
        AuditEvent $event,
        ?string $actorIdentifier,
        DateTimeImmutable $recordedAt,
        array $context,
    ): void {
        $this->auditEntries->add(new ModerationAuditEntry($this, $event, $actorIdentifier, $recordedAt, $context));
    }

    public function getId(): ModerationCaseId
    {
        return ModerationCaseId::fromString($this->identifier);
    }

    public function getTypeKey(): string
    {
        return $this->typeKey;
    }

    public function getStatus(): ModerationStatus
    {
        return $this->status;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /** @return array<string, mixed>|null */
    public function getPrivateMetadata(): ?array
    {
        return $this->privateMetadata;
    }

    public function getSubmittedAt(): DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function getDecidedAt(): ?DateTimeImmutable
    {
        return $this->decidedAt;
    }

    public function getModeratorIdentifier(): ?string
    {
        return $this->moderatorIdentifier;
    }

    public function getDecisionReason(): ?string
    {
        return $this->decisionReason;
    }

    public function getExternalReference(): ?string
    {
        return $this->externalReference;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    /** @return list<ModerationAuditEntry> */
    public function getAuditEntries(): array
    {
        return array_values($this->auditEntries->toArray());
    }
}
