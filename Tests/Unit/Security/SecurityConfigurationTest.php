<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class SecurityConfigurationTest extends TestCase
{
    public function testModeratorRoleHasNoEditorInheritanceOrUnrelatedModulePrivilege(): void
    {
        $policy = Yaml::parseFile($this->packagePath('Configuration/Policy.yaml'));
        $role = $policy['roles']['Devclaim.NeosModeration:Moderator'];

        self::assertArrayNotHasKey('parentRoles', $role);
        self::assertSame([
            'Neos.Neos:Backend.GeneralAccess',
            'Devclaim.NeosModeration:Backend.Module',
            'Devclaim.NeosModeration:Backend.Actions',
        ], array_column($role['privileges'], 'privilegeTarget'));
        self::assertFalse(in_array('Neos.Neos:AbstractEditor', $role, true));
        self::assertStringNotContainsString('Content', serialize($role));
        self::assertStringNotContainsString('Media', serialize($role));
        self::assertStringNotContainsString('Workspace', serialize($role));
        self::assertStringNotContainsString('Publishing', serialize($role));
    }

    public function testDecisionActionsArePolicyProtectedPostOnlyAndBehindTheBackendLogin(): void
    {
        $policy = Yaml::parseFile($this->packagePath('Configuration/Policy.yaml'));
        $methodTargets = $policy['privilegeTargets'][
            'Neos\\Flow\\Security\\Authorization\\Privilege\\Method\\MethodPrivilege'
        ];
        $matcher = $methodTargets['Devclaim.NeosModeration:Backend.Actions']['matcher'];
        self::assertStringContainsString('(session|cases|case|correct|approve|reject|delete)Action()', $matcher);

        // writing actions only answer POST, which Flow protects with the CSRF token of the session
        $routes = Yaml::parseFile($this->packagePath('Configuration/Routes.yaml'));
        foreach ($routes as $route) {
            if (in_array($route['defaults']['@action'], ['correct', 'approve', 'reject', 'delete'], true)) {
                self::assertSame(['POST'], $route['httpMethods'], $route['name']);
            }
        }

        $settings = Yaml::parseFile($this->packagePath('Configuration/Settings.yaml'));
        $pattern = $settings['Neos']['Flow']['security']['authentication']['providers']['Neos.Neos:Backend']
            ['requestPatterns']['Devclaim.NeosModeration:Controllers'];
        self::assertSame(
            'Devclaim\\NeosModeration\\Controller\\.*',
            $pattern['patternOptions']['controllerObjectNamePattern'],
        );
    }

    private function packagePath(string $relativePath): string
    {
        return dirname(__DIR__, 3) . '/' . $relativePath;
    }
}
