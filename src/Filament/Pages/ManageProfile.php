<?php

namespace WireNinja\Accelerator\Filament\Pages;

use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Override;
use WireNinja\Accelerator\Actions\User\SendTelegramTestMessageAction;
use WireNinja\Accelerator\Filament\Forms\Components\BooleanCard;
use WireNinja\Accelerator\Filament\Schemas\Components\VerticalWizard;

class ManageProfile extends EditProfile
{
    protected Width|string|null $maxWidth = Width::SixExtraLarge;

    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [
            ...$data,
            'avatar' => blank($data['avatar'] ?? null) ? null : $data['avatar'],
            'organization' => [
                'name' => $this->getProfileOrganization()?->getAttribute('name'),
            ],
        ];
    }

    #[Override]
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $organizationData = $data['organization'] ?? null;

        unset($data['organization']);

        if (is_array($organizationData) && filled($organizationData['name'] ?? null)) {
            $this->getProfileOrganization()?->update([
                'name' => $organizationData['name'],
            ]);
        }

        return $data;
    }

    public function form(Schema $schema): Schema
    {
        $steps = [
            Step::make('Informasi Dasar')
                ->icon('lucide-user')
                ->schema([
                    Section::make('Informasi Dasar')
                        ->description('Identitas dasar yang dipakai di seluruh panel.')
                        ->components([
                            TextInput::make('name')
                                ->label('Nama Lengkap')
                                ->placeholder('Contoh: John Doe')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('username')
                                ->label('Username')
                                ->placeholder('john_doe')
                                ->unique(ignoreRecord: true)
                                ->maxLength(255),
                            TextInput::make('email')
                                ->label('Alamat Email')
                                ->email()
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255),
                        ]),
                ]),
        ];

        if ($this->hasProfileOrganization()) {
            $steps[] = Step::make('Organisasi')
                ->icon('lucide-building-2')
                ->schema([
                    Section::make('Organisasi')
                        ->description('Konfigurasi organisasi yang terhubung dengan akun Anda.')
                        ->icon('lucide-building-2')
                        ->components([
                            TextInput::make('organization.name')
                                ->label('Nama Organisasi')
                                ->placeholder('Contoh: PT Nusantara Jaya')
                                ->required()
                                ->maxLength(255),
                        ]),
                ]);
        }

        $steps = [
            ...$steps,
            Step::make('Foto Profil')
                ->icon('lucide-image')
                ->schema([
                    Section::make('Foto Profil')
                        ->description('Avatar yang muncul di panel dan area autentikasi.')
                        ->components([
                            FileUpload::make('avatar')
                                ->label('Foto Profil')
                                ->image()
                                ->avatar()
                                ->imageEditor()
                                ->disk('public')
                                ->directory('avatars')
                                ->translateLabel(),
                        ]),
                ]),

            Step::make('Keamanan')
                ->icon('lucide-key-round')
                ->schema([
                    Section::make('Keamanan Akun')
                        ->description('Biarkan kosong jika tidak ingin mengubah kata sandi.')
                        ->components([
                            $this->getPasswordFormComponent(),
                            $this->getPasswordConfirmationFormComponent(),
                            $this->getCurrentPasswordFormComponent(),
                        ]),
                ]),

            Step::make('Status Akun')
                ->icon('lucide-info')
                ->schema([
                    Section::make('Status Akun')
                        ->description('Metadata sistem — hanya baca.')
                        ->components([
                            TextInput::make('google_id')
                                ->label('Google ID')
                                ->disabled()
                                ->helperText('Dihubungkan melalui SSO Google.'),
                            DateTimePicker::make('email_verified_at')
                                ->label('Email Diverifikasi')
                                ->native(false)
                                ->disabled(),
                            DateTimePicker::make('two_factor_confirmed_at')
                                ->label('2FA Dikonfirmasi')
                                ->native(false)
                                ->disabled(),
                        ]),
                ]),

            Step::make('Notifikasi & Telegram')
                ->icon('lucide-send')
                ->schema([
                    Section::make('Telegram')
                        ->description('Hubungkan akun Telegram Anda untuk menerima notifikasi sistem.')
                        ->components([
                            TextInput::make('telegram_chat_id')
                                ->label('Telegram Chat ID')
                                ->placeholder('Contoh: 123456789')
                                ->maxLength(255)
                                ->prefixIcon('lucide-send')
                                ->helperText('User harus mengirim pesan ke bot terlebih dahulu agar chat id valid dan bisa menerima notifikasi.')
                                ->suffixAction(
                                    Action::make('sendTelegramTestMessage')
                                        ->icon('lucide-send-horizontal')
                                        ->tooltip('Kirim pesan uji Telegram')
                                        ->disabled(fn (Get $get): bool => blank($get('telegram_chat_id')))
                                        ->action(function (Get $get): void {
                                            resolve(SendTelegramTestMessageAction::class)->handle(
                                                mustUser(),
                                                (string) $get('telegram_chat_id'),
                                            );

                                            Notification::make()
                                                ->success()
                                                ->title('Pesan uji Telegram terkirim')
                                                ->body('Periksa chat Telegram Anda untuk memastikan bot sudah terhubung.')
                                                ->send();
                                        }),
                                    true,
                                ),
                            BooleanCard::make('receives_product_price_telegram_notifications')
                                ->label('Terima Notifikasi Harga Produk')
                                ->trueLabel('Aktifkan Notifikasi Harga')
                                ->trueDescription('Terima notifikasi Telegram saat skema harga produk dibuat, diubah, atau dihapus.'),
                        ]),
                ]),
        ];

        return $schema
            ->components([
                VerticalWizard::make($steps)
                    ->navigationHeading('Kelola Profil')
                    ->navigationDescription('Atur informasi pribadi, keamanan akun, dan saluran notifikasi.')
                    ->sticky(false)
                    ->skippable()
                    ->columnSpanFull(),
            ]);
    }

    private function hasProfileOrganization(): bool
    {
        return $this->getProfileOrganization() instanceof Model;
    }

    private function getProfileOrganization(): ?Model
    {
        $tenant = Filament::getTenant();

        if ($tenant instanceof Model) {
            return $tenant;
        }

        $user = $this->getUser();

        foreach (['organization', 'organizations'] as $relationshipName) {
            if (! method_exists($user, $relationshipName)) {
                continue;
            }

            $relationship = $user->{$relationshipName}();

            if ($relationship instanceof Relation) {
                return $relationship->first();
            }
        }

        return null;
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->label('Simpan Perubahan')
            ->icon('lucide-user-round-check');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()
            ->label('Kembali ke Dashboard')
            ->icon('lucide-undo');
    }
}
