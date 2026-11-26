# Self-service profil Sekolah dan LPK

Rancangan review untuk Issue #13. P0 menutup publikasi registri lama dan mengurangi izin Filament. Skema serta endpoint P1 di bawah ini adalah kontrak usulan untuk review Aga.

## Model data

Registri kanonis tetap berada di schools dan foundations. Pertahankan PK, slug, NPSN, kode dan relasi. Seluruh data warisan tanpa persetujuan baru berstatus belum ditinjau. P0 meminta schools.is_published bersama status verified serta verified_at/verified_by untuk Sekolah. Flag historis saja tidak cukup. Alur review untuk membuat persetujuan baru masuk P1. P0 mengembalikan daftar kosong dan detail 404 untuk LPK. foundations.is_active tetap berarti aktif secara operasional. P0 tidak mewariskan media atau nama LPK ke profil sekolah. Direktori identitas minimal memerlukan kontrak publik tersendiri.

| Tabel usulan | Kolom dan batasan |
| --- | --- |
| organization_memberships | id, user_id FK users, school_id FK schools nullable, foundation_id FK foundations nullable, role, state, invited_at, activated_at, revoked_at, granted_by/revoked_by FK users. CHECK mensyaratkan persis satu FK lembaga dan role yang sesuai (school_admin atau foundation_admin). Indeks unik parsial per user dan FK lembaga, indeks user+state dan FK+state. |
| staff_invitations | id publik acak, membership_id FK, alamat ternormalisasi atau referensi akun, token_digest unik, delivery, issued_by, expires_at, consumed_at, revoked_at, last_sent_at. Penerbitan ulang mencabut token sebelumnya dalam transaksi. Maksimal satu undangan belum dituntaskan per membership dengan indeks parsial; undangan kedaluwarsa dicabut saat mengganti. |
| organization_profiles | id, school_id atau foundation_id dengan FK, CHECK tepat satu, indeks unik per lembaga, published_revision_id nullable. Pointer tidak boleh mengarah ke revisi profil lain; tegakkan FK komposit (id, published_revision_id) ke (profile_id, id). |
| profile_revisions | id, profile_id FK, revision_number, status, lock_version, schema_version, payload JSONB, creator, submitter, reviewer, publisher (FK user), catatan review dan timestamp. UNIQUE (profile_id, revision_number). Satu revisi aktif per profil untuk status draft/submitted/needs_changes/approved. Revisi yang sudah diajukan dibekukan. |
| staff_audit_events | id, actor_id, action, membership_id atau profile_revision_id, occurred_at, request_id, alasan dan metadata minimal yang di-allowlist. Audit issuance, reissue, activation, revoke, review dan publish. Hindari email penuh, payload profil, kode, token dan password. |

Penerapan tabel bersifat aditif. P1 tidak melakukan backfill publik atau mempromosikan flag historis menjadi bukti persetujuan. Saat cutover P1, API publik membaca pointer ke salinan yang dipublikasikan. Menyimpan perubahan membuat revisi baru. Mengajukan membekukannya; meminta revisi membuka salinan kerja baru. Approve dan publish adalah aksi terpisah. Publish mengunci profil dan mengganti pointer dalam satu transaksi. Unpublish mencabut pointer dengan alasan, tanpa menghapus histori. Kombinasi versi, idempotency key dan transaksi mencegah lost update serta publish ganda.

## Izin

| Kemampuan | Aga | Benito | TU/Sekolah | Petugas LPK |
| --- | --- | --- | --- | --- |
| Login Filament internal | Ya | Ya | Tidak | Tidak |
| Undang, ulangi, cabut assignment staf | Ya | Ya, hanya role dan scope staf | Tidak | Tidak |
| Kelola role sistem/diagnostik, hapus pengecualian, Harvy | Ya, tindakan terpisah dan diaudit | Tidak | Tidak | Tidak |
| Baca/ubah draft milik scope | Review MPK | Review MPK | Hanya assignment sekolah aktif | Hanya assignment LPK aktif |
| Lihat progres sekolah di bawah LPK | Ya | Ya | Sekolah sendiri | Ringkasan status, tanpa membuka draft |
| Submit | Tidak atas nama petugas pada MVP | Tidak atas nama petugas pada MVP | Sekolah yang di-assign | LPK yang di-assign |
| Minta revisi, approve, publish | Ya | Ya, capability editorial eksplisit | Tidak | Tidak |

Akun staf tidak memakai users.role untuk scope. Satu orang dapat memiliki beberapa assignment eksplisit. Hubungan induk-anak dan kecocokan domain email tidak mewariskan grant. Tolak grant kepada akun sistem/legacy dan self-grant dari menu ini. Otorisasi Scope memerlukan session guard staf, state active, subtype tepat dan FK scope yang sama. Mutation mengulang pemeriksaan dengan membership yang dikunci, dalam transaksi yang sama. Revokasi efektif pada request berikutnya, termasuk sesi lama, dan mencabut undangan tertunda.

## Kontrak HTTP usulan

Next.js menjadi BFF same-origin. Browser memakai cookie sesi portal opaque, HttpOnly, Secure, SameSite=Lax, host-only dan tanpa data sesi di localStorage. FE meneruskan credential sesi Laravel hanya lewat penyimpanan/komunikasi server-side. Guard staf terpisah dari Filament. Rotate session pada login/accept/logout. Validasi Origin dan CSRF pada operasi tulis, jangan meneruskan cookie Filament. Semua API privat memakai Cache-Control: no-store. Simpan rahasia sesi hanya server-side dan scope-kan aksesnya.

| Endpoint Laravel di belakang BFF | Kontrak |
| --- | --- |
| POST /api/staff/session, DELETE /api/staff/session | Login/logout staf, throttle dan response gagal seragam. |
| POST /api/staff/invitations/accept | Token di body POST, password dipilih penerima untuk akun baru. Digest satu kali pakai + expiry + rate limit. Untuk penambahan scope akun existing wajib login ke akun yang sama, tanpa reset password. |
| GET /api/staff/me/scopes | Daftar assignment aktif dengan scope, label lembaga, role dan status. |
| GET /api/staff/scopes/{kind}/{id}/profile | Identitas kanonis read-only, draft sendiri, status review, dan versi publik. Privasi bergantung pada assignment yang dievaluasi oleh Laravel. |
| PATCH /api/staff/scopes/{kind}/{id}/draft | Body hanya field berjalan yang di-allowlist, dengan If-Match versi draft. 412 untuk versi basi, 422 untuk field tidak valid, 404 untuk scope tak terjangkau, 401 tanpa sesi. |
| POST /api/staff/scopes/{kind}/{id}/submit | Payload menyertakan versi dan idempotency key. Validasi kelengkapan ketika submit. 409 jika transisi tak berlaku. |
| GET /api/staff/scopes/{kind}/{id}/revisions, GET /api/staff/scopes/{kind}/{id}/preview | Histori dan preview privat, tanpa URL publik atau cache. |
| GET /api/staff/scopes/foundation/{id}/schools/statuses | Hanya id aman, label dan status progres sekolah terhubung. Tidak membuka kontak privat, draft atau hak edit sekolah. |

Field MVP yang dapat diisi meliputi kontak institusi, alamat dan koordinat, deskripsi, program, fasilitas, dan media yang lolos validasi tipe/ukuran. NPSN, kode, kepemilikan, pemetaan LPK, data bank, snapshot, role dan status publikasi berada di luar payload yang boleh diubah. Beri validasi sintaks/range, teks terkontrol, proses media privat sampai publish, serta sanitasi sebelum render. Browser atau API tidak boleh menerima URL media privat yang dapat ditebak.

Undangan memakai token acak kriptografis dengan digest saat disimpan. Tautan email menggunakan fragment atau langkah copy-paste agar kode tidak masuk query log, dan komponen penerima tidak menjalankan analytics. Jalur manual hanya memakai terminal operator privat tanpa perekaman dan handoff ke penerima terverifikasi. Aktivasi manual tidak menandai email terverifikasi. Filament dapat membuat undangan pending dan menunjukkan status pengiriman, tanpa menampilkan token. Penerimaan harus atomik, terikat ke membership dan identitas, serta sekali pakai.

## Filament Petugas & Undangan

Daftar dengan filter lembaga, kategori scope, status dan tanggal. Aksi Undang Petugas memilih lembaga dari registri dan memasukkan nama/alamat personal. Ringkasan konfirmasi menampilkan scope, role yang terkunci oleh tipe lembaga, kanal, dan masa berlaku. Detail memperlihatkan grant aktif, undangan pending, tanggal, dan riwayat. Aksi Kirim Ulang atau Cabut memerlukan konfirmasi dan alasan bila mencabut. Record revoked tetap ada untuk audit. Gunakan policy server-side terpisah dari pengelolaan role panel. Akun panel dan akun percobaan tidak muncul sebagai target staf yang dapat diberi scope.

## Review dan verifikasi bertahap

Gunakan [checklist release dan rollback DEV](p0-dev-release-runbook.md) untuk cutover P0 setelah GO terpisah. P0 masih membaca field kanonis sekolah yang lolos publikasi. Salinan publik immutable baru berlaku setelah mekanisme revisi P1 tersedia.

1. Review P0 BE/FE bersama. P0 mengosongkan 487 sekolah dan 70 LPK dari tampilan publik berdasar data DEV audit. Sumber UI memerlukan header X-EMPK-Publication strict-v1 agar BE lama tidak diterima selama transisi. Registri tetap tersedia bagi operator yang berwenang.
2. Validasi skema, FK, versi, throttle, alur undangan dan keputusan sesi. Tes integrasi staf harus mengirim request nyata untuk kasus lintas sekolah/LPK, scope tidak aktif/dicabut, body scope palsu, token replay/kedaluwarsa, panel login ditolak, profile revision stale, dan publish otomatis ditolak. Uji satu akun dengan dua assignment independen. Cakupan kriteria ini menjadi syarat sebelum pilot.
3. Pilot satu sekolah dan satu LPK secara terpisah. Verifikasi publik tetap kosong sampai review/publish, dan perubahan setelah publish hanya menjadi draft baru. Audit field dan hapus cache sebelum aktivasi. Persetujuan terpisah diperlukan untuk setiap perubahan environment atau data.
