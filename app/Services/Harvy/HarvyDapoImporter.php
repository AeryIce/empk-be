<?php

namespace App\Services\Harvy;

use App\Models\School;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

final class HarvyDapoImporter
{
    /**
     * Jalankan Harvy untuk 1 sekolah:
     * - Resolve URL DAPO
     * - Fetch HTML dengan retry kecil (ala auto-F5)
     * - Parsing ringan → mappedFields
     * - Bungkus hasil ke HarvyDapoResult (belum apply ke DB).
     */
    public function runForSchool(School $school): HarvyDapoResult
    {
        $sourceUrl = $this->resolveSourceUrl($school);
        $syncedAt  = CarbonImmutable::now();

        $mappedFields = [];
        $rawSnapshot  = [];
        $notes        = [];

        // Fetch dengan retry kecil
        $fetch       = $this->fetchWithRetry($sourceUrl);
        $response    = $fetch['response'];
        $attempts    = $fetch['attempts'];
        $attemptLogs = $fetch['attempt_logs'];
        $exception   = $fetch['exception'];

        $notes['attempts']     = $attempts;
        $notes['attempt_logs'] = $attemptLogs;

        // Semua percobaan gagal total
        if ($response === null) {
            $notes['status']    = 'exception';
            $notes['exception'] = $exception ? get_class($exception) : 'UnknownException';
            $notes['message']   = $exception
                ? $exception->getMessage()
                : 'Tidak ada response dari DAPO setelah beberapa percobaan.';

            return new HarvyDapoResult(
                school: $school,
                sourceUrl: $sourceUrl,
                mappedFields: $mappedFields,
                rawSnapshot: $rawSnapshot,
                syncedAt: $syncedAt,
                notes: $notes,
            );
        }

        // Ada response tapi tetap tidak sukses (404/401/500 setelah retry)
        if (! $response->successful()) {
            $notes['status']      = 'http_error';
            $notes['http_status'] = $response->status();
            $notes['message']     = 'Gagal mengambil halaman DAPO (response tidak sukses) setelah beberapa percobaan.';

            return new HarvyDapoResult(
                school: $school,
                sourceUrl: $sourceUrl,
                mappedFields: $mappedFields,
                rawSnapshot: $rawSnapshot,
                syncedAt: $syncedAt,
                notes: $notes,
            );
        }

        // Berhasil
        $html = $response->body();

        $mappedFields = $this->parseHtmlToMappedFields($html);

        $rawSnapshot = [
            'source'      => 'dapo',
            'url'         => $sourceUrl,
            'fetched_at'  => $syncedAt->toIso8601String(),
            'content_len' => mb_strlen($html, '8bit'),
            'html'        => $html,
        ];

        $notes['status']              = 'fetched';
        $notes['message']             = 'HTML DAPO berhasil diambil dan dicoba diparsing.';
        $notes['content_length']      = $rawSnapshot['content_len'];
        $notes['mapped_fields_count'] = count($mappedFields);

        return new HarvyDapoResult(
            school: $school,
            sourceUrl: $sourceUrl,
            mappedFields: $mappedFields,
            rawSnapshot: $rawSnapshot,
            syncedAt: $syncedAt,
            notes: $notes,
        );
    }

    /**
     * Parsing ringan HTML DAPO → mappedFields.
     * Belum sempurna, tapi cukup buat langkah bayi.
     *
     * @return array<string,mixed>
     */
    private function parseHtmlToMappedFields(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        // Ganti <br> & <p> jadi newline biar enak di-split
        $normalized = preg_replace('~<(br|p)\b[^>]*>~i', "\n", $html);
        if ($normalized === null) {
            $normalized = $html;
        }

        // Buang semua tag HTML → jadi text polos
        $text = strip_tags($normalized);

        // Normalisasi line break
        $text  = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = array_map('trim', explode("\n", $text));

        // Buang baris kosong
        $lines = array_values(array_filter($lines, static fn ($line) => $line !== ''));

        $fields = [];

        // --- Identitas & legal resmi ---
        $fields['status_kepemilikan_resmi']      = $this->findValueAfterLabel($lines, 'Status Kepemilikan');
        $fields['sk_pendirian_nomor']            = $this->findValueAfterLabel($lines, 'SK Pendirian Sekolah');
        $fields['sk_pendirian_tanggal']          = $this->normaliseDate($this->findValueAfterLabel($lines, 'Tanggal SK Pendirian'));
        $fields['sk_izin_operasional_nomor']     = $this->findValueAfterLabel($lines, 'SK Izin Operasional');
        $fields['sk_izin_operasional_tanggal']   = $this->normaliseDate($this->findValueAfterLabel($lines, 'Tanggal SK Izin Operasional'));

        // --- Data pelengkap ---
        $fields['kebutuhan_khusus_dilayani']     = $this->findValueAfterLabel($lines, 'Kebutuhan Khusus Dilayani');
        $fields['nama_bank']                     = $this->normalizeZeroLike($this->findValueAfterLabel($lines, 'Nama Bank'));
        $fields['cabang_bank']                   = $this->normalizeZeroLike($this->findValueAfterLabel($lines, 'Cabang KCP/Unit'));
        $fields['rekening_atas_nama']            = $this->normalizeZeroLike($this->findValueAfterLabel($lines, 'Rekening Atas Nama'));

        // --- Data rinci ---
        $fields['status_bos']                    = $this->findValueAfterLabel($lines, 'Status BOS');
        // DAPO typo "Waku Penyelenggaraan"
        $fields['waktu_penyelenggaraan']         = $this->findValueAfterLabel($lines, 'Waku Penyelenggaraan')
            ?? $this->findValueAfterLabel($lines, 'Waktu Penyelenggaraan');
        $fields['sertifikasi_iso']               = $this->findValueAfterLabel($lines, 'Sertifikasi ISO');
        $fields['sumber_listrik_resmi']          = $this->findValueAfterLabel($lines, 'Sumber Listrik');
        $fields['daya_listrik_va']               = $this->parseIntegerFromText($this->findValueAfterLabel($lines, 'Daya Listrik'));
        $fields['kecepatan_internet_mbps']       = $this->parseIntegerFromText($this->findValueAfterLabel($lines, 'Kecepatan Internet'));

        // --- Kontak & geo-lokasi ---
        $fields['lintang']                       = $this->parseFloatFromText($this->findValueAfterLabel($lines, 'Lintang'));
        $fields['bujur']                         = $this->parseFloatFromText($this->findValueAfterLabel($lines, 'Bujur'));
        $fields['operator_sekolah']              = $this->findValueAfterLabel($lines, 'Operator');

        // Buang yang null/kosong biar diff nanti rapih
        return array_filter(
            $fields,
            static fn ($value) => $value !== null && $value !== ''
        );
    }

    /**
     * Cari nilai setelah label pada baris "Label : Nilai".
     */
    private function findValueAfterLabel(array $lines, string $label): ?string
    {
        foreach ($lines as $line) {
            $pos = stripos($line, $label . ' :');
            if ($pos === false) {
                $pos = stripos($line, $label . ':');
            }
            if ($pos === false) {
                continue;
            }

            $after = substr($line, $pos + strlen($label));
            // Buang ":" dan spasi di depan nilai
            $after = preg_replace('/^\s*:?\s*/', '', (string) $after);
            $value = trim((string) $after);

            if ($value === '') {
                return null;
            }

            return $value;
        }

        return null;
    }

    /**
     * Normalisasi tanggal ke Y-m-d kalau bisa, atau null kalau ragu.
     */
    private function normaliseDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || $value === '-') {
            return null;
        }

        // Sudah Y-m-d
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }

        // d-m-Y → Y-m-d
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $value, $m) === 1) {
            return sprintf('%s-%s-%s', $m[3], $m[2], $m[1]);
        }

        return null;
    }

    /**
     * Ubah "0", "-", "" → null (buat field bank).
     */
    private function normalizeZeroLike(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === '-' || $trimmed === '0' || $trimmed === '0.0') {
            return null;
        }

        return $trimmed;
    }

    /**
     * Ambil angka bulat dari teks ("5.500 m2" → 5500).
     */
    private function parseIntegerFromText(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $value);
        if ($digits === null || $digits === '' || $digits === '0') {
            return null;
        }

        $int = (int) $digits;

        return $int > 0 ? $int : null;
    }

    /**
     * Ambil angka pecahan dari teks ("-6.1944000000" → -6.1944).
     */
    private function parseFloatFromText(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalized = str_replace(',', '.', $value);

        if (preg_match('/-?\d+(?:\.\d+)?/', $normalized, $m) !== 1) {
            return null;
        }

        return (float) $m[0];
    }

    /**
     * Versi yang sekaligus menyimpan snapshot & dapo_last_sync_at ke DB
     * kalau statusnya "fetched".
     */
    public function syncAndStoreForSchool(School $school): HarvyDapoResult
    {
        $result = $this->runForSchool($school);

        if (($result->notes['status'] ?? null) !== 'fetched') {
            return $result;
        }

        $school->dapo_last_sync_at = $result->syncedAt;
        $school->dapo_snapshot     = $result->rawSnapshot;
        $school->save();

        return $result;
    }

    /**
     * Ambil URL:
     * - pakai dapo_school_url kalau ada,
     * - kalau tidak, bangun dari dapo_id.
     */
    private function resolveSourceUrl(School $school): string
    {
        if (! empty($school->dapo_school_url)) {
            return $school->dapo_school_url;
        }

        if (! empty($school->dapo_id)) {
            return sprintf('https://dapo.kemendikdasmen.go.id/sekolah/%s', $school->dapo_id);
        }

        throw new InvalidArgumentException(
            'School tidak memiliki dapo_school_url maupun dapo_id. Harvy tidak tahu harus baca dari mana.'
        );
    }

    /**
     * Fetch ke DAPO dengan retry kecil (ala auto-F5).
     *
     * @return array{
     *   response: ?\Illuminate\Http\Client\Response,
     *   attempts: int,
     *   attempt_logs: array<int,array<string,mixed>>,
     *   exception: ?Throwable
     * }
     */
    private function fetchWithRetry(string $url, int $maxAttempts = 3, int $delayMs = 350): array
    {
        $attempts      = 0;
        $attemptLogs   = [];
        $response      = null;
        $lastException = null;

        $retryStatusCodes = [404, 500, 502, 503, 504];

        while ($attempts < $maxAttempts) {
            $attempts++;

            try {
                $res = Http::timeout(15)->get($url);

                $attemptLogs[] = [
                    'attempt' => $attempts,
                    'status'  => $res->status(),
                ];

                if ($res->successful()) {
                    $response = $res;
                    break;
                }

                if (! in_array($res->status(), $retryStatusCodes, true)) {
                    break;
                }
            } catch (Throwable $e) {
                $lastException = $e;
                $attemptLogs[] = [
                    'attempt'   => $attempts,
                    'exception' => get_class($e),
                    'message'   => $e->getMessage(),
                ];
            }

            if ($attempts < $maxAttempts) {
                usleep($delayMs * 1000);
            }
        }

        return [
            'response'     => $response,
            'attempts'     => $attempts,
            'attempt_logs' => $attemptLogs,
            'exception'    => $lastException,
        ];
    }
}
