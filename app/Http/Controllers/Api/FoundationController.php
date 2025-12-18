<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Foundation;
use Illuminate\Http\JsonResponse;

class FoundationController extends Controller
{
    /**
     * List semua yayasan aktif + jumlah sekolah.
     */
    public function index(): JsonResponse
    {
        $foundations = Foundation::query()
            ->where('is_active', true)
            ->withCount('schools')
            ->orderBy('name')
            ->get()
            ->map(function (Foundation $foundation) {
                return [
                    'kode' => $foundation->kode,
                    'slug' => $foundation->slug,
                    'name' => $foundation->name,
                    'kabkota' => $foundation->kabkota,
                    'provinsi' => $foundation->provinsi,
                    'logo_url' => $foundation->logo_url,
                    'schools_count' => $foundation->schools_count,
                ];
            });

        return response()->json($foundations);
    }

    /**
     * Detail satu yayasan + sekolah di bawahnya.
     */
    public function show(string $slug): JsonResponse
    {
        $foundation = Foundation::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with(['schools' => function ($query) {
                $query->orderBy('jenjang')->orderBy('nama');
            }])
            ->firstOrFail();

        return response()->json([
            'kode' => $foundation->kode,
            'slug' => $foundation->slug,
            'name' => $foundation->name,

            'about' => $foundation->about_text,
            'vision' => $foundation->vision_text,
            'mission' => $foundation->mission_text,
            'motto' => $foundation->motto,
            'core_values' => $foundation->core_values,
            'program_unggulan' => $foundation->program_unggulan,
            'services_text' => $foundation->services_text,

            'contact' => [
                'telepon' => $foundation->telepon,
                'email' => $foundation->email,
                'website' => $foundation->website,
                'instagram' => $foundation->instagram,
                'facebook' => $foundation->facebook,
                'youtube' => $foundation->youtube,
                'whatsapp' => $foundation->whatsapp,
            ],

            'media' => [
                'logo_url' => $foundation->logo_url,
                'cover_url' => $foundation->cover_url,
                'qr_url' => $foundation->qr_url,
                'profile_image_url' => $foundation->profile_image_url,
                'brochure_pdf_url' => $foundation->brochure_pdf_url,
            ],

            'schools' => $foundation->schools->map(function ($school) use ($foundation) {
            return [
                'id' => $school->id,
                'nama' => $school->nama,
                'jenjang' => $school->jenjang,
                'kabkota' => $school->kabkota,
                'paroki' => $school->paroki,
                'slug' => $school->slug,

                // ✅ Kalau sekolah punya cover/logo sendiri → pakai itu.
                // Kalau kosong → fallback ke cover/logo yayasan.
                'cover_url' => $school->cover_url ?: ($foundation->cover_url ?? null),
                'logo_url'  => $school->logo_url  ?: ($foundation->logo_url  ?? null),
            ];
        }),
 ]);
    }
}
