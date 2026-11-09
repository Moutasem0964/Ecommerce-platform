<?php

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ModuleBoundariesTest extends TestCase
{
    private const ALLOWED = [
        'Shared' => [],
        'Identity' => ['Shared'],
        'Inventory' => ['Shared'],
        'Catalog' => ['Shared', 'Inventory'],
        'Ordering' => ['Shared', 'Identity', 'Inventory', 'Catalog\\Contracts'],
        'Notifications' => ['Shared', 'Identity', 'Ordering\\Events'],
    ];

    public static function modules(): array
    {
        $modules = [];
        foreach (array_keys(self::ALLOWED) as $module) {
            $modules[$module] = [$module];
        }

        return $modules;
    }

    #[DataProvider('modules')]
    public function test_module_depends_only_on_allowed_modules(string $module): void
    {
        $violations = [];

        foreach ($this->phpFiles($this->modulePath($module)) as $file) {
            preg_match_all('/App\\\\Modules\\\\(\w+(?:\\\\\w+)*)/', file_get_contents($file), $matches);

            foreach (array_unique($matches[1]) as $reference) {
                if (! $this->isAllowed($module, $reference)) {
                    $violations[] = $this->relative($file)." -> App\\Modules\\{$reference}";
                }
            }
        }

        $this->assertSame([], $violations);
    }

    #[DataProvider('modules')]
    public function test_only_the_inventory_module_talks_grpc(string $module): void
    {
        $violations = [];

        if ($module !== 'Inventory') {
            foreach ($this->phpFiles($this->modulePath($module)) as $file) {
                if (preg_match('/\b(Proto|Grpc)\\\\/', file_get_contents($file))) {
                    $violations[] = $this->relative($file);
                }
            }
        }

        $this->assertSame([], $violations);
    }

    #[DataProvider('modules')]
    public function test_foreign_keys_never_cross_module_boundaries(string $module): void
    {
        $migrations = $this->phpFiles($this->modulePath($module).'/Database/Migrations');
        $sources = array_map('file_get_contents', $migrations);

        $ownTables = [];
        foreach ($sources as $source) {
            preg_match_all("/Schema::create\\('(\\w+)'/", $source, $created);
            $ownTables = [...$ownTables, ...$created[1]];
        }

        $violations = [];
        foreach ($sources as $i => $source) {
            $up = strstr($source, 'public function down()', true) ?: $source;

            preg_match_all("/->constrained\\('(\\w+)'/", $up, $explicit);
            preg_match_all("/foreignId\\('(\\w+)_id'\\)(?:->\\w+\\([^)]*\\))*->constrained\\(\\)/", $up, $implicit);
            preg_match_all("/->on\\('(\\w+)'\\)/", $up, $on);

            $referenced = [...$explicit[1], ...array_map(fn ($name) => $name.'s', $implicit[1]), ...$on[1]];

            foreach (array_unique($referenced) as $table) {
                if (! in_array($table, $ownTables, true)) {
                    $violations[] = $this->relative($migrations[$i])." -> {$table}";
                }
            }
        }

        $this->assertSame([], $violations);
    }

    public function test_all_application_code_lives_in_modules(): void
    {
        $outside = [];

        foreach ($this->phpFiles($this->root().'/app') as $file) {
            $path = $this->relative($file);
            if (! str_starts_with($path, 'app/Modules/') && ! str_starts_with($path, 'app/Providers/')) {
                $outside[] = $path;
            }
        }

        $this->assertSame([], $outside);
    }

    private function isAllowed(string $module, string $reference): bool
    {
        foreach ([$module, ...self::ALLOWED[$module]] as $allowed) {
            if ($reference === $allowed || str_starts_with($reference, $allowed.'\\')) {
                return true;
            }
        }

        return false;
    }

    private function phpFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function modulePath(string $module): string
    {
        return $this->root().'/app/Modules/'.$module;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function relative(string $file): string
    {
        return ltrim(str_replace('\\', '/', substr($file, strlen($this->root()))), '/');
    }
}
