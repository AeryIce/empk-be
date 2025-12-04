<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\JsonResponse;

class SchoolController extends Controller
{
    /**
     * GET /api/schools
     * List semua sekolah untuk halaman /schools di FE.
     */
    public function index(): JsonResponse
    {
        $schools = School::query()
            ->with('foundation')
            ->orderBy('nama')
            ->get();

        $data = $schools->map(static function (School $school): array {
            $foundation = $school->foundation;

            return [
                'slug'         => $school->slug,
                'name'         => $school->nama,
                'jenjang'      => $school->jenjang,

                // coba ambil dari status / status_sekolah kalau ada, kalau tidak ya null
                'status'       => $school->status
                    ?? $school->status_sekolah
                    ?? null,

                'kabkota'      => $school->kabkota,
                'alamat'       => $school->alamat,
                'provinsi'     => $school->provinsi,
                'npsn'         => $school->npsn,

                'latitude'     => $school->latitude,
                'longitude'    => $school->longitude,

                // ✅ Fallback: kalau sekolah belum punya cover/logo sendiri,
                // pakai cover/logo dari yayasan.
                'cover_url'    => $school->cover_url
                    ?: ($foundation?->cover_url ?? null),
                'logo_url'     => $school->logo_url
                    ?: ($foundation?->logo_url ?? null),

                'yayasan_id'   => $foundation?->kode,
                'yayasan_name' => $foundation?->name,
            ];
        });

        return response()->json($data);
    }

    /**
     * GET /api/schools/{slug}
     * (kalau nanti mau dipakai detail sekolah)
     */
    public function show(string $slug): JsonResponse
    {
        $school = School::query()
            ->with('foundation')
            ->where('slug', $slug)
            ->firstOrFail();

        $foundation = $school->foundation;

        return response()->json([
            'slug'         => $school->slug,
            'name'         => $school->nama,
            'jenjang'      => $school->jenjang,
            'status'       => $school->status
                ?? $school->status_sekolah
                ?? null,
            'kabkota'      => $school->kabkota,
            'alamat'       => $school->alamat,
            'provinsi'     => $school->provinsi,
            'npsn'         => $school->npsn,
            'latitude'     => $school->latitude,
            'longitude'    => $school->longitude,

            'cover_url'    => $school->cover_url
                ?: ($foundation?->cover_url ?? null),
            'logo_url'     => $school->logo_url
                ?: ($foundation?->logo_url ?? null),

            'yayasan_id'   => $foundation?->kode,
            'yayasan_name' => $foundation?->name,
        ]);
    }
}
