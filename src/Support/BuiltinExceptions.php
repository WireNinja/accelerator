<?php

namespace WireNinja\Accelerator\Support;

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
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
        // Guest HTTP exceptions are intentionally excluded from telemetry to suppress bot noise.
        // CLI work remains reportable; apps with public guest features must narrow this policy.
        $exceptions->dontReportWhen(function () {
            if (app()->runningInConsole()) {
                return false;
            }

            return Auth::guest();
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
            'businessExceptionSessionBody' => Cast::string(session(self::BUSINESS_EXCEPTION_SESSION_BODY), null),
            'businessExceptionSessionTitle' => Cast::string(session(self::BUSINESS_EXCEPTION_SESSION_TITLE), null),
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

        return Str::contains($request->headers->get('referer') ?? '', '/admin');
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
