<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\JsonResponse;

class SchoolController extends Controller
{
    public function index(): JsonResponse
    {
        $schools = School::query()->published()->orderBy('nama')->get();

        return response()->json($schools->map($this->publicProfile(...)))
            ->header('X-EMPK-Publication', 'strict-v1');
    }

    public function show(string $slug): JsonResponse
    {
        $school = School::query()->published()->where('slug', $slug)->firstOrFail();

        return response()->json($this->publicProfile($school))
            ->header('X-EMPK-Publication', 'strict-v1');
    }

    private function publicProfile(School $school): array
    {
        return [
            'slug' => $school->slug,
            'name' => $school->nama,
            'jenjang' => $school->jenjang,
            'status' => $school->status_sekolah,
            'kabkota' => $school->kabkota,
            'alamat' => $school->alamat,
            'provinsi' => $school->provinsi,
            'npsn' => $school->npsn,
            'latitude' => $school->latitude,
            'longitude' => $school->longitude,
            'cover_url' => $school->cover_url,
            'logo_url' => $school->logo_url,
            // The linked legacy foundation has no independent public approval yet.
            'yayasan_id' => null,
            'yayasan_name' => null,
        ];
    }
}
