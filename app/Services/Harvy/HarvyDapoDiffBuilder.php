<?php

namespace App\Services\Harvy;

use App\Models\School;

final class HarvyDapoDiffBuilder
{
    /**
     * Build diff antara nilai di DB (School) vs hasil mapping dari DAPO.
     *
     * Output: array of:
     * [
     *   'field'       => 'status_bos',
     *   'current'     => null,
     *   'from_dapo'   => 'Ya',
     *   'change_type' => 'missing_in_db' | 'missing_in_dapo' | 'different' | 'same',
     * ]
     *
     * @return array<int,array<string,mixed>>
     */
    public function build(HarvyDapoResult $result): array
    {
        /** @var School $school */
        $school   = $result->school;
        $diffs    = [];
        $fields   = $result->mappedFields ?? [];

        foreach ($fields as $field => $fromDapo) {
            // Nilai di DB
            $current = $school->{$field} ?? null;

            [$currentNorm, $fromDapoNorm] = $this->normalisePair($current, $fromDapo);

            // Tentukan tipe perubahan
            if ($currentNorm === $fromDapoNorm) {
                $changeType = 'same';
            } elseif ($currentNorm === null && $fromDapoNorm !== null) {
                $changeType = 'missing_in_db';
            } elseif ($currentNorm !== null && $fromDapoNorm === null) {
                $changeType = 'missing_in_dapo';
            } else {
                $changeType = 'different';
            }

            $diffs[] = [
                'field'       => $field,
                'current'     => $current,
                'from_dapo'   => $fromDapo,
                'change_type' => $changeType,
            ];
        }

        return $diffs;
    }

    /**
     * Normalisasi nilai sebelum dibandingkan:
     * - String kosong / "-" → null
     * - String dengan spasi di-trim
     * - Numeric string → tetap boleh, nanti distandarkan kalau dua-duanya numeric
     *
     * @return array{0:mixed,1:mixed}
     */
    private function normalisePair(mixed $current, mixed $fromDapo): array
    {
        $currentNorm   = $this->normaliseValue($current);
        $fromDapoNorm  = $this->normaliseValue($fromDapo);

        // Kalau dua-duanya numeric, samakan ke float supaya "6" == "6.0"
        if (is_numeric($currentNorm) && is_numeric($fromDapoNorm)) {
            $currentNorm  = (float) $currentNorm;
            $fromDapoNorm = (float) $fromDapoNorm;
        }

        return [$currentNorm, $fromDapoNorm];
    }

    /**
     * Normalisasi 1 nilai.
     */
    private function normaliseValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '' || $trimmed === '-') {
                return null;
            }

            return $trimmed;
        }

        return $value;
    }
}
