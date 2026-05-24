<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * ロール別配置の禁止規約をコードで強制する Architecture テスト。
 *
 * Controller / UseCase / Test 配置で `Admin/` `Coach/` `Student/` のロール別サブディレクトリを切ると、
 * 後から他ロールが同操作を持った時に大規模リネームが必要になる (.claude/rules/backend-http.md / backend-usecases.md 参照)。
 * Pro 生として「リソース固有認可は Policy で表現し、ファイルパスでは表現しない」を学ぶための検出ライン。
 */
class RoleSeparationArchitectureTest extends TestCase
{
    public function test_no_role_specific_controller_namespace_exists(): void
    {
        // Arrange
        $forbiddenRoles = ['Admin', 'Coach', 'Student'];
        $violations = [];

        // Act
        foreach ($forbiddenRoles as $role) {
            $path = app_path("Http/Controllers/{$role}");
            if (is_dir($path)) {
                $violations[] = "app/Http/Controllers/{$role}";
            }
        }

        // Assert
        $this->assertEmpty(
            $violations,
            "Controller のロール別 namespace は禁止です。Policy で認可分岐し、ファイル名は Feature ベースで命名してください: \n".implode("\n", $violations),
        );
    }

    public function test_no_role_specific_use_case_subdirectory_exists(): void
    {
        // Arrange
        $forbiddenRoles = ['Admin', 'Coach', 'Student'];
        $violations = [];

        // Act
        $entityDirs = glob(app_path('UseCases/*'), GLOB_ONLYDIR) ?: [];
        foreach ($entityDirs as $entityDir) {
            foreach ($forbiddenRoles as $role) {
                $candidate = $entityDir.'/'.$role;
                if (is_dir($candidate)) {
                    $violations[] = str_replace(base_path().'/', '', $candidate);
                }
            }
        }

        // Assert
        $this->assertEmpty(
            $violations,
            "UseCase のロール別サブディレクトリは禁止です。Action 名で意味を区別してください (StoreAction / AssignAction 等): \n".implode("\n", $violations),
        );
    }

    public function test_no_role_specific_test_subdirectory_in_feature_http(): void
    {
        // Arrange
        $forbiddenRoles = ['Admin', 'Coach', 'Student'];
        $violations = [];

        // Act
        $testHttpDir = base_path('tests/Feature/Http');
        if (is_dir($testHttpDir)) {
            $entityDirs = glob($testHttpDir.'/*', GLOB_ONLYDIR) ?: [];
            $directRoleDirs = array_filter(
                $entityDirs,
                fn (string $dir) => in_array(basename($dir), $forbiddenRoles, true),
            );
            foreach ($directRoleDirs as $dir) {
                $violations[] = str_replace(base_path().'/', '', $dir);
            }
            foreach ($entityDirs as $entityDir) {
                foreach ($forbiddenRoles as $role) {
                    $candidate = $entityDir.'/'.$role;
                    if (is_dir($candidate)) {
                        $violations[] = str_replace(base_path().'/', '', $candidate);
                    }
                }
            }
        }

        // Assert
        $this->assertEmpty(
            $violations,
            "tests/Feature/Http 配下のロール別ディレクトリは禁止です。Entity ベースのフラット構造を維持してください: \n".implode("\n", $violations),
        );
    }

    public function test_use_case_action_classes_end_with_action_suffix(): void
    {
        // Arrange: ViewModels / Traits / DTOs ディレクトリと、Result / ViewModel / Helper 接尾辞の DTO は除外
        $ignoredSubdirs = ['ViewModels', 'Traits', 'DTOs'];
        $ignoredSuffixes = ['Result', 'ViewModel', 'Helper'];
        $violations = [];

        // Act
        $files = $this->phpFilesUnder(app_path('UseCases'));
        foreach ($files as $file) {
            $relative = str_replace(app_path('UseCases').'/', '', $file);
            $segments = explode('/', $relative);
            if (count($segments) >= 2 && in_array($segments[1] ?? '', $ignoredSubdirs, true)) {
                continue;
            }
            // trait / abstract / interface は Action 接尾辞対象外 (mixin / 共有ロジック)
            $src = file_get_contents($file);
            if (preg_match('/^\s*(?:final\s+)?(?:abstract\s+)?(?:trait|interface)\s+\w+/m', $src)) {
                continue;
            }
            $basename = basename($file, '.php');
            foreach ($ignoredSuffixes as $suffix) {
                if (str_ends_with($basename, $suffix)) {
                    continue 2;
                }
            }
            if (! str_ends_with($basename, 'Action')) {
                $violations[] = 'app/UseCases/'.$relative;
            }
        }

        // Assert
        $this->assertEmpty(
            $violations,
            "app/UseCases 配下のクラスは Action 接尾辞必須です (ViewModels / Traits / DTOs サブディレクトリ + Result / ViewModel / Helper 接尾辞 は除く): \n".implode("\n", $violations),
        );
    }

    /**
     * 指定ディレクトリ配下の .php ファイル一覧を再帰的に取得。
     *
     * @return array<int, string>
     */
    private function phpFilesUnder(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
