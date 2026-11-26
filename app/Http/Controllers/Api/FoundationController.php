<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class FoundationController extends Controller
{
    public function index(): JsonResponse
    {
        // Active is an operational status. It never authorizes public release.
        return response()->json([])->header('X-EMPK-Publication', 'strict-v1');
    }

    public function show(string $slug): never
    {
        abort(404);
    }
}
