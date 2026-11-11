<?php

namespace Tests\Feature\Architecture;

use Illuminate\Notifications\DatabaseNotification;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Guards the service layer: every model has exactly one service (a class
 * using ManagesModel), and no code outside that service touches the model's
 * data statically (Model::query(), ::create(), ::find(), ...).
 */
class ServiceLayerTest extends TestCase
{
    /** Third-party models the application reads or writes. */
    private const VENDOR_MODELS = [
        Activity::class,
        DatabaseNotification::class,
        Permission::class,
        Role::class,
    ];

    /** Static calls that reach the database. */
    private const DATA_METHODS = 'query|create|forceCreate|insert|insertOrIgnore|upsert|where\w*|find\w*|first\w*|updateOrCreate|withTrashed|onlyTrashed|withoutTrashed|count|exists|all|latest|oldest|orderBy\w*|pluck|destroy|truncate|role|permission|with|has|doesntHave';

    public function test_every_model_has_exactly_one_service(): void
    {
        $owners = $this->owners();

        foreach ($this->models() as $model) {
            $this->assertArrayHasKey($model, $owners, "{$model} has no service: add one that uses ManagesModel.");
        }

        foreach ($owners as $model => $services) {
            $this->assertCount(1, $services, "{$model} is owned by several services: ".implode(', ', $services));
        }
    }

    public function test_models_are_only_queried_through_their_service(): void
    {
        $owners = $this->owners();
        $violations = [];

        foreach ($this->sourceFiles() as $path) {
            $source = (string) file_get_contents($path);
            $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);

            foreach ($owners as $model => [$ownerPath]) {
                if ($relative === $ownerPath) {
                    continue;
                }

                foreach ($this->referencesTo($model, $source) as $reference) {
                    if (preg_match_all('/(?<![\w\\\\$>])'.preg_quote($reference, '/').'::('.self::DATA_METHODS.')\(/', $source, $matches)) {
                        foreach ($matches[0] as $call) {
                            $violations[] = "{$relative}: {$call} (use ".basename($ownerPath, '.php').')';
                        }
                    }
                }
            }
        }

        $this->assertSame([], $violations, "Models must be accessed through their service:\n".implode("\n", $violations));
    }

    /**
     * @return array<class-string, array<int, string>> model => owning service files
     */
    private function owners(): array
    {
        $owners = [];

        foreach ($this->sourceFiles(['app']) as $path) {
            $source = (string) file_get_contents($path);

            if (! preg_match('/^\s*use ManagesModel;/m', $source)
                || ! preg_match('/function model\(\): string\s*\{\s*return ([\w\\\\]+)::class;/', $source, $match)) {
                continue;
            }

            $owners[$this->resolve($match[1], $source)][] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
        }

        return $owners;
    }

    /**
     * @return array<int, class-string>
     */
    private function models(): array
    {
        $models = self::VENDOR_MODELS;
        $finder = Finder::create()->files()->name('*.php')->in(app_path())->path('/(^|\/)Models\//')->notPath('Concerns');

        foreach ($finder as $file) {
            $source = $file->getContents();

            if (preg_match('/^namespace ([\w\\\\]+);/m', $source, $namespace) && preg_match('/^(?:final |abstract )*class (\w+)/m', $source, $class)) {
                $models[] = $namespace[1].'\\'.$class[1];
            }
        }

        return $models;
    }

    /**
     * Application code, views and seeders. Model classes may use their own
     * static helpers, and factories build models by design.
     *
     * @param  array<int, string>  $roots
     * @return array<int, string>
     */
    private function sourceFiles(array $roots = ['app', 'resources/views', 'database/seeders']): array
    {
        $finder = Finder::create()->files()->name('*.php')->in(array_map(base_path(...), $roots))->notPath('/(^|\/)Models\//');

        return array_map(fn ($file) => $file->getRealPath(), iterator_to_array($finder, false));
    }

    /**
     * The spellings that name the model in this file: its fully qualified
     * name, and its short name or alias when imported.
     *
     * @return array<int, string>
     */
    private function referencesTo(string $model, string $source): array
    {
        $references = ['\\'.$model];

        if (preg_match('/^use '.preg_quote($model, '/').'(?: as (\w+))?;/m', $source, $match)) {
            $references[] = $match[1] ?? class_basename($model);
        } elseif (preg_match('/^namespace ([\w\\\\]+);/m', $source, $namespace) && $model === $namespace[1].'\\'.class_basename($model)) {
            $references[] = class_basename($model);
        }

        return $references;
    }

    private function resolve(string $name, string $source): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        if (preg_match('/^use ([\w\\\\]+\\\\'.preg_quote($name, '/').');/m', $source, $match)) {
            return $match[1];
        }

        preg_match('/^namespace ([\w\\\\]+);/m', $source, $namespace);

        return $namespace[1].'\\'.$name;
    }
}
