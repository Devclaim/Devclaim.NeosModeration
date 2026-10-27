<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Utility\Algorithms;

#[Flow\Entity]
#[ORM\Table(name: 'devclaim_moderation_audit_entry')]
class ModerationAuditEntry
{
    #[ORM\Id]
    #[ORM\Column(name: 'identifier', type: 'string', length: 36)]
    protected string $identifier;

    #[ORM\ManyToOne(targetEntity: ModerationCase::class, inversedBy: 'auditEntries')]
    #[ORM\JoinColumn(
        name: 'moderation_case_id',
        referencedColumnName: 'identifier',
        nullable: false,
        onDelete: 'CASCADE',
    )]
    protected ModerationCase $moderationCase;

    #[ORM\Column(name: 'event', type: 'string', length: 32, enumType: AuditEvent::class)]
    protected AuditEvent $event;

    #[ORM\Column(name: 'actor_identifier', type: 'string', length: 255, nullable: true)]
    protected ?string $actorIdentifier;

    #[ORM\Column(name: 'recorded_at', type: 'datetime_immutable')]
    protected DateTimeImmutable $recordedAt;

    /**
     * @var array
     * @phpstan-var array<string, scalar|null>
     */
    #[ORM\Column(name: 'context', type: 'json')]
    protected array $context;

    /** @param array<string, scalar|null> $context */
    public function __construct(
        ModerationCase $moderationCase,
        AuditEvent $event,
        ?string $actorIdentifier,
        DateTimeImmutable $recordedAt,
        array $context = [],
    ) {
        $this->identifier = Algorithms::generateUUID();
        $this->moderationCase = $moderationCase;
        $this->event = $event;
        $this->actorIdentifier = $actorIdentifier;
        $this->recordedAt = $recordedAt;
        $this->context = $context;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getModerationCase(): ModerationCase
    {
        return $this->moderationCase;
    }

    public function getEvent(): AuditEvent
    {
        return $this->event;
    }

    public function getActorIdentifier(): ?string
    {
        return $this->actorIdentifier;
    }

    public function getRecordedAt(): DateTimeImmutable
    {
        return $this->recordedAt;
    }

    /** @return array<string, scalar|null> */
    public function getContext(): array
    {
        return $this->context;
    }
}
