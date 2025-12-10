<?php

namespace App\Services\Harvy;

final class HarvyDapoApplier
{
    /**
     * Isi hanya field yang "missing_in_db" dari hasil DAPO ke model School.
     *
     * - Tidak mengubah field yang sudah punya nilai di DB.
     * - Tidak menyentuh apapun kalau status source bukan "fetched".
     *
     * @param  bool  $saveModel  Kalau true → langsung save & refresh School di sini.
     *                           Kalau false → hanya set nilai di object, tidak memanggil save().
     *
     * @return array{
     *   school_id: int|string|null,
     *   applied_count: int,
     *   applied: array<int,string>,
     *   applied_values: array<string,mixed>,
     *   reason?: string
     * }
     */
    public function applyMissingInDb(HarvyDapoResult $result, bool $saveModel = true): array
    {
        $school = $result->school;
        $status = $result->notes['status'] ?? null;

        if ($status !== 'fetched') {
            return [
                'school_id'      => $school->id ?? null,
                'applied_count'  => 0,
                'applied'        => [],
                'applied_values' => [],
                'reason'         => 'source_not_fetched',
            ];
        }

        $builder = new HarvyDapoDiffBuilder();
        $diffs   = $builder->build($result);

        $applied       = [];
        $appliedValues = [];

        foreach ($diffs as $row) {
            if (($row['change_type'] ?? null) !== 'missing_in_db') {
                continue;
            }

            $field    = $row['field'];
            $fromDapo = $row['from_dapo'];

            $school->{$field}  = $fromDapo;
            $applied[]         = $field;
            $appliedValues[$field] = $fromDapo;
        }

        if ($saveModel && ! empty($applied)) {
            $school->save();
            $school->refresh();

            // pastikan appliedValues sesuai kondisi di DB setelah save
            foreach ($applied as $field) {
                $appliedValues[$field] = $school->{$field};
            }
        }

        return [
            'school_id'      => $school->id ?? null,
            'applied_count'  => count($applied),
            'applied'        => $applied,
            'applied_values' => $appliedValues,
        ];
    }
}
