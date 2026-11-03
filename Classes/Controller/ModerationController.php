<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Controller;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Package\PackageManager;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\Security\Authorization\PrivilegeManagerInterface;
use Neos\Flow\Security\Context;
use Neos\Neos\Controller\Module\AbstractModuleController;

/**
 * The backend module "moderation" at /neos/moderation (the entry of the Neos menu and of preferredStartModules): the
 * moderation app in the frame of the backend, with its top bar and menu. The app (Resources/Private/App, built to
 * Resources/Public/App) renders into a shadow root of its own, so the styles of the backend and of the app stay apart,
 * and talks to ModerationApiController with the backend session. Made for phones as well (setting viewport of the
 * module, used by Devclaim.NeosModernBackend).
 */
#[Flow\Scope('singleton')]
final class ModerationController extends AbstractModuleController
{
    /** @var array<string, array{label?: string, uri?: string, privilegeTarget?: string}> */
    #[Flow\InjectConfiguration(path: 'screens', package: 'Devclaim.NeosModeration')]
    protected array $screens = [];

    #[Flow\Inject]
    protected PrivilegeManagerInterface $privilegeManager;

    #[Flow\Inject]
    protected ResourceManager $resourceManager;

    #[Flow\Inject]
    protected PackageManager $packageManager;

    #[Flow\Inject]
    protected Context $securityContext;

    public function indexAction(): string
    {
        $configuration = [
            'apiBase' => '/neos/moderation/api',
            'csrfToken' => $this->securityContext->getCsrfProtectionToken(),
            'screens' => $this->accessibleScreens(),
        ];
        $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_HTML5);
        $json = json_encode(
            $configuration,
            JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES,
        );

        // the styles are loaded into the shadow root by the app (main.tsx); preloaded here, so it renders sooner
        return '<div id="moderation-app"></div>
<noscript>Die Moderation braucht JavaScript.</noscript>
<script type="application/json" id="moderation-configuration">' . $json . '</script>
<link rel="preload" as="style" href="' . $escape($this->asset('app.css')) . '">
<script type="module" src="' . $escape($this->asset('app.js')) . '"></script>';
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
            $allowed = $privilegeTarget !== '' && $this->privilegeManager->isPrivilegeTargetGranted($privilegeTarget);
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
