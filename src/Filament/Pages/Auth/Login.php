<?php

namespace WireNinja\Accelerator\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

class Login extends BaseLogin
{
    /** Keep login throttle keys compact and free of raw user input. */
    protected function getRateLimitKey(mixed $method, mixed $component = null): string
    {
        $method ??= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, limit: 2)[1]['function'];
        $component ??= static::class;

        $panelId = Filament::getCurrentOrDefaultPanel()?->getId() ?? 'default';
        $login = Str::lower(trim((string) ($this->data['login'] ?? 'unknown')));

        return 'lrl:'.sha1($panelId.'|'.$component.'|'.$method.'|'.$login.'|'.(request()->ip() ?? 'unknown'));
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('login')
            ->label('Email atau username')
            ->placeholder('Masukkan email atau username')
            ->autocomplete('username')
            ->required()
            ->default(config('accelerator.dev.login_default'))
            ->autofocus();
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label(__('filament-panels::auth/pages/login.form.password.label'))
            ->hint(filament()->hasPasswordReset() ? new HtmlString(Blade::render('<x-filament::link :href="filament()->getRequestPasswordResetUrl()" tabindex="-1"> {{ __(\'filament-panels::auth/pages/login.actions.request_password_reset.label\') }}</x-filament::link>')) : null)
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('current-password')
            ->required()
            ->default(config('accelerator.dev.password_default'));
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.login' => 'Email, username, atau password tidak sesuai.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $login = trim((string) ($data['login'] ?? ''));

        return [
            filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username' => $login,
            'password' => $data['password'],
        ];
    }
}
