<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Controller;

use Devclaim\NeosModeration\Application\ModerationCaseService;
use Devclaim\NeosModeration\Domain\Exception\AccessDenied;
use Devclaim\NeosModeration\Domain\Exception\CaseAlreadyDecided;
use Devclaim\NeosModeration\Domain\Exception\InvalidModerationData;
use Devclaim\NeosModeration\Domain\Exception\ModerationCaseNotFound;
use Devclaim\NeosModeration\Domain\Exception\UnknownModerationType;
use Devclaim\NeosModeration\Domain\Model\ModerationAuditEntry;
use Devclaim\NeosModeration\Domain\Model\ModerationCase;
use Devclaim\NeosModeration\Domain\Model\ModerationStatus;
use Devclaim\NeosModeration\Domain\Service\CorrectableTypeHandlerInterface;
use Devclaim\NeosModeration\Domain\Service\ModerationTypeHandlerInterface;
use Devclaim\NeosModeration\Domain\Service\PreviewableTypeHandlerInterface;
use Devclaim\NeosModeration\Domain\ValueObject\ModerationCaseId;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Security\Context as SecurityContext;

/**
 * The JSON API of the moderation app (ModerationAppController), with the Neos backend session: the cases of the
 * types the user may moderate, a case with its presentation, corrections and preview, and the decisions. Every action
 * goes through ModerationCaseService, which checks the type privilege; POST requests need the CSRF token
 * (header X-Flow-Csrftoken).
 *
 * Errors: 404 no such case, 403 not allowed, 409 already decided, 422 {"errors": {field: code}}, 400 a bad request.
 */
#[Flow\Scope('singleton')]
final class ModerationApiController extends ActionController
{
    protected $supportedMediaTypes = ['application/json'];

    /** the most a request body may have */
    private const MAX_BODY_BYTES = 200_000;

    public function __construct(
        private readonly ModerationCaseService $moderationCaseService,
        private readonly SecurityContext $securityContext,
    ) {
    }

    /**
     * GET session: the CSRF token of the session, for pages outside of the app that decide cases (e.g. a bar in the
     * Neos UI); and whether the user may moderate anything at all
     */
    public function sessionAction(): string
    {
        return $this->respond(fn(): array => [
            'csrfToken' => $this->securityContext->getCsrfProtectionToken(),
            'moderates' => $this->moderationCaseService->getAccessibleTypes() !== [],
        ]);
    }

    /** GET cases?status=pending|approved|rejected|all&type=…&q=… */
    public function casesAction(string $status = 'pending', string $type = '', string $q = ''): string
    {
        return $this->respond(function () use ($status, $type, $q): array {
            $types = $this->moderationCaseService->getAccessibleTypes();
            $cases = $this->moderationCaseService->list(
                $status === 'all' ? null : (ModerationStatus::tryFrom($status) ?? ModerationStatus::Pending),
                $type === '' ? null : $type,
            );
            $summaries = array_map(fn(ModerationCase $case): array => $this->summary($case, $types), $cases);
            $query = mb_strtolower(trim($q));
            if ($query !== '') {
                $summaries = array_values(array_filter(
                    $summaries,
                    static fn(array $summary): bool => str_contains(mb_strtolower($summary['searchText']), $query),
                ));
            }

            return [
                'cases' => array_map(static function (array $summary): array {
                    unset($summary['searchText']);
                    return $summary;
                }, $summaries),
                'counts' => $this->moderationCaseService->counts(),
                'types' => array_values(array_map(
                    static fn(ModerationTypeHandlerInterface $handler): array => [
                        'key' => $handler->getTypeKey(),
                        'label' => $handler->getLabel(),
                    ],
                    $types,
                )),
            ];
        });
    }

    /** GET cases/{caseId} */
    public function caseAction(string $caseId): string
    {
        return $this->respond(fn(): array => $this->detail(
            $this->moderationCaseService->get(ModerationCaseId::fromString($caseId)),
        ));
    }

    /** POST cases/{caseId}/corrections {"corrections": {field: value}}: replaces the corrections */
    public function correctAction(string $caseId): string
    {
        return $this->respond(function () use ($caseId): array {
            $corrections = $this->body()['corrections'] ?? null;
            if (!is_array($corrections)) {
                throw new InvalidModerationData(['__general' => 'invalid']);
            }
            return $this->detail(
                $this->moderationCaseService->correct(ModerationCaseId::fromString($caseId), $corrections),
            );
        }, post: true);
    }

    /** POST cases/{caseId}/approve {"reason": …} */
    public function approveAction(string $caseId): string
    {
        return $this->respond(fn(): array => $this->detail($this->moderationCaseService->approve(
            ModerationCaseId::fromString($caseId),
            $this->reason(),
        )), post: true);
    }

    /** POST cases/{caseId}/reject {"reason": …} */
    public function rejectAction(string $caseId): string
    {
        return $this->respond(fn(): array => $this->detail($this->moderationCaseService->reject(
            ModerationCaseId::fromString($caseId),
            $this->reason(),
        )), post: true);
    }

    /** @param callable(): array<string, mixed> $action */
    private function respond(callable $action, bool $post = false): string
    {
        $this->response->setContentType('application/json');
        $this->response->setHttpHeader('Cache-Control', 'no-store');
        if ($post && $this->request->getHttpRequest()->getMethod() !== 'POST') {
            $this->response->setStatusCode(405);
            $this->response->setHttpHeader('Allow', 'POST');
            return $this->json(['error' => 'methodNotAllowed']);
        }
        try {
            return $this->json($action());
        } catch (ModerationCaseNotFound | UnknownModerationType) {
            $this->response->setStatusCode(404);
            return $this->json(['error' => 'notFound']);
        } catch (AccessDenied) {
            $this->response->setStatusCode(403);
            return $this->json(['error' => 'accessDenied']);
        } catch (CaseAlreadyDecided $exception) {
            $this->response->setStatusCode(409);
            return $this->json(['error' => 'alreadyDecided', 'message' => $exception->getMessage()]);
        } catch (InvalidModerationData $exception) {
            $this->response->setStatusCode(422);
            return $this->json(['error' => 'invalid', 'errors' => $exception->errors]);
        } catch (\InvalidArgumentException) {
            // a malformed case id, a too long reason
            $this->response->setStatusCode(400);
            return $this->json(['error' => 'badRequest']);
        }
    }

    /** @return array<string, mixed> */
    private function body(): array
    {
        $body = (string)$this->request->getHttpRequest()->getBody();
        $data = strlen($body) <= self::MAX_BODY_BYTES ? json_decode($body, true) : null;
        return is_array($data) ? $data : [];
    }

    private function reason(): ?string
    {
        $reason = $this->body()['reason'] ?? null;
        return is_string($reason) ? $reason : null;
    }

    /**
     * @param array<string, ModerationTypeHandlerInterface> $types
     * @return array<string, mixed>
     */
    private function summary(ModerationCase $case, array $types): array
    {
        $handler = $types[$case->getTypeKey()];
        $presentation = $handler->present($case);

        return [
            'id' => $case->getId()->value,
            'type' => $case->getTypeKey(),
            'typeLabel' => $handler->getLabel(),
            'title' => $presentation->title,
            'summary' => $presentation->summary,
            'status' => $case->getStatus()->value,
            'submittedAt' => $case->getSubmittedAt()->format(DATE_ATOM),
            'decidedAt' => $case->getDecidedAt()?->format(DATE_ATOM),
            'corrections' => count($case->getCorrections()),
            'searchText' => implode(' ', [
                $presentation->title,
                ...array_map(static fn($field): string => $field->value, $presentation->fields),
            ]),
        ];
    }

    /** @return array<string, mixed> */
    private function detail(ModerationCase $case): array
    {
        $types = $this->moderationCaseService->getAccessibleTypes();
        $handler = $types[$case->getTypeKey()];
        $summary = $this->summary($case, $types);
        unset($summary['searchText']);
        $presentation = $handler->present($case);
        $pending = $case->getStatus() === ModerationStatus::Pending;

        return [
            ...$summary,
            'locale' => $case->getLocale(),
            'payload' => $case->getPayload(),
            'correctedValues' => $case->getCorrections(),
            'effective' => $case->getEffectivePayload(),
            'fields' => array_map(
                static fn($field): array => ['label' => $field->label, 'value' => $field->value],
                $presentation->fields,
            ),
            'links' => $presentation->links,
            'decisionHints' => (object)$presentation->decisionHints,
            'privateMetadata' => $this->moderationCaseService->canViewPrivateMetadata($case)
                ? $case->getPrivateMetadata()
                : null,
            'correctable' => $pending && $handler instanceof CorrectableTypeHandlerInterface
                ? array_map(static fn($field): array => (array)$field, $handler->getCorrectableFields($case))
                : [],
            'preview' => $handler instanceof PreviewableTypeHandlerInterface
                ? [
                    'uri' => $handler->getPreviewUri(),
                    'data' => $handler->getPreviewData($case),
                    'submitted' => $handler->getPreviewData($case, true),
                ]
                : null,
            'decision' => [
                'moderator' => $case->getModeratorIdentifier(),
                'reason' => $case->getDecisionReason(),
                'externalReference' => $case->getExternalReference(),
            ],
            'audit' => array_map(static fn(ModerationAuditEntry $entry): array => [
                'event' => $entry->getEvent()->value,
                'actor' => $entry->getActorIdentifier(),
                'recordedAt' => $entry->getRecordedAt()->format(DATE_ATOM),
                'context' => $entry->getContext(),
            ], $case->getAuditEntries()),
        ];
    }

    /** @param array<string, mixed> $data */
    private function json(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
