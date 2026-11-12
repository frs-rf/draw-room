<?php

namespace App\Http\Controllers;

use App\Models\Draw;
use App\Services\DrawPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;

/** Halaman penonton. Tanpa login, hanya-baca. */
class PublicDrawController extends Controller
{
    public function index(): View
    {
        return view('draw.viewer', DrawPage::data() + ['isPanitia' => false]);
    }

    /** Dipakai penonton untuk sinkron ulang (reconnect / telat masuk). */
    public function state(): JsonResponse
    {
        $draw = Draw::latestVisible();

        return response()
            ->json($draw ? $draw->publicState() : ['draw' => null, 'steps' => []])
            ->header('Cache-Control', 'no-store');
    }
}
