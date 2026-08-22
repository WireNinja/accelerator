<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Tests\Feature;

use Filament\Facades\Filament;
use Filament\FilamentManager;
use Filament\Panel;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\SocialiteServiceProvider;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Mockery;
use Orchestra\Testbench\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use WireNinja\Accelerator\Http\Controllers\Auth\GoogleAuthController;
use WireNinja\Accelerator\Services\GoogleOAuthService;

final class GoogleAuthControllerTest extends TestCase
{
    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [SocialiteServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('session.driver', 'array');
    }

    public function test_it_always_prompts_google_to_select_an_account(): void
    {
        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('with')
            ->once()
            ->with(['prompt' => 'select_account'])
            ->andReturnSelf();
        $provider->shouldReceive('redirect')
            ->once()
            ->andReturn(new RedirectResponse('https://accounts.google.test'));

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $response = app(GoogleAuthController::class)->redirect();

        $this->assertSame('https://accounts.google.test', $response->getTargetUrl());
    }

    public function test_it_reports_an_authorization_rejection_and_notifies_the_user(): void
    {
        $this->mockCallbackFailure(new AuthenticationException('No provisioned user matches this Google account.'));

        Log::shouldReceive('notice')
            ->once()
            ->with('Google OAuth login rejected.', [
                'reason' => 'No provisioned user matches this Google account.',
            ]);

        $response = $this->performCallback();

        $this->assertSame('http://localhost/admin/login', $response->getTargetUrl());
        $this->assertDangerNotification('Akun Google tidak diizinkan masuk.');
    }

    public function test_it_reports_invalid_state_separately_and_notifies_the_user(): void
    {
        $exception = new InvalidStateException;
        $this->mockCallbackFailure($exception);

        Log::shouldReceive('notice')
            ->once()
            ->with('Google OAuth state validation failed.', [
                'exception' => InvalidStateException::class,
            ]);

        $response = $this->performCallback();

        $this->assertSame('http://localhost/admin/login', $response->getTargetUrl());
        $this->assertDangerNotification('Sesi login Google tidak valid atau kedaluwarsa. Silakan coba lagi.');
    }

    private function performCallback(): RedirectResponse
    {
        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getLoginUrl')->once()->andReturn('/admin/login');
        $filament = Mockery::mock(FilamentManager::class);
        $filament->shouldReceive('getPanel')->once()->with('admin')->andReturn($panel);
        Filament::swap($filament);

        $request = Request::create('/auth/google/callback');
        $request->setLaravelSession($this->app['session']->driver());

        return app(GoogleAuthController::class)->callback(
            $request,
            app(GoogleOAuthService::class),
        );
    }

    private function mockCallbackFailure(AuthenticationException|InvalidStateException $exception): void
    {
        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('user')->once()->andThrow($exception);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);
    }

    private function assertDangerNotification(string $title): void
    {
        /** @var array<int, array<string, mixed>> $notifications */
        $notifications = session('filament.notifications', []);

        $this->assertCount(1, $notifications);
        $this->assertSame($title, $notifications[0]['title']);
        $this->assertSame('danger', $notifications[0]['status']);
    }
}
