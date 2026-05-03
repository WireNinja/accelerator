<?php

namespace WireNinja\Accelerator\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

class Login extends BaseLogin
{
    public function getView(): string
    {
        return 'accelerator::filament.pages.auth.login';
    }

    public function getLayout(): string
    {
        return 'accelerator::components.filament.layout.login';
    }

    /**
     * Dont remove this. This is required to make the rate limit works on the login page.
     * Why? Because we may use octane in the prod, and default rate limit key is too long for swoole table.
     */
    protected function getRateLimitKey($method, $component = null): string
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
            ->autofocus();
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
