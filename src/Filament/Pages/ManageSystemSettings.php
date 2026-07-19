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
use WireNinja\Accelerator\Enums\LoginLayoutEnum;
use WireNinja\Accelerator\Filament\Schemas\Components\VerticalWizard;
use WireNinja\Accelerator\Settings\SystemSettings;

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
            'password_reset_enabled' => $this->settings->password_reset_enabled,
            'email_verification_enabled' => $this->settings->email_verification_enabled,
            'support_enabled' => $this->settings->support_enabled,
            'telegram_bot_token' => $this->settings->telegram_bot_token,
            'telegram_api_base_uri' => $this->settings->telegram_api_base_uri,
            'google_font' => $this->settings->google_font->value,
            'app_notice' => $this->settings->app_notice,
            'app_version' => $this->settings->app_version,
            'simple_page_image' => $this->settings->simple_page_image,
            'simple_page_layout' => $this->settings->simple_page_layout->value,
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
                                            str_replace(' ', '+', self::resolveGoogleFontValue($get('google_font')))
                                        ) : '',
                                        filled($get('google_font')) ? sprintf(
                                            '<span style="font-family: %s; font-size: 32px !important;">The quick brown fox jumps over the lazy dog.</span>',
                                            self::resolveGoogleFontValue($get('google_font'))
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
                    Step::make('Autentikasi')
                        ->icon('lucide-shield-check')
                        ->schema([
                            Section::make('Kebijakan Akses')
                                ->description('Konfigurasi pemulihan akun dan keamanan email.')
                                ->columns(2)
                                ->schema([
                                    Toggle::make('password_reset_enabled')
                                        ->label('Izinkan Reset Kata Sandi')
                                        ->helperText('Izinkan pengguna mereset kata sandi mereka yang terlupa.'),
                                    Toggle::make('email_verification_enabled')
                                        ->label('Wajibkan Verifikasi Email')
                                        ->helperText('Paksa pengguna untuk memverifikasi alamat email mereka sebelum mengakses sistem.'),
                                ]),
                        ]),
                    Step::make('Notifikasi Telegram')
                        ->icon('lucide-bot')
                        ->schema([
                            Section::make('Koneksi Telegram')
                                ->description('Integrasi Telegram bot untuk keperluan log, notifikasi, dan alert sistem.')
                                ->columns(1)
                                ->schema([
                                    TextInput::make('telegram_bot_token')
                                        ->label('Telegram Bot Token')
                                        ->password()
                                        ->revealable()
                                        ->placeholder('123456789:AA...')
                                        ->helperText('Token bot dari BotFather untuk pengiriman notifikasi dan pesan uji Telegram.')
                                        ->columnSpanFull(),
                                    TextInput::make('telegram_api_base_uri')
                                        ->label('Telegram API Base URI')
                                        ->url()
                                        ->placeholder('https://api.telegram.org')
                                        ->helperText('Opsional. Isi jika memakai bridge atau self-hosted Telegram Bot API server.')
                                        ->columnSpanFull(),
                                ]),
                        ]),
                    Step::make('Sistem & Pemberitahuan')
                        ->icon('lucide-cpu')
                        ->schema([
                            Section::make('Sistem')
                                ->description('Atur versi aplikasi dan pengumuman global di sidebar.')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('app_version')
                                        ->label('Versi Aplikasi')
                                        ->required()
                                        ->placeholder('1.0.0'),
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
                    Step::make('Layar Login')
                        ->icon('lucide-layout')
                        ->schema([
                            Section::make('Visual Halaman Autentikasi')
                                ->description('Atur judul, subjudul, dan latar belakang visual layar login.')
                                ->columns(1)
                                ->schema([
                                    Select::make('simple_page_layout')
                                        ->label('Layout Layar Autentikasi')
                                        ->default(LoginLayoutEnum::LeftReveal->value)
                                        ->options(LoginLayoutEnum::class)
                                        ->required()
                                        ->columnSpanFull(),
                                    FileUpload::make('simple_page_image')
                                        ->label('Background Layar Autentikasi')
                                        ->image()
                                        ->disk('public')
                                        ->helperText('Gambar untuk background halaman login dan autentikasi.')
                                        ->columnSpanFull(),
                                ]),
                        ]),
                ])
                    ->navigationHeading('Pengaturan Sistem')
                    ->navigationDescription('Kelola identitas brand, autentikasi, dan preferensi sistem inti.')
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

        $this->settings->brand_name = $state['brand_name'];
        $this->settings->brand_logo = $state['brand_logo'];
        $this->settings->brand_favicon = $state['brand_favicon'];
        $this->settings->password_reset_enabled = $state['password_reset_enabled'];
        $this->settings->email_verification_enabled = $state['email_verification_enabled'];
        $this->settings->support_enabled = $state['support_enabled'];
        $this->settings->telegram_bot_token = blank($state['telegram_bot_token']) ? null : $state['telegram_bot_token'];
        $this->settings->telegram_api_base_uri = blank($state['telegram_api_base_uri']) ? null : $state['telegram_api_base_uri'];
        $this->settings->google_font = self::resolveGoogleFontEnum($state['google_font']);
        $this->settings->app_notice = $state['app_notice'];
        $this->settings->app_version = $state['app_version'];
        $this->settings->simple_page_image = $state['simple_page_image'];
        $layoutInput = $state['simple_page_layout'];
        $this->settings->simple_page_layout = $layoutInput instanceof LoginLayoutEnum
            ? $layoutInput
            : (LoginLayoutEnum::tryFrom((string) $layoutInput) ?? LoginLayoutEnum::LeftReveal);

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
