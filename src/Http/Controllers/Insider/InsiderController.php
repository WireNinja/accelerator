<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Http\Controllers\Insider;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WireNinja\Accelerator\Support\Diagnostics\InsiderDiagnostics;

final class InsiderController extends Controller
{
    public function __invoke(Request $request, InsiderDiagnostics $diagnostics): JsonResponse|View
    {
        $sections = $diagnostics->collect($request);

        if ($request->expectsJson() || $request->boolean('json')) {
            return response()->json(['schema' => 1, 'sections' => $sections]);
        }

        return view('accelerator::insider.index', ['sections' => $sections]);
    }
}
