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

    public function testDecisionActionsArePolicyProtectedAndFormsCarryCsrfTokens(): void
    {
        $policy = Yaml::parseFile($this->packagePath('Configuration/Policy.yaml'));
        $methodTargets = $policy['privilegeTargets'][
            'Neos\\Flow\\Security\\Authorization\\Privilege\\Method\\MethodPrivilege'
        ];
        $matcher = $methodTargets['Devclaim.NeosModeration:Backend.Actions']['matcher'];
        $detailFusion = file_get_contents($this->packagePath('Resources/Private/Fusion/Views/Show.fusion'));

        self::assertStringContainsString('approve|reject', $matcher);
        self::assertIsString($detailFusion);
        self::assertSame(2, substr_count($detailFusion, 'method="post"'));
        self::assertSame(2, substr_count($detailFusion, 'name="__csrfToken"'));
    }

    private function packagePath(string $relativePath): string
    {
        return dirname(__DIR__, 3) . '/' . $relativePath;
    }
}
