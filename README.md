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
the app after logging in (`Neos.Neos.moduleConfiguration.preferredStartModules`); moderators who may open the content
module but not publish to live land there too (`ModeratorStartAspect`).

The app talks to a JSON API with the session and its CSRF token (`ModerationApiController`, `/neos/moderation/api/…`):
the cases with counts per status, a case, its corrections, approval and rejection. Pages of the same origin outside of
the app (e.g. a bar in the Neos UI next to the content a case created) get the token from `GET …/api/session`
(`{"csrfToken": …, "moderates": true|false}`) and use the same API.

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

A type whose cases are edited elsewhere (e.g. as content in Neos) needs neither: its presentation (`present()`) gives
the links there (`CasePresentation::$links`, shown above the details while the case is pending, and with the decision
afterwards) and its preview shows the current state. `CasePresentation::$decisionHints` (`approve`, `reject`) says in
the confirmation what a decision does; next to the preview the app lists the presented fields as "Eingesendet".
`CasePresentation::$editedAt` says when what the case created was last edited after the submission: the app then
switches between the submission (`getPreviewData($case, true)`) and the current state, shows "Bearbeitet" in the
history, and opens the submission first with `?view=submitted`.

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

## Screens

Other packages can add screens next to the inbox, e.g. a list of what approvals created. A screen is a setting and a
JSON API; the app shows it to users with its privilege (administrators: all):

```yaml
Devclaim:
    NeosModeration:
        screens:
            events:
                label: 'Veranstaltungen'
                uri: '/neos/moderation/api/events'
                privilegeTarget: 'NeosSvelte.Events:EventLibrary'
```

`GET uri` answers `{title, filters, columns, items}`. `filters`: `{name, label, kind, options, default?}` with kind
`chips`, `select` or `period`, `default` the value selected when the screen opens; an item matches an option when its `filterValues[name]` is the option's value or a list
containing it. `columns`: `{key, label}` shown from an item's `values`. An item: `{id, title, subtitle, color,
status: {value, label, tone: success|warning|neutral}, values, filterValues, date, searchText, preview: {uri, data} |
null, details: [{label, value}], links: [{label, uri}], caseId?}`; the preview uses the protocol of the case previews
(read only). An item with a `caseId` (it came from a moderation case) is shown as that case, as in the inbox, with
its `details` as a section. The API needs the backend session: add its controller to the request patterns of the provider
`Neos.Neos:Backend` (see `Configuration/Settings.yaml`). A session keeps the request patterns of its tokens, also
through logging out and in again in the same browser (Flow reuses the token objects): after deploying a new pattern,
destroy the sessions (`./flow session:destroyall`), else such users get "Ihre Anmeldung gilt hierfür nicht (mehr)".

## Transaction and idempotency contract

Decisions lock the case inside a Doctrine transaction. A recorded decision of the same kind returns the existing case
without running the handler again; the opposite decision fails. An approval or rejection checks the case (locked),
runs the handler outside of a transaction (`approve()`, or `reject()` of a `RejectionHandlerInterface`), then records
the decision (locked again): handlers may write to other stores, e.g. the event-sourced content repository, which
cannot commit inside a transaction of the same connection. Handlers must therefore be idempotent by the stable case
identifier: a repeated or concurrent decision runs them again, and they find what they created before (e.g. a node
whose id is derived from the case).

A type that prepares something as soon as a case arrives (e.g. the content moderators review and edit before
deciding) implements `SubmissionAwareTypeHandlerInterface`: `submitted()` runs after the case is stored, outside of
the transaction. A failure there does not undo the submission; it is logged (`Neos.Flow:SystemLogger`), and the
handler catches up when the case is decided.

Only trusted application code should call `SubmitModerationCase`. Public endpoints remain the responsibility of the
project and must authenticate the caller, rate-limit it, verify spam protection, and sanitize inputs before invoking
the service. The registered handler remains responsible for authoritative type-specific validation.
