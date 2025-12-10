<?php

namespace App\Services\Harvy;

use App\Models\School;
use DateTimeInterface;

final class HarvyDapoResult
{
    /**
     * @param array<string, mixed> $mappedFields   Data yang sudah dipetakan ke kolom-kolom schools
     * @param array<string, mixed> $rawSnapshot    Snapshot mentah hasil baca DAPO (untuk disimpan di dapo_snapshot)
     * @param array<string, string> $notes         Catatan kecil status/diagnostic
     */
    public function __construct(
        public readonly School $school,
        public readonly string $sourceUrl,
        public readonly array $mappedFields = [],
        public readonly array $rawSnapshot = [],
        public readonly ?DateTimeInterface $syncedAt = null,
        public readonly array $notes = [],
    ) {
    }

    public function hasMappedFields(): bool
    {
        return $this->mappedFields !== [];
    }
}
