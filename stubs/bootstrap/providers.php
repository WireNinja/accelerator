<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AccountingPanelProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\AppPanelProvider;
use App\Providers\Filament\ProductionPanelProvider;
use App\Providers\Filament\SalesPanelProvider;
use App\Providers\Filament\SupportPanelProvider;
use Laravel\Fortify\FortifyServiceProvider;
use WireNinja\Accelerator\AcceleratorServiceProvider;
use WireNinja\Accelerator\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    AppPanelProvider::class,
    ProductionPanelProvider::class,
    SalesPanelProvider::class,
    AccountingPanelProvider::class,
    SupportPanelProvider::class,
    AcceleratorServiceProvider::class,
    HorizonServiceProvider::class,
    FortifyServiceProvider::class,
];
