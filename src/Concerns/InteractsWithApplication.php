<?php

namespace WireNinja\Accelerator\Concerns;

use BezhanSalleh\FilamentShield\Commands\InstallCommand;
use BezhanSalleh\FilamentShield\Commands\PublishCommand;
use BezhanSalleh\FilamentShield\Commands\SeederCommand;
use BezhanSalleh\FilamentShield\Commands\SetupCommand;
use BezhanSalleh\FilamentShield\Commands\SuperAdminCommand;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;
use SessionHandlerInterface;
use WireNinja\Accelerator\Support\OctaneTableSessionHandler;
use WireNinja\Accelerator\Support\Telegram\TelegramBotConfigurator;

/**
 * @property-read Application $app
 */
trait InteractsWithApplication
{
    /**
     * Bootstrap Eloquent best practices.
     *
     * @DONOT-REMOVE Model::unguard()
     * Filament v5 nested forms (Repeater::relationship, HasMany sync via Schema) pass
     * payloads without fillable checks in some paths. Disturbing global unguard will
     * cause SilentlyDiscardedAttributesException on many resources that intentionally
     * accept nested attributes. Validation is still performed via Filament schema,
     * FormRequest, or policy. Do not change this to opt-in without a comprehensive
     * migration to explicit fillable on all userland models.
     */
    protected function bootEloquentBestPractices(): void
    {
        Model::shouldBeStrict(! app()->isProduction());
        Model::unguard();
    }

    /**
     * Bootstrap application defaults for production-ready applications.
     */
    protected function bootApplicationDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn(): ?Password => app()->isProduction()
                ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
                : null,
        );
    }

    protected function bootTelegramConfiguration(): void
    {
        rescue(fn(): bool => resolve(TelegramBotConfigurator::class)->syncConfig());
    }

    /**
     * Register the custom `octane-table` driver with Laravel's session manager.
     *
     * This registration only introduces the driver name. The driver is actually used
     * later by the framework `StartSession` middleware when `SESSION_DRIVER=octane-table`
     * is active on HTTP requests.
     */
    protected function bootCustomSessionDrivers(): void
    {
        if (config('session.driver') !== 'octane-table') {
            return;
        }

        Session::extend('octane-table', fn(Application $application): SessionHandlerInterface => new OctaneTableSessionHandler(
            minutes: (int) $application['config']->get('session.lifetime'),
            tableName: (string) $application['config']->get('session.octane_table', 'sessions'),
        ));
    }

    /**
     * Bootstrap Filament Shield destructive commands protection.
     */
    protected function bootShieldDestructiveCommands(): void
    {
        InstallCommand::prohibit(app()->isProduction());
        PublishCommand::prohibit(app()->isProduction());
        SetupCommand::prohibit(app()->isProduction());
        SeederCommand::prohibit(app()->isProduction());
        SuperAdminCommand::prohibit(app()->isProduction());
    }

    protected function bootFilamentConfiguration(): void
    {
        FilamentTimezone::set(config('app.timezone'));

        Table::configureUsing(static function (Table $table): void {
            $table
                ->defaultSort('id', 'desc')
                ->deferLoading()
                ->deferFilters()
                ->deferColumnManager()
                ->defaultCurrency('IDR')
                ->defaultDateDisplayFormat('j F Y')
                ->defaultTimeDisplayFormat('H:i:s')
                ->paginationMode(PaginationMode::Cursor)
                ->defaultNumberLocale('id-ID')
                ->striped()
                ->filtersLayout(FiltersLayout::AfterContent)
                ->emptyStateIcon('lucide-database')
                ->emptyStateHeading('Belum ada data')
                ->emptyStateDescription('Anda bisa menambahkan data baru dengan mengklik tombol "Tambah" di pojok kanan atas')
                ->persistColumnSearchesInSession(false)
                ->persistColumnsInSession(false)
                ->persistFiltersInSession(false)
                ->persistSearchInSession(false)
                ->persistSortInSession(false)
                ->filtersApplyAction(static function (Action $action) {
                    $action
                        ->label('Terapkan')
                        ->icon('lucide-database-search');
                });
        });

        FileUpload::configureUsing(static function (FileUpload $fileUpload): void {
            $fileUpload
                ->imageEditor()
                ->maxParallelUploads(5)
                ->maxSize(1024 * 1024); // 1 GB (Filament maxSize unit is KB)
        });

        Select::configureUsing(static function (Select $select): void {
            $select
                ->searchable()
                ->preload()
                ->native(false);
        });

        DateTimePicker::configureUsing(static function (DateTimePicker $dateTimePicker): void {
            $dateTimePicker
                ->native(false)
                ->displayFormat('j F Y H:i');
        });

        TimePicker::configureUsing(static function (TimePicker $timePicker): void {
            $timePicker
                ->native(false)
                ->displayFormat('H:i');
        });

        Step::configureUsing(static function (Step $step) {
            $step->completedIcon('lucide-thumbs-up');
        });

        CreateAction::configureUsing(static function (CreateAction $action) {
            $action->icon('lucide-circle-fading-plus');
        });

        EditAction::configureUsing(static function (EditAction $action) {
            $action->icon('lucide-notebook-pen');
        });

        DeleteAction::configureUsing(static function (DeleteAction $action) {
            $action->icon('lucide-shredder');
        });

        //  BetterActionGroup::configureUsing(static function (BetterActionGroup $actionGroup) {
        //     $actionGroup->icon('lucide-dots-vertical');
        //  });
    }
}
