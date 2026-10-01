<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppContent;
use Illuminate\Http\JsonResponse;

class AppContentController extends Controller
{
    public function index(): JsonResponse
    {
        $contents = AppContent::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('key');

        return response()->json($contents);
    }
}