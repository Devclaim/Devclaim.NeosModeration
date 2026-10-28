<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Controller;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Package\PackageManager;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\Security\Authorization\PrivilegeManagerInterface;
use Neos\Flow\Security\Context;
use Neos\Neos\Domain\Service\UserService;

/**
 * The moderation app at /neos/moderation: a page of its own, made for phones as well, instead of a module in the
 * frame of the Neos backend. Moderators without access to the content module land here after logging in (setting
 * Neos.Neos.moduleConfiguration.preferredStartModules: the module "moderation" has the same address). The app
 * (Resources/Private/App, built to Resources/Public/App) talks to ModerationApiController with the backend session.
 */
#[Flow\Scope('singleton')]
final class ModerationAppController extends ActionController
{
    /** @var array<string, array{label?: string, uri?: string, privilegeTarget?: string}> */
    #[Flow\InjectConfiguration(path: 'screens', package: 'Devclaim.NeosModeration')]
    protected array $screens = [];

    public function __construct(
        private readonly PrivilegeManagerInterface $privilegeManager,
        private readonly ResourceManager $resourceManager,
        private readonly PackageManager $packageManager,
        private readonly Context $securityContext,
        private readonly UserService $userService,
    ) {
    }

    public function indexAction(): string
    {
        $this->response->setContentType('text/html');
        $this->response->setHttpHeader('Cache-Control', 'no-store');
        $this->response->setHttpHeader('X-Frame-Options', 'DENY');
        $this->response->setHttpHeader('Referrer-Policy', 'same-origin');

        $user = $this->userService->getCurrentUser();
        $configuration = [
            'apiBase' => '/neos/moderation/api',
            'csrfToken' => $this->securityContext->getCsrfProtectionToken(),
            'user' => $user?->getLabel() ?? '',
            'backendUri' => '/neos',
            'logoutUri' => '/neos/logout',
            'screens' => $this->accessibleScreens(),
        ];
        $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_HTML5);
        $json = json_encode(
            $configuration,
            JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES,
        );

        return '<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#161616">
<link rel="icon" href="data:,">
<title>Moderation</title>
<link rel="stylesheet" href="' . $escape($this->asset('app.css')) . '">
</head>
<body>
<div id="moderation-app"></div>
<noscript>Die Moderation braucht JavaScript.</noscript>
<script type="application/json" id="moderation-configuration">' . $json . '</script>
<script type="module" src="' . $escape($this->asset('app.js')) . '"></script>
</body>
</html>';
    }

    /**
     * The screens of other packages the user may open (setting Devclaim.NeosModeration.screens)
     *
     * @return list<array{key: string, label: string, uri: string}>
     */
    private function accessibleScreens(): array
    {
        $screens = [];
        foreach ($this->screens as $key => $screen) {
            $privilegeTarget = $screen['privilegeTarget'] ?? '';
            $allowed = $this->securityContext->hasRole('Neos.Neos:Administrator')
                || ($privilegeTarget !== '' && $this->privilegeManager->isPrivilegeTargetGranted($privilegeTarget));
            if ($allowed && isset($screen['label'], $screen['uri'])) {
                $screens[] = ['key' => (string)$key, 'label' => $screen['label'], 'uri' => $screen['uri']];
            }
        }
        return $screens;
    }

    /** the public URI of a file of the built app, with its modification time against stale browser caches */
    private function asset(string $file): string
    {
        $path = $this->packageManager->getPackage('Devclaim.NeosModeration')->getPackagePath()
            . 'Resources/Public/App/' . $file;
        $uri = $this->resourceManager->getPublicPackageResourceUriByPath(
            'resource://Devclaim.NeosModeration/Public/App/' . $file,
        );

        return $uri . '?v=' . (is_file($path) ? (string)filemtime($path) : '0');
    }
}
