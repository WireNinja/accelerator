<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\PermissionServiceProvider;
use WireNinja\Accelerator\Console\Shield\SafeRegenerateCommand;

enum SafeRegenerateRole: string
{
    case SuperAdmin = 'super_admin';
    case AllPermissions = 'all_permissions';
    case ExplicitPermissions = 'explicit_permissions';

    /** @return list<string>|null */
    public function defaultPermissions(): ?array
    {
        return match ($this) {
            self::AllPermissions => null,
            self::SuperAdmin => [],
            self::ExplicitPermissions => ['ViewAny:User', 'Create:User'],
        };
    }
}

final class FakeShieldGenerateCommand extends Command
{
    protected $signature = 'shield:generate
        {--all}
        {--option=}
        {--ignore-existing-policies}
        {--panel=}';

    /** @var array<string, mixed> */
    public array $receivedOptions = [];

    public function __construct(private readonly int $exitCode = self::SUCCESS)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->receivedOptions = $this->options();

        return $this->exitCode;
    }
}

final class SafeRegenerateCommandTest extends TestCase
{
    private FakeShieldGenerateCommand $shieldGenerate;

    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [PermissionServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('accelerator.enums.role', SafeRegenerateRole::class);
        $app['config']->set('filament-shield.super_admin.name', SafeRegenerateRole::SuperAdmin->value);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('permissions', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('role_has_permissions', static function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::registerCommand(app(SafeRegenerateCommand::class));
        $this->replaceShieldGenerateCommand();
    }

    public function test_it_synchronizes_generated_permissions_to_role_defaults_by_permission_name(): void
    {
        $viewAny = Permission::findOrCreate('ViewAny:User');
        $create = Permission::findOrCreate('Create:User');
        $stale = Permission::findOrCreate('Delete:User');

        Role::findOrCreate(SafeRegenerateRole::SuperAdmin->value)->syncPermissions($stale);
        Role::findOrCreate(SafeRegenerateRole::ExplicitPermissions->value)->syncPermissions($stale);

        app(PermissionRegistrar::class)->getPermissions();

        $exitCode = Artisan::call('shield:safe-regenerate', [
            '--panel' => 'admin',
            '--json' => true,
            '--compact' => true,
        ]);

        /** @var array<string, mixed> $payload */
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame('OK', $payload['status']);
        $this->assertSame('admin', $payload['panel']);
        $this->assertSame(Command::SUCCESS, $payload['shield_exit_code']);
        $this->assertSame(3, $payload['roles_synchronized']);
        $this->assertEquals([
            'all' => true,
            'option' => 'policies_and_permissions',
            'ignore-existing-policies' => true,
            'panel' => 'admin',
        ], array_intersect_key($this->shieldGenerate->receivedOptions, array_flip([
            'all',
            'option',
            'ignore-existing-policies',
            'panel',
        ])));
        $this->assertCount(0, Role::findByName(SafeRegenerateRole::SuperAdmin->value)->permissions);
        $this->assertEqualsCanonicalizing(
            [$create->name, $stale->name, $viewAny->name],
            Role::findByName(SafeRegenerateRole::AllPermissions->value)->permissions->pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$create->name, $viewAny->name],
            Role::findByName(SafeRegenerateRole::ExplicitPermissions->value)->permissions->pluck('name')->all(),
        );
        $this->assertTrue(Role::findByName(SafeRegenerateRole::ExplicitPermissions->value)->hasPermissionTo($viewAny));
        $this->assertFalse(Role::findByName(SafeRegenerateRole::ExplicitPermissions->value)->hasPermissionTo($stale));
        $this->assertCount(3, app(PermissionRegistrar::class)->getPermissions());
    }

    #[DataProvider('jsonModes')]
    public function test_it_does_not_synchronize_roles_when_shield_generation_fails(bool $json): void
    {
        $permission = Permission::findOrCreate('ViewAny:User');
        $role = Role::findOrCreate(SafeRegenerateRole::ExplicitPermissions->value);
        $role->syncPermissions($permission);
        $this->replaceShieldGenerateCommand(7);

        $arguments = ['--panel' => 'admin'];

        if ($json) {
            $arguments['--json'] = true;
            $arguments['--compact'] = true;
        }

        $exitCode = Artisan::call('shield:safe-regenerate', $arguments);
        $output = Artisan::output();

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertSame([$permission->name], $role->fresh()->permissions->pluck('name')->all());

        if ($json) {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

            $this->assertSame('ERROR', $payload['status']);
            $this->assertSame('admin', $payload['panel']);
            $this->assertSame(7, $payload['shield_exit_code']);
            $this->assertSame(0, $payload['roles_synchronized']);
        } else {
            $this->assertStringContainsString('Shield regeneration failed for panel [admin] (exit 7).', $output);
        }
    }

    /** @return iterable<string, array{bool}> */
    public static function jsonModes(): iterable
    {
        yield 'standard output' => [false];
        yield 'JSON output' => [true];
    }

    private function replaceShieldGenerateCommand(int $exitCode = Command::SUCCESS): void
    {
        $this->shieldGenerate = new FakeShieldGenerateCommand($exitCode);
        Artisan::registerCommand($this->shieldGenerate);
    }
}
