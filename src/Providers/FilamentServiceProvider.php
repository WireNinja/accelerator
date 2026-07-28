<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Providers;

use BezhanSalleh\FilamentShield\Commands\InstallCommand;
use BezhanSalleh\FilamentShield\Commands\PublishCommand;
use BezhanSalleh\FilamentShield\Commands\SeederCommand;
use BezhanSalleh\FilamentShield\Commands\SetupCommand;
use BezhanSalleh\FilamentShield\Commands\SuperAdminCommand;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\LivewireManager;
use Spatie\Activitylog\Models\Activity;
use WireNinja\Accelerator\Console\Filament\VerifyResourceCommand;
use WireNinja\Accelerator\Console\Shield\SafeRegenerateCommand;
use WireNinja\Accelerator\Policies\ActivityPolicy;
use WireNinja\Accelerator\Support\BuiltinExceptions;

final class FilamentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerActivityPolicy();
        $this->registerAssets();
        $this->registerRenderHooks();
        $this->registerLivewireNamespace();
        $this->protectShieldCommands();
        $this->configureFilament();

        if ($this->app->runningInConsole()) {
            $this->commands([
                SafeRegenerateCommand::class,
                VerifyResourceCommand::class,
            ]);
        }
    }

    private function registerActivityPolicy(): void
    {
        if (Gate::getPolicyFor(Activity::class) === null) {
            Gate::policy(Activity::class, ActivityPolicy::class);
        }
    }

    private function registerAssets(): void
    {
        FilamentAsset::register([
            Js::make('iconify', (string) config('accelerator.assets.iconify_url'))->loadedOnRequest(),
            Js::make('leaflet-js', (string) config('accelerator.assets.leaflet_js_url'))->loadedOnRequest(),
            Css::make('leaflet-css', (string) config('accelerator.assets.leaflet_css_url'))->loadedOnRequest(),
        ], package: 'wireninja/accelerator');
    }

    private function registerLivewireNamespace(): void
    {
        $this->app->make(LivewireManager::class)->addNamespace(
            namespace: 'accelerator',
            viewPath: __DIR__.'/../../resources/views/livewire',
        );
    }

    private function registerRenderHooks(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            static fn (): View => view(
                'accelerator::filament.business-exception-handler',
                BuiltinExceptions::getFilamentBusinessExceptionViewData(),
            ),
        );

        if (! config('accelerator.features.settings')) {
            return;
        }

        FilamentView::registerRenderHook(
            PanelsRenderHook::SIDEBAR_NAV_START,
            static fn (): View => view('accelerator::filament.sidebar.notice'),
        );
        FilamentView::registerRenderHook(
            'accelerator::sidebar.support',
            static fn (): View => view('accelerator::filament.sidebar.support'),
        );
    }

    private function protectShieldCommands(): void
    {
        $prohibit = $this->app->isProduction();

        InstallCommand::prohibit($prohibit);
        PublishCommand::prohibit($prohibit);
        SetupCommand::prohibit($prohibit);
        SeederCommand::prohibit($prohibit);
        SuperAdminCommand::prohibit($prohibit);
    }

    private function configureFilament(): void
    {
        FilamentTimezone::set(config('app.timezone'));

        Table::configureUsing(static function (Table $table): void {
            $table
                ->defaultSort('id', 'desc')
                ->deferLoading()
                ->deferFilters()
                ->deferColumnManager()
                ->stackedOnMobile()
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
                ->filtersApplyAction(static function (Action $action): void {
                    $action
                        ->label('Terapkan')
                        ->icon('lucide-database-search');
                });
        });

        FileUpload::configureUsing(static function (FileUpload $fileUpload): void {
            $fileUpload
                ->imageEditor()
                ->maxParallelUploads(5)
                ->maxSize(((int) config('accelerator.uploads.max_megabytes', 100)) * 1024);
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

        Step::configureUsing(static function (Step $step): void {
            $step->completedIcon('lucide-thumbs-up');
        });

        CreateAction::configureUsing(static function (CreateAction $action): void {
            $action->icon('lucide-circle-fading-plus');
        });

        EditAction::configureUsing(static function (EditAction $action): void {
            $action->icon('lucide-notebook-pen');
        });

        DeleteAction::configureUsing(static function (DeleteAction $action): void {
            $action->icon('lucide-shredder');
        });
    }
}
