<?php

namespace App\Services\Harvy;

use App\Models\School;

final class HarvyDapoSyncService
{
    /**
     * Sinkron 1 sekolah:
     * - Fetch dari DAPO
     * - Simpan snapshot & dapo_last_sync_at
     * - Apply hanya field yang missing_in_db
     *
     * @return array{
     *   school_id: int|string|null,
     *   status: string|null,
     *   http_status: int|null,
     *   attempts: int|null,
     *   mapped_fields_count: int|null,
     *   applied_count: int,
     *   applied: array<int,string>,
     *   applied_values: array<string,mixed>,
     *   reason?: string
     * }
     */
    public function syncSchool(School $school): array
    {
        $importer = new HarvyDapoImporter();

        // 1. Fetch & parsing dari DAPO
        $result = $importer->runForSchool($school);

        $status          = $result->notes['status']             ?? null;
        $httpStatus      = $result->notes['http_status']        ?? null;
        $attempts        = $result->notes['attempts']           ?? null;
        $mappedFieldsCnt = $result->notes['mapped_fields_count'] ?? null;

        $summary = [
            'school_id'           => $school->id ?? null,
            'status'              => $status,
            'http_status'         => $httpStatus,
            'attempts'            => $attempts,
            'mapped_fields_count' => $mappedFieldsCnt,
            'applied_count'       => 0,
            'applied'             => [],
            'applied_values'      => [],
        ];

        // Kalau DAPO nggak berhasil di-fetch, jangan lanjut apply apapun
        if ($status !== 'fetched') {
            $summary['reason'] = 'source_not_fetched';

            return $summary;
        }

        // 2. Simpan snapshot & waktu sync ke DB (tanpa mengganggu field lain)
        $school->dapo_last_sync_at = $result->syncedAt;
        $school->dapo_snapshot     = $result->rawSnapshot;

        // Jangan save dulu, biar sekali jalan bareng perubahan field dari applier

        // 3. Apply hanya field yang missing_in_db
        $applier      = new HarvyDapoApplier();
        $applySummary = $applier->applyMissingInDb($result, false); // false = jangan save di dalam Applier

        // Gabungkan perubahan yang dihasilkan Applier ke $school
        foreach ($applySummary['applied_values'] as $field => $value) {
            $school->{$field} = $value;
        }

        // 4. Save sekali saja untuk semua perubahan
        if (! empty($applySummary['applied']) || $status === 'fetched') {
            $school->save();
            $school->refresh();
        }

        $summary['applied']        = $applySummary['applied'];
        $summary['applied_count']  = $applySummary['applied_count'];
        $summary['applied_values'] = $applySummary['applied_values'];

        return $summary;
    }

    /**
     * Helper kalau mau dipanggil langsung pakai ID dari tinker / command.
     *
     * @return array<string,mixed>
     */
    public function syncSchoolById(int $schoolId): array
    {
        $school = School::find($schoolId);

        if (! $school) {
            return [
                'school_id'           => $schoolId,
                'status'              => null,
                'http_status'         => null,
                'attempts'            => null,
                'mapped_fields_count' => null,
                'applied_count'       => 0,
                'applied'             => [],
                'applied_values'      => [],
                'reason'              => 'school_not_found',
            ];
        }

        return $this->syncSchool($school);
    }
}
