<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Controller;

use Devclaim\NeosModeration\Application\ModerationCaseService;
use Devclaim\NeosModeration\Domain\Model\ModerationAuditEntry;
use Devclaim\NeosModeration\Domain\Model\ModerationCase;
use Devclaim\NeosModeration\Domain\Model\ModerationStatus;
use Devclaim\NeosModeration\Domain\Service\ModerationTypeHandlerInterface;
use Devclaim\NeosModeration\Domain\ValueObject\ModerationCaseId;
use GuzzleHttp\Psr7\Response;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Context;
use Neos\Fusion\View\FusionView;
use Neos\Neos\Controller\Module\AbstractModuleController;
use Psr\Http\Message\ResponseInterface;

#[Flow\Scope('singleton')]
final class ModerationController extends AbstractModuleController
{
    protected $defaultViewObjectName = FusionView::class;

    public function __construct(
        private readonly ModerationCaseService $moderationCaseService,
        private readonly Context $securityContext,
    ) {
    }

    public function indexAction(string $status = 'pending', ?string $type = null): void
    {
        $selectedStatus = $status === 'all' ? null : ModerationStatus::tryFrom($status);
        if ($status !== 'all' && $selectedStatus === null) {
            $selectedStatus = ModerationStatus::Pending;
            $status = $selectedStatus->value;
        }

        $types = $this->moderationCaseService->getAccessibleTypes();
        $cases = $this->moderationCaseService->list($selectedStatus, $type === '' ? null : $type);
        $this->view->assignMultiple([
            'cases' => array_map(fn(ModerationCase $case): array => $this->caseListItem($case, $types), $cases),
            'types' => array_map($this->typeViewModel(...), $types),
            'selectedStatus' => $status,
            'selectedType' => $type ?? '',
        ]);
    }

    public function showAction(string $caseId): void
    {
        $case = $this->moderationCaseService->get(ModerationCaseId::fromString($caseId));
        $types = $this->moderationCaseService->getAccessibleTypes();
        $handler = $types[$case->getTypeKey()];
        $presentation = $handler->present($case);
        $privateMetadata = $this->moderationCaseService->canViewPrivateMetadata($case)
            ? $case->getPrivateMetadata()
            : null;

        $this->view->assignMultiple([
            'case' => $this->caseDetail($case, $handler),
            'presentation' => [
                'title' => $presentation->title,
                'fields' => array_map(
                    static fn($field): array => ['label' => $field->label, 'value' => $field->value],
                    $presentation->fields,
                ),
            ],
            'privateMetadata' => $privateMetadata === null ? null : $this->encodeJson($privateMetadata),
            'auditEntries' => array_map($this->auditViewModel(...), $case->getAuditEntries()),
            'csrfToken' => $this->securityContext->getCsrfProtectionToken(),
        ]);
    }

    public function approveAction(string $caseId, ?string $reason = null): ResponseInterface
    {
        if ($this->request->getHttpRequest()->getMethod() !== 'POST') {
            return new Response(405, ['Allow' => 'POST']);
        }
        $this->moderationCaseService->approve(ModerationCaseId::fromString($caseId), $reason);
        $this->addFlashMessage('The moderation case was approved.');
        $this->redirect('show', arguments: ['caseId' => $caseId]);
    }

    public function rejectAction(string $caseId, ?string $reason = null): ResponseInterface
    {
        if ($this->request->getHttpRequest()->getMethod() !== 'POST') {
            return new Response(405, ['Allow' => 'POST']);
        }
        $this->moderationCaseService->reject(ModerationCaseId::fromString($caseId), $reason);
        $this->addFlashMessage('The moderation case was rejected.');
        $this->redirect('show', arguments: ['caseId' => $caseId]);
    }

    /**
     * @param array<string, ModerationTypeHandlerInterface> $types
     * @return array<string, string>
     */
    private function caseListItem(ModerationCase $case, array $types): array
    {
        return [
            'identifier' => $case->getId()->value,
            'typeKey' => $case->getTypeKey(),
            'typeLabel' => $types[$case->getTypeKey()]->getLabel(),
            'status' => $case->getStatus()->value,
            'locale' => $case->getLocale(),
            'submittedAt' => $case->getSubmittedAt()->format('Y-m-d H:i:s T'),
        ];
    }

    /** @return array<string, string> */
    private function caseDetail(ModerationCase $case, ModerationTypeHandlerInterface $handler): array
    {
        return [
            ...$this->caseListItem($case, [$case->getTypeKey() => $handler]),
            'decidedAt' => $case->getDecidedAt()?->format('Y-m-d H:i:s T') ?? '',
            'moderatorIdentifier' => $case->getModeratorIdentifier() ?? '',
            'decisionReason' => $case->getDecisionReason() ?? '',
            'externalReference' => $case->getExternalReference() ?? '',
        ];
    }

    /** @return array<string, string> */
    private function typeViewModel(ModerationTypeHandlerInterface $handler): array
    {
        return [
            'key' => $handler->getTypeKey(),
            'label' => $handler->getLabel(),
            'icon' => $handler->getIcon(),
        ];
    }

    /** @return array<string, string> */
    private function auditViewModel(ModerationAuditEntry $entry): array
    {
        return [
            'event' => $entry->getEvent()->value,
            'actor' => $entry->getActorIdentifier() ?? 'system',
            'recordedAt' => $entry->getRecordedAt()->format('Y-m-d H:i:s T'),
            'context' => $this->encodeJson($entry->getContext()),
        ];
    }

    /** @param array<mixed> $value */
    private function encodeJson(array $value): string
    {
        return (string)json_encode(
            $value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }
}
