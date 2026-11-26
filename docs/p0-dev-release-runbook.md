# Checklist release dan rollback P0 DEV

Runbook untuk eksekusi terpisah setelah GO Aga. Cakupan hanya Railway development dan FE DEV. [Keputusan Aga](https://github.com/AeryIce/empk-be/pull/14#issuecomment-7184135590) · [FE #32](https://github.com/AeryIce/empk-fe/pull/32)

## Sebelum window

- [ ] Pin SHA BE/FE yang direview, branch dan environment DEV, serta catat deployment ID aktif dari kedua platform. Periksa hasil tes dan status health. Konfirmasi URL langsung Railway DEV, URL FE DEV dan alias/cache yang dapat menyajikan direktori.
- [ ] Tetapkan operator dan durasi window FE → BE. Siapkan kontrol fail-closed yang berlaku pada URL Railway langsung dan semua alias, atau kandidat build pemulihan yang mempertahankan proteksi P0. Bila harus menutup sementara, kembalikan HTTP 503 dengan Cache-Control: no-store pada empat jalur publik, sambil membiarkan health dan akses internal berjalan. Uji kontrol yang dipilih sebelum release. Kemampuan ini belum diverifikasi di DEV.
- [ ] Dari Railway DEV yang sudah diidentifikasi, catat baseline agregat dan fingerprint internal untuk seluruh nilai record sekolah/LPK beserta ID serta relasinya. Gunakan transaksi read-only dan akses operator privat. Simpan hanya hitungan dan bukti kecocokan, tanpa data profil. Audit terakhir mencatat 487 sekolah dan 70 LPK. Jika jumlah atau persetujuan publikasi berbeda dari baseline yang disetujui, hentikan untuk review. Release P0 tidak menyertakan migrasi, seeder, backfill atau perubahan flag.
- [ ] Inventaris jalur cache lama: CDN/proxy API, hostname origin langsung, Vercel/Next.js dan browser. Siapkan purge untuk list/detail, sitemap dan halaman turunannya apabila pernah bercache. Header baru tidak menghapus objek yang sudah tersimpan.
- [ ] Rekam satu slug Sekolah dan satu slug LPK lama yang diketahui pernah bisa diakses. Simpan privat untuk smoke test dan gunakan hanya status, jumlah, dan header pada laporan.

## Eksekusi DEV

1. **Deploy FE aman dahulu** setelah persetujuan terpisah. Pastikan build yang benar menolak respons API lama tanpa marker strict-v1. Periksa direktori, detail, Favorit, beranda, halaman Jakarta, Tanya MPK dan sitemap. Tidak boleh ada fallback data lama. Akhiri window bila FE tidak sehat. API langsung di BE lama masih dapat menjawab data warisan sampai BE P0 aktif atau kontrol fail-closed diterapkan.
2. **Deploy BE P0** segera setelah FE lolos. Konfirmasi SHA aktual dan health /up serta /healthz. Jangan menjalankan Artisan migrasi, seeder, sinkronisasi DAPO atau perubahan profil.
3. **Purge cache relevan** pada kedua lapisan. Ulangi pemeriksaan dari sesi baru dan client tanpa cookie, dengan URL asli yang sama. Tambahkan percobaan cache-busting dan header conditional dari respons lama jika tersedia. Bandingkan hasilnya dengan permintaan URL asli. Periksa Age dan indikator HIT jika dikirim. Periksa langsung origin dan semua alias yang digunakan, bukan hanya proxy FE.

| Target langsung BE DEV | Hasil yang harus terlihat |
| --- | --- |
| GET /api/schools dan GET /api/foundations | HTTP 200, JSON [], X-EMPK-Publication: strict-v1, Cache-Control berisi no-store. |
| GET /api/schools/{slug-lama} dan GET /api/foundations/{slug-lama} | HTTP 404 dan Cache-Control berisi no-store. Berlaku juga untuk slug acak dan permintaan conditional. |
| Permintaan conditional pada daftar dan detail lama | Hasil terbaru tetap 200 kosong atau 404. Respons lama, respons 304, atau cache HIT yang menyajikan data lama menghentikan release. |
| GET /up dan /healthz | HTTP 200. Akses Filament internal tetap tersedia. UAT memakai sesi baru Aga dan Benito. |

4. **Periksa registri internal.** Bandingkan snapshot read-only sebelum/sesudah menggunakan hitungan, fingerprint record dan relasi. Benito harus dapat melihat registri internal dan bekerja pada konten yang diizinkan. Di DEV, periksa bahwa sesi Benito tidak menampilkan aksi hapus, hapus massal, sinkronisasi DAPO atau field internal yang terlarang. Catat status otorisasi hanya jika sudah tersedia di log. Bukti percobaan mutasi dan replay berasal dari tes terisolasi. Catat bukti tanpa alamat email, kredensial, isi profil atau cookie.
5. Hentikan release jika SHA, health, 404/empty, cache, izin atau registri meleset. Lepas kontrol sementara hanya setelah smoke origin, alias dan FE lulus.

## Rollback yang menjaga pembatasan publik

- Jika FE gagal sebelum deploy BE, hentikan rollout. Pertahankan FE fail-closed atau aktifkan halaman maintenance aman. Catat bahwa BE lama masih perlu pembatasan jalur publik sampai perbaikan siap.
- Jika BE gagal atau cache masih menyajikan data lama, blokir keempat jalur publik pada origin/alias dengan kontrol yang sudah diuji. Pertahankan FE aman. Gunakan build BE pengganti yang mempertahankan pembatasan. Revisi lama yang mengekspos registri hanya dapat dipakai sementara di belakang kontrol fail-closed yang melindungi juga URL Railway langsung. Jika kontrol belum terbukti, tetap pada containment dan eskalasikan ke Aga.
- Jika FE gagal sesudah BE P0 aktif, pertahankan proteksi BE dan gunakan revisi FE tanpa fallback atau maintenance. Jangan mengaktifkan kembali FE yang membaca JSON demo.
- Setelah pemulihan, purge ulang, verifikasi GET langsung, health, status cache, dan fingerprint read-only. Tidak ada alasan untuk rollback schema atau restore database sebagai bagian dari P0.

## Batas P0

P0 membaca field kanonis Sekolah setelah flag dan bukti verifikasi terpenuhi. Perubahan luar biasa oleh SuperModerator pada record publik dapat mengubah respons publik saat itu juga. Snapshot publik immutable, review per revisi, aktivasi staf dan assignment portal tetap bagian P1. LPK tetap ditahan hingga persetujuan independennya tersedia.
