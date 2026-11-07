<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Scaffolds the back-end pieces of a new admin resource: model, migration,
 * factory, service, policy and a service test. Existing files are never
 * overwritten. Admin screens, API routes and permissions are left to the
 * developer, with the next steps printed at the end.
 */
class MakeCrudModuleCommand extends Command
{
    protected $signature = 'starter:make-crud {name : Singular class name, e.g. Article}';

    protected $description = 'Scaffold model, migration, factory, service, policy and test for a new CRUD resource';

    public function handle(): int
    {
        $name = (string) $this->argument('name');

        if (! preg_match('/^[A-Z][A-Za-z0-9]*$/', $name)) {
            $this->error('The name must be a singular StudlyCase class name, e.g. Article.');

            return self::FAILURE;
        }

        $plural = Str::plural($name);
        $table = Str::snake($plural);
        $permission = 'manage '.Str::snake($plural, ' ');

        $files = [
            app_path("Models/{$name}.php") => $this->render('model'),
            database_path('migrations/'.now()->format('Y_m_d_His')."_create_{$table}_table.php") => $this->render('migration'),
            database_path("factories/{$name}Factory.php") => $this->render('factory'),
            app_path("Services/{$name}Service.php") => $this->render('service'),
            app_path("Policies/{$name}Policy.php") => $this->render('policy'),
            base_path("tests/Feature/{$name}ModuleTest.php") => $this->render('test'),
        ];

        $replacements = [
            '{{ name }}' => $name,
            '{{ plural }}' => $plural,
            '{{ table }}' => $table,
            '{{ permission }}' => $permission,
            '{{ variable }}' => Str::camel($name),
        ];

        foreach (array_keys($files) as $path) {
            if (File::exists($path) || (str_contains($path, 'migrations/') && File::glob(database_path("migrations/*_create_{$table}_table.php")))) {
                $this->error("Refusing to overwrite existing file: {$path}");

                return self::FAILURE;
            }
        }

        foreach ($files as $path => $stub) {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, strtr($stub, $replacements));
            $this->line('Created <info>'.str_replace(base_path().DIRECTORY_SEPARATOR, '', $path).'</info>');
        }

        $this->newLine();
        $this->info("Scaffolded {$name}. Next steps:");
        $this->line('  1. Review the migration columns and add any fields the model needs.');
        $this->line("  2. Add '{$permission}' to database/seeders/RolesAndPermissionsSeeder.php and grant it to the admin role.");
        $this->line("  3. Register the policy's abilities in routes and screens with the '{$permission}' permission.");
        $this->line('  4. Run php artisan migrate and php artisan test.');

        return self::SUCCESS;
    }

    private function render(string $stub): string
    {
        return match ($stub) {
            'model' => <<<'PHP'
<?php

namespace App\Models;

use App\Models\Concerns\TracksUserActions;
use Database\Factories\{{ name }}Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class {{ name }} extends Model
{
    /** @use HasFactory<{{ name }}Factory> */
    use HasFactory, LogsActivity, SoftDeletes, TracksUserActions;

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'description'])
            ->logOnlyDirty()
            ->useLogName('{{ table }}');
    }
}

PHP,
            'migration' => <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{{ table }}', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{{ table }}');
    }
};

PHP,
            'factory' => <<<'PHP'
<?php

namespace Database\Factories;

use App\Models\{{ name }};
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<{{ name }}>
 */
class {{ name }}Factory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 100000),
            'description' => fake()->sentence(),
        ];
    }
}

PHP,
            'service' => <<<'PHP'
<?php

namespace App\Services;

use App\Models\{{ name }};
use App\Services\Concerns\ManagesModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Single entry point for reading and writing {{ name }} records.
 */
class {{ name }}Service
{
    /** @use ManagesModel<{{ name }}> */
    use ManagesModel;

    public function create(array $data): {{ name }}
    {
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? null, $data['name'] ?? null);

        return $this->query()->create($data);
    }

    public function update({{ name }} ${{ variable }}, array $data): {{ name }}
    {
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? null, $data['name'] ?? null, ${{ variable }});

        ${{ variable }}->fill($data);
        ${{ variable }}->save();

        return ${{ variable }};
    }

    /**
     * Soft delete: the record can be restored from the trash.
     */
    public function delete({{ name }} ${{ variable }}): void
    {
        ${{ variable }}->delete();
    }

    public function restore({{ name }} ${{ variable }}): {{ name }}
    {
        ${{ variable }}->restore();

        return ${{ variable }};
    }

    public function forceDelete({{ name }} ${{ variable }}): void
    {
        ${{ variable }}->forceDelete();
    }

    public function searchPaginated(?string $search, int $perPage = 10): LengthAwarePaginator
    {
        return $this->query()
            ->when($search, fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")
            ))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function stats(): array
    {
        return [
            'total' => $this->query()->count(),
            'trashed' => $this->onlyTrashed()->count(),
        ];
    }

    /**
     * Trashed records keep their slug, so restoring one can never collide.
     */
    protected function uniqueSlug(?string $slug, ?string $name, ?{{ name }} $ignore = null): string
    {
        $base = Str::slug($slug ?: (string) $name) ?: '{{ table }}';
        $candidate = $base;
        $suffix = 2;

        while ($this->withTrashed()
            ->where('slug', $candidate)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists()
        ) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    protected function model(): string
    {
        return {{ name }}::class;
    }
}

PHP,
            'policy' => <<<'PHP'
<?php

namespace App\Policies;

use App\Models\{{ name }};
use App\Models\User;

/**
 * Every ability is gated by the '{{ permission }}' permission.
 */
class {{ name }}Policy
{
    public function viewAny(User $user): bool
    {
        return $user->can('{{ permission }}');
    }

    public function view(User $user, {{ name }} ${{ variable }}): bool
    {
        return $user->can('{{ permission }}');
    }

    public function create(User $user): bool
    {
        return $user->can('{{ permission }}');
    }

    public function update(User $user, {{ name }} ${{ variable }}): bool
    {
        return $user->can('{{ permission }}');
    }

    public function delete(User $user, {{ name }} ${{ variable }}): bool
    {
        return $user->can('{{ permission }}');
    }

    public function restore(User $user, {{ name }} ${{ variable }}): bool
    {
        return $user->can('{{ permission }}');
    }

    public function forceDelete(User $user, {{ name }} ${{ variable }}): bool
    {
        return $user->can('{{ permission }}');
    }
}

PHP,
            'test' => <<<'PHP'
<?php

namespace Tests\Feature;

use App\Models\{{ name }};
use App\Services\{{ name }}Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class {{ name }}ModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_generates_a_unique_slug(): void
    {
        $service = app({{ name }}Service::class);

        $first = $service->create(['name' => 'Sample Record']);
        $second = $service->create(['name' => 'Sample Record']);

        $this->assertSame('sample-record', $first->slug);
        $this->assertSame('sample-record-2', $second->slug);
    }

    public function test_delete_is_soft_and_restore_brings_the_record_back(): void
    {
        $service = app({{ name }}Service::class);
        ${{ variable }} = {{ name }}::factory()->create();

        $service->delete(${{ variable }});
        $this->assertSoftDeleted(${{ variable }});

        $service->restore($service->withTrashed()->findOrFail(${{ variable }}->id));
        $this->assertNull(${{ variable }}->fresh()->deleted_at);
    }
}

PHP,
            default => throw new \InvalidArgumentException("Unknown stub [{$stub}]."),
        };
    }
}
