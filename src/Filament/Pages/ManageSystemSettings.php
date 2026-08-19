<?php

namespace WireNinja\Accelerator\Filament\Pages;

use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Override;
use UnitEnum;
use WireNinja\Accelerator\Enums\GoogleFontEnum;
use WireNinja\Accelerator\Filament\Schemas\Components\VerticalWizard;
use WireNinja\Accelerator\Settings\SystemSettings;
use WireNinja\Accelerator\Support\Cast;

/**
 * @property-read Schema $form
 */
class ManageSystemSettings extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithFormActions;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-settings-2';

    protected static ?int $navigationSort = 90;

    protected static ?string $navigationLabel = 'Pengaturan Sistem';

    protected static ?string $title = 'Pengaturan Sistem';

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $slug = 'system/settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var SystemSettings */
    protected $settings;

    public function mount(): void
    {
        $this->settings = resolve(SystemSettings::class);

        $this->form->fill([
            'brand_name' => $this->settings->brand_name,
            'brand_logo' => blank($this->settings->brand_logo) ? null : $this->settings->brand_logo,
            'brand_favicon' => blank($this->settings->brand_favicon) ? null : $this->settings->brand_favicon,
            'support_enabled' => $this->settings->support_enabled,
            'google_font' => $this->settings->google_font->value,
            'app_notice' => $this->settings->app_notice,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                VerticalWizard::make([
                    Step::make('Identitas Visual')
                        ->icon('lucide-palette')
                        ->schema([
                            Section::make('Identitas Visual')
                                ->description('Kelola identitas visual dan tipografi aplikasi.')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('brand_name')
                                        ->label('Nama Aplikasi')
                                        ->required()
                                        ->maxLength(255)
                                        ->columnSpanFull(),
                                    Select::make('google_font')
                                        ->label('Keluarga Font Google')
                                        ->default(GoogleFontEnum::Poppins->value)
                                        ->options(GoogleFontEnum::class)
                                        ->live()
                                        ->columnSpanFull()
                                        ->helperText(fn (?GoogleFontEnum $state): string => self::resolveGoogleFontEnum($state)->getDescription()),
                                    Text::make(fn (Get $get): HtmlString => new HtmlString(sprintf(
                                        '%s %s',
                                        filled($get('google_font')) ? sprintf(
                                            '<link href="https://fonts.googleapis.com/css2?family=%s&display=swap" rel="stylesheet">',
                                            str_replace(' ', '+', self::resolveGoogleFontValue(Cast::string($get('google_font'), null)))
                                        ) : '',
                                        filled($get('google_font')) ? sprintf(
                                            '<span style="font-family: %s; font-size: 32px !important;">The quick brown fox jumps over the lazy dog.</span>',
                                            self::resolveGoogleFontValue(Cast::string($get('google_font'), null))
                                        ) : '',
                                    )))->columnSpanFull(),
                                    FileUpload::make('brand_logo')
                                        ->label('Logo Brand')
                                        ->image()
                                        ->disk('public')
                                        ->directory('app-settings/branding'),
                                    FileUpload::make('brand_favicon')
                                        ->label('Favicon')
                                        ->image()
                                        ->disk('public')
                                        ->directory('app-settings/branding'),
                                ]),
                        ]),
                    Step::make('Sistem & Pemberitahuan')
                        ->icon('lucide-cpu')
                        ->schema([
                            Section::make('Sistem')
                                ->description('Atur pengumuman global dan bantuan pengembang di sidebar.')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('app_notice')
                                        ->label('Pemberitahuan Global')
                                        ->placeholder('Contoh: Maintenance terjadwal besok...')
                                        ->helperText('Pemberitahuan ini akan muncul di sidebar untuk semua pengguna.')
                                        ->columnSpanFull(),
                                    Toggle::make('support_enabled')
                                        ->label('Tampilkan Bantuan (Support)')
                                        ->helperText('Tampilkan kotak bantuan pengembang (Whatsapp & Telegram) di sidebar.')
                                        ->columnSpanFull(),
                                ]),
                        ]),
                ])
                    ->navigationHeading('Pengaturan Sistem')
                    ->navigationDescription('Kelola identitas brand dan preferensi sistem inti.')
                    ->sticky(false)
                    ->skippable()
                    ->columnSpanFull(),
            ]);
    }

    #[Override]
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    EmbeddedSchema::make('form'),
                ])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        SchemaActions::make($this->getFormActions())
                            ->key('form-actions'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $this->settings = resolve(SystemSettings::class);

        $state = $this->form->getState();

        $this->settings->brand_name = Cast::mustString($state['brand_name'] ?? null);
        $this->settings->brand_logo = Cast::string($state['brand_logo'] ?? null, null);
        $this->settings->brand_favicon = Cast::string($state['brand_favicon'] ?? null, null);
        $this->settings->support_enabled = Cast::bool($state['support_enabled'] ?? false);
        $this->settings->google_font = self::resolveGoogleFontEnum(Cast::string($state['google_font'] ?? null, null));
        $this->settings->app_notice = Cast::string($state['app_notice'] ?? null, null);

        $this->settings->save();

        Notification::make()
            ->success()
            ->title('Pengaturan sistem berhasil disimpan')
            ->send();
    }

    protected static function resolveGoogleFontEnum(string|GoogleFontEnum|null $font): GoogleFontEnum
    {
        if ($font instanceof GoogleFontEnum) {
            return $font;
        }

        return GoogleFontEnum::tryFrom((string) $font) ?? GoogleFontEnum::Poppins;
    }

    protected static function resolveGoogleFontValue(string|GoogleFontEnum|null $font): string
    {
        return self::resolveGoogleFontEnum($font)->value;
    }

    /**
     * @return Action[]
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Simpan')
                ->color('primary')
                ->submit('form'),
        ];
    }

    /**
     * @return Action[]
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Simpan pengaturan')
                ->color('primary')
                ->submit('form'),
        ];
    }
}
