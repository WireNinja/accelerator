<?php

namespace WireNinja\Accelerator\Filament\Pages;

use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Override;
use WireNinja\Accelerator\Actions\User\SendTelegramTestMessageAction;

class ManageProfile extends EditProfile
{
    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [
            ...$data,
            'avatar' => blank($data['avatar'] ?? null) ? null : $data['avatar'],
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Profile Details')
                    ->tabs([
                        Tab::make('Profil Utama')
                            ->icon(Heroicon::UserCircle)
                            ->components([
                                Grid::make(2)
                                    ->components([
                                        Section::make('Ringkasan Akun')
                                            ->description('Identitas dasar yang dipakai di seluruh panel.')
                                            ->columnSpan(1)
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
                                        Section::make('Foto Profil')
                                            ->description('Avatar yang muncul di panel dan area autentikasi.')
                                            ->columnSpan(1)
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
                            ]),

                        Tab::make('Keamanan')
                            ->icon(Heroicon::ShieldCheck)
                            ->components([
                                Grid::make(2)
                                    ->components([
                                        Section::make('Ganti Kata Sandi')
                                            ->description('Biarkan kosong jika tidak ingin mengubah kata sandi.')
                                            ->columnSpan(1)
                                            ->components([
                                                $this->getPasswordFormComponent(),
                                                $this->getPasswordConfirmationFormComponent(),
                                                $this->getCurrentPasswordFormComponent(),
                                            ]),
                                        Section::make('Status Akun')
                                            ->description('Metadata sistem — hanya baca.')
                                            ->columnSpan(1)
                                            ->components([
                                                TextInput::make('google_id')
                                                    ->label('Google ID')
                                                    ->prefixIcon(Heroicon::UserCircle)
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
                                                DateTimePicker::make('last_seen_at')
                                                    ->label('Terakhir Aktif')
                                                    ->native(false)
                                                    ->disabled(),
                                            ]),
                                    ]),
                                Section::make('Telegram')
                                    ->description('Simpan Telegram Chat ID Anda lalu kirim pesan uji untuk memastikan bot sudah terhubung.')
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
                                        Toggle::make('receives_product_price_telegram_notifications')
                                            ->label('Terima Notifikasi Harga Produk')
                                            ->helperText('Aktifkan jika Anda ingin menerima pemberitahuan Telegram saat skema harga produk berubah.'),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
