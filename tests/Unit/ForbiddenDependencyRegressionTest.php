<?php

namespace Kaching\Intelligence\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ForbiddenDependencyRegressionTest extends TestCase
{
    public function test_no_classes_in_src_import_forbidden_core_or_framework_classes(): void
    {
        $srcDir = realpath(__DIR__ . '/../../src');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($srcDir));

        $forbiddenPatterns = [
            'App\Models\User',
            'App\Models',
            'Illuminate\Http\Request',
            'Illuminate\Database\Eloquent',
            'Illuminate\Support\Facades\DB',
            'Illuminate\Support\Facades\Auth',
        ];

        $checkedFiles = 0;
        foreach ($files as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $checkedFiles++;
            $content = file_get_contents($file->getPathname());

            foreach ($forbiddenPatterns as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $content,
                    "Forbidden dependency [{$forbidden}] found in [{$file->getPathname()}]."
                );
            }
        }

        $this->assertGreaterThan(10, $checkedFiles, "Expected to check more than 10 PHP files in src/");
    }
}
