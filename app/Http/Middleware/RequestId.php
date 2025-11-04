<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;   // ← TAMBAHKAN INI
use Symfony\Component\HttpFoundation\Response;

class RequestId
{
    /**
     * Tambahkan X-Request-ID ke setiap request + inject ke log context.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->headers->get('X-Request-ID') ?: Str::uuid()->toString();

        // jadikan tersedia di log context (pakai Facade biar Intelephense paham)
        Log::withContext(['request_id' => $id]);

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        // kembalikan ke client agar bisa ditrace end-to-end
        $response->headers->set('X-Request-ID', $id);

        return $response;
    }
}
