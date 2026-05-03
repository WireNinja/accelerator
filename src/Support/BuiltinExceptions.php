<?php

namespace WireNinja\Accelerator\Support;

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\ViewException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use WireNinja\Accelerator\Exceptions\BusinessException;

final class BuiltinExceptions
{
    public const BUSINESS_EXCEPTION_EVENT_NAME = 'accelerator-business-exception';

    public const BUSINESS_EXCEPTION_HEADER = 'X-Filament-Business-Exception';

    public const BUSINESS_EXCEPTION_TITLE_HEADER = 'X-Filament-Business-Title';

    public const BUSINESS_EXCEPTION_BODY_HEADER = 'X-Filament-Business-Body';

    public const BUSINESS_EXCEPTION_MODAL_ID = 'accelerator-business-exception-modal';

    public const BUSINESS_EXCEPTION_SESSION_TITLE = 'accelerator.business-exception.title';

    public const BUSINESS_EXCEPTION_SESSION_BODY = 'accelerator.business-exception.body';

    public static function make(Exceptions $exceptions): void
    {
        $exceptions->dontReportWhen(function () {
            if (app()->runningInConsole()) {
                return false;
            }

            return user() === null;
        });

        $exceptions->render(function (BusinessException $exception, Request $request): Response {
            if (self::isFilamentLivewireRequest($request)) {
                return self::renderFilamentLivewireBusinessException($exception);
            }

            if (self::isFilamentAdminRequest($request)) {
                return redirect()->back()->with([
                    self::BUSINESS_EXCEPTION_SESSION_TITLE => $exception->getNotificationTitle(),
                    self::BUSINESS_EXCEPTION_SESSION_BODY => $exception->getNotificationBody(),
                ]);
            }

            return $request->expectsJson()
                ? response()->json(['message' => $exception->getMessage()], 409)
                : response($exception->getMessage(), 409);
        });

        // Laravel only renders custom error views for HttpExceptionInterface instances.
        // A plain `throw new Exception` with APP_DEBUG=true triggers Ignition instead.
        // This render callback intercepts all unhandled Throwables on web requests
        // and returns the custom errors.500 view so our design is always visible.
        $exceptions->render(function (Throwable $exception, Request $request): ?Response {
            if (app()->hasDebugModeEnabled()) {
                return null;
            }

            if ($request->expectsJson()) {
                return null;
            }

            if ($exception instanceof ViewException || $exception instanceof BusinessException) {
                return null;
            }

            if (view()->exists('errors.500')) {
                return response()->view('errors.500', [
                    'exception' => $exception,
                    'errors' => new ViewErrorBag,
                ], 500);
            }

            return null;
        });
    }

    /**
     * @return array<string, int|string|null>
     */
    public static function getFilamentBusinessExceptionViewData(): array
    {
        return [
            'businessExceptionBodyHeaderName' => self::BUSINESS_EXCEPTION_BODY_HEADER,
            'businessExceptionDefaultTitle' => BusinessException::DEFAULT_NOTIFICATION_TITLE,
            'businessExceptionEventName' => self::BUSINESS_EXCEPTION_EVENT_NAME,
            'businessExceptionHeaderName' => self::BUSINESS_EXCEPTION_HEADER,
            'businessExceptionModalId' => self::BUSINESS_EXCEPTION_MODAL_ID,
            'businessExceptionSessionBody' => session(self::BUSINESS_EXCEPTION_SESSION_BODY),
            'businessExceptionSessionTitle' => session(self::BUSINESS_EXCEPTION_SESSION_TITLE),
            'businessExceptionTitleHeaderName' => self::BUSINESS_EXCEPTION_TITLE_HEADER,
        ];
    }

    public static function getFilamentBusinessExceptionStatusCode(): int
    {
        return BusinessException::FILAMENT_STATUS_CODE;
    }

    private static function isFilamentAdminRequest(Request $request): bool
    {
        if ($request->routeIs('filament.*')) {
            return true;
        }

        if ($request->is('admin') || $request->is('admin/*')) {
            return true;
        }

        return Str::contains($request->headers->get('referer', ''), '/admin');
    }

    private static function isFilamentLivewireRequest(Request $request): bool
    {
        return $request->hasHeader('X-Livewire') && self::isFilamentAdminRequest($request);
    }

    private static function renderFilamentLivewireBusinessException(BusinessException $exception): Response
    {
        $response = response()->json([
            'message' => $exception->getMessage(),
            'title' => $exception->getNotificationTitle(),
            'body' => $exception->getNotificationBody(),
        ], $exception->getFilamentStatusCode());

        $response->headers->set(self::BUSINESS_EXCEPTION_HEADER, '1');
        $response->headers->set(self::BUSINESS_EXCEPTION_TITLE_HEADER, rawurlencode($exception->getNotificationTitle()));

        if (filled($exception->getNotificationBody())) {
            $response->headers->set(self::BUSINESS_EXCEPTION_BODY_HEADER, rawurlencode($exception->getNotificationBody()));
        }

        return $response;
    }
}
