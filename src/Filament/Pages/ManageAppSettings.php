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
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Override;
use UnitEnum;
use WireNinja\Accelerator\Enums\GoogleFontEnum;
use WireNinja\Accelerator\Settings\SystemSettings;

/**
 * @property-read Schema $form
 */
class ManageAppSettings extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithFormActions;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'lucide-settings-2';

    protected static ?int $navigationSort = 90;

    protected static ?string $navigationLabel = 'App Settings';

    protected static ?string $title = 'App Settings';

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $slug = 'system/app-settings';

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
            'registration_enabled' => $this->settings->registration_enabled,
            'password_reset_enabled' => $this->settings->password_reset_enabled,
            'email_verification_enabled' => $this->settings->email_verification_enabled,
            'telegram_bot_token' => $this->settings->telegram_bot_token,
            'telegram_api_base_uri' => $this->settings->telegram_api_base_uri,
            'google_font' => $this->settings->google_font->value,
            'app_notice' => $this->settings->app_notice,
            'app_version' => $this->settings->app_version,
            'simple_page_image' => $this->settings->simple_page_image,
            'simple_page_title' => $this->settings->simple_page_title,
            'simple_page_subtitle' => $this->settings->simple_page_subtitle,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Settings')
                    ->tabs([
                        Tab::make('Branding')
                            ->icon('lucide-palette')
                            ->columns(2)
                            ->schema([
                                TextInput::make('brand_name')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                Select::make('google_font')
                                    ->label('Google font family')
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
                                    ->label('Brand logo')
                                    ->image()
                                    ->disk('public')
                                    ->directory('app-settings/branding'),
                                FileUpload::make('brand_favicon')
                                    ->label('Favicon')
                                    ->image()
                                    ->disk('public')
                                    ->directory('app-settings/branding'),
                            ]),
                        Tab::make('Authentication')
                            ->icon('lucide-shield-check')
                            ->schema([
                                Toggle::make('registration_enabled')
                                    ->label('Allow registration')
                                    ->helperText('Allow users to create new accounts.'),
                                Toggle::make('password_reset_enabled')
                                    ->label('Allow password reset')
                                    ->helperText('Allow users to reset their forgotten passwords.'),
                                Toggle::make('email_verification_enabled')
                                    ->label('Require email verification')
                                    ->helperText('Force users to verify their email address before accessing the system.'),
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
                            ])
                            ->columns(3),
                        Tab::make('System')
                            ->icon('lucide-cpu')
                            ->schema([
                                TextInput::make('app_version')
                                    ->label('App Version')
                                    ->required()
                                    ->placeholder('1.0.0'),
                                TextInput::make('app_notice')
                                    ->label('Global notice')
                                    ->placeholder('e.g. Schedule maintenance tomorrow...')
                                    ->helperText('This notice will appear in the sidebar for all users.')
                                    ->columnSpanFull(),
                                TextInput::make('simple_page_title')
                                    ->label('Auth Screen Title')
                                    ->required()
                                    ->placeholder('Manage your workspace')
                                    ->columnSpanFull(),
                                TextInput::make('simple_page_subtitle')
                                    ->label('Auth Screen Subtitle')
                                    ->required()
                                    ->placeholder('Internal System – online solutions for your workspace.')
                                    ->columnSpanFull(),
                                FileUpload::make('simple_page_image')
                                    ->label('Auth Screen Background')
                                    ->image()
                                    ->disk('public')
                                    ->helperText('Image for login and auth page background.')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                    ]),
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
        $this->settings->registration_enabled = $state['registration_enabled'];
        $this->settings->password_reset_enabled = $state['password_reset_enabled'];
        $this->settings->email_verification_enabled = $state['email_verification_enabled'];
        $this->settings->telegram_bot_token = blank($state['telegram_bot_token']) ? null : $state['telegram_bot_token'];
        $this->settings->telegram_api_base_uri = blank($state['telegram_api_base_uri']) ? null : $state['telegram_api_base_uri'];
        $this->settings->google_font = self::resolveGoogleFontEnum($state['google_font']);
        $this->settings->app_notice = $state['app_notice'];
        $this->settings->app_version = $state['app_version'];
        $this->settings->simple_page_title = $state['simple_page_title'];
        $this->settings->simple_page_subtitle = $state['simple_page_subtitle'];
        $this->settings->simple_page_image = $state['simple_page_image'];

        $this->settings->save();

        Notification::make()
            ->success()
            ->title('Application settings saved')
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
                ->label('Save')
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
                ->label('Save settings')
                ->color('primary')
                ->submit('form'),
        ];
    }
}
