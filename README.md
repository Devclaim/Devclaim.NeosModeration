# Devclaim.NeosModeration

`Devclaim.NeosModeration` provides frontend-independent moderation cases, an append-only audit history, atomic decision
services, per-type authorization, corrections by moderators, and a moderation app for desktop and phones. It deliberately
contains no public submission endpoint, spam protection, event model, or frontend dependency.

## The moderation app

`/neos/moderation` is a page of its own (`ModerationAppController`, a React app in `Resources/Private/App`, built to
`Resources/Public/App`), made for phones as well: the list of cases with filters, a case with its preview, its
correctable fields, the private contact data, the history and the decision. Links keep the state
(`/neos/moderation?case=…&status=…`). It uses the Neos backend session; without one, the Neos login brings the user
back. The backend module "moderation" has the same address, so users without the content module (moderators) land in
the app after logging in (`Neos.Neos.moduleConfiguration.preferredStartModules`).

The app talks to a JSON API with the session and its CSRF token (`ModerationApiController`, `/neos/moderation/api/…`):
the cases with counts per status, a case, its corrections, approval and rejection.

```bash
cd DistributionPackages/Devclaim.NeosModeration && yarn install && yarn build   # after a change of the app
```

## Corrections and preview

A handler that implements `CorrectableTypeHandlerInterface` names the payload fields moderators may correct before
deciding (`CorrectableField`: label, editor, group) and validates corrected values like submitted ones. Corrections are
stored next to the submission, which never changes; `ModerationCase::getEffectivePayload()` is the payload with them,
and the app switches between both. Every change of the corrections is in the history.

A handler that implements `PreviewableTypeHandlerInterface` names a page of the frontend that shows a case as the
website would (`getPreviewUri()`, on the same host). The app shows it in an iframe and sends the data by postMessage
(`{"type": "moderation:preview", key, data, editable}`); the page answers with its height and with corrections of
fields edited in place (`{"type": "moderation:correct", field, value}`).

## Registering a type

The following fictional example belongs in the consuming project package, not in this package's production
configuration:

```yaml
# Acme.Site/Configuration/Settings.yaml
Devclaim:
    NeosModeration:
        types:
            'acme.example':
                handler: 'Acme\Site\Moderation\ExampleTypeHandler'
```

The handler implements `ModerationTypeHandlerInterface`. It validates all submission values and limits before it
returns a `ValidatedSubmission`, presents the immutable case as escaped label/value fields, and performs approval.
If rejection needs type-specific work, it additionally implements `RejectionHandlerInterface`.

```php
<?php

declare(strict_types=1);

namespace Acme\Site\Moderation;

use Devclaim\NeosModeration\Domain\Model\ModerationCase;
use Devclaim\NeosModeration\Domain\Service\ModerationTypeHandlerInterface;
use Devclaim\NeosModeration\Domain\ValueObject\ApprovalResult;
use Devclaim\NeosModeration\Domain\ValueObject\CasePresentation;
use Devclaim\NeosModeration\Domain\ValueObject\PresentationField;
use Devclaim\NeosModeration\Domain\ValueObject\ValidatedSubmission;

final class ExampleTypeHandler implements ModerationTypeHandlerInterface
{
    public function getTypeKey(): string { return 'acme.example'; }
    public function getLabel(): string { return 'Example contribution'; }
    public function getIcon(): string { return 'fas fa-inbox'; }
    public function getPermissionIdentifier(): string { return 'Acme.Site:Moderation.Example'; }
    public function getPrivateMetadataPermissionIdentifier(): ?string
    {
        return 'Acme.Site:Moderation.Example.PrivateMetadata';
    }

    public function validateSubmission(string $locale, array $payload, ?array $privateMetadata): ValidatedSubmission
    {
        // Reject unknown fields and enforce type-specific lengths/counts here.
        return new ValidatedSubmission(['title' => (string)$payload['title']], $privateMetadata);
    }

    public function present(ModerationCase $case): CasePresentation
    {
        return new CasePresentation('Example contribution', [
            new PresentationField('Title', (string)$case->getPayload()['title']),
        ]);
    }

    public function approve(ModerationCase $case): ApprovalResult
    {
        // Use $case->getId() as the idempotency key for any external side effect.
        return new ApprovalResult('example:' . $case->getId()->value);
    }
}
```

Define the handler's method privilege targets and a project-specific child role:

```yaml
# Acme.Site/Configuration/Policy.yaml
privilegeTargets:
    'Neos\Flow\Security\Authorization\Privilege\Method\MethodPrivilege':
        'Acme.Site:Moderation.Example':
            matcher: 'method(Acme\Site\Moderation\ExamplePermissionSubject->access())'
        'Acme.Site:Moderation.Example.PrivateMetadata':
            matcher: 'method(Acme\Site\Moderation\ExamplePrivatePermissionSubject->access())'

roles:
    'Acme.Site:ExampleModerator':
        parentRoles: ['Devclaim.NeosModeration:Moderator']
        privileges:
            - privilegeTarget: 'Acme.Site:Moderation.Example'
                permission: GRANT
            - privilegeTarget: 'Acme.Site:Moderation.Example.PrivateMetadata'
                permission: GRANT
```

Privilege targets are identifiers evaluated explicitly by the application service; their matcher is never used to
authorize a controller. A dedicated no-op permission-subject method is a convenient Flow policy declaration.
Omit the private-metadata target and return `null` when only administrators may see contact data.

The base `Devclaim.NeosModeration:Moderator` role does **not** inherit `Neos.Neos:AbstractEditor`. It grants only backend
shell access plus the moderation app and its API, and no type privilege. Consequently it cannot access content, media,
workspaces, publishing, redirects, users, packages, sites, or configuration. Neos administrators can access all
registered moderation types; every other account must be granted the handler's exact type privilege.

## Transaction and idempotency contract

Approval and rejection acquire a pessimistic lock on the case inside a Doctrine transaction. A repeated decision of
the same kind returns the existing case without rerunning the handler; the opposite decision fails. Approval handlers
that write through the same Doctrine connection participate in that transaction. Handlers that call an external or
event-sourced system must additionally use the stable case identifier as an idempotency key, because no SQL
transaction can atomically cover an unrelated store.

Only trusted application code should call `SubmitModerationCase`. Public endpoints remain the responsibility of the
project and must authenticate the caller, rate-limit it, verify spam protection, and sanitize inputs before invoking
the service. The registered handler remains responsible for authoritative type-specific validation.
