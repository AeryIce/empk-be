# Artefak pemulihan direktori publik Railway DEV

Dokumen persiapan dan review. Langkah deployment di bawah menunggu GO Aga terpisah. [Instruksi pemulihan](https://github.com/AeryIce/empk-be/pull/14#issuecomment-7209369385)

## Isi dan bukti artefak

- Base backend terdeploy yang disetujui adalah 1a503a2984fbf46210e55f94ae750372079f3dff. Branch review berasal langsung dari commit tersebut.
- Dockerfile tetap memakai PHP 8.3 CLI dan menjalankan server.php di port layanan dengan document root public. Guard berjalan sebelum penanganan file statis dan Laravel pada origin yang sama.
- Semua metode pada /api/schools dan /api/foundations beserta subpath mengembalikan HTTP 503 dengan Cache-Control: no-store, private. HEAD tanpa body. Respons biasa hanya berisi JSON generik. Guard mencakup alias index.php, variasi encoding, pemisah, huruf besar, segmen titik, dan permintaan bersyarat. Rute lain memakai alur router lama.
- Tes lokal memakai PHP 8.3 dengan mbstring, intl, pdo_sqlite dan openssl. Jalankan suite PHPUnit dengan DB_CONNECTION=sqlite dan DB_DATABASE=:memory: dalam checkout bersih. Untuk smoke gunakan Node.js 22 dan jalankan node tests/Smoke/dev-recovery.mjs. Di Windows, EMPK_SMOKE_PHP dapat menunjuk php.exe dan EMPK_SMOKE_PHP_EXT_DIR ke folder ekstensi. Script hanya memakai loopback dan fixture SQLite sementara; dua akun sintetis menguji login Filament melalui Livewire, CSRF, serta kelanjutan sesi setelah guard menolak permintaan.
- Simpan full commit SHA dari review dan SHA-256 arsip sumber bersama hasil tes. Arsip berisi file tracked dari git archive, termasuk server.php dan Dockerfile. File env lokal, vendor lokal, dump DB DEV, dan arsip pengguna tidak ikut. Build image Docker dan penerapan Railway DEV adalah verifikasi tersendiri. Status sehat dan tersedianya kandidat pada riwayat rollback baru dapat dibuktikan sesudah deployment disetujui.

## Bukti CI Docker

Workflow DEV recovery Docker verification hanya berjalan untuk PR #15 dari branch review yang sama. Job mengambil exact PR head, membuat arsip Git, mengecek commit yang tertanam di tar, dan membangun Dockerfile dari arsip itu. Tes memakai CMD bawaan image dan HTTP loopback dari dalam container dengan network none, tanpa port terpublikasi. Laravel memakai key sementara dan SQLite fixture yang dibuat di container. Image tidak dipush ke registry.

Sebelum memakai bukti CI untuk review, unduh dev-recovery-docker-evidence-<SHA> dari run yang sukses untuk head PR yang sama. Dari direktori hasil ekstraksi, jalankan sha256sum -c SHA256SUMS lalu git get-tar-commit-id < guard-source.tar. Cocokkan SHA commit dengan provenance.txt dan head PR. Bukti mengikat arsip, Dockerfile, hash server.php di image, ID image, versi PHP, CMD, dan hasil smoke. CI Docker tidak membuktikan builder/start command efektif di Railway, kesehatan deployment DEV, atau ketersediaan rollback Railway.

## Target tetap

| Resource | ID |
| --- | --- |
| reliable-luck | 77e47620-6ea9-4cbf-9882-2bb572bc697c |
| development | 69ac85c2-4427-4e36-a403-10ad1d07536e |
| empk-be | 86562782-e249-40ee-9ddc-f2e726ae100a |

Origin DEV yang harus diverifikasi adalah https://empk-be-development.up.railway.app. Health ada di /up dan /healthz. Gunakan hanya origin DEV dan alias DEV yang sudah terverifikasi.

## Urutan dengan persetujuan terpisah

1. **Gate guard.** Aga menyetujui full SHA guard, waktu cutover, dan penanggung jawab. Verifikasi Docker build, exact project/environment/service melalui metadata CLI, deployment aktif, branch tracking, start command PHP yang menjalankan server.php, dan ketiadaan perubahan staged yang akan ikut diterapkan. Jangan menerapkan atau membuang marker staged lama. Hindari race dengan auto-deploy dev. Catat jalur cache dan cara purge tanpa menyentuh production.
2. **Siapkan unggahan.** Buat arsip dari Git SHA yang disetujui dengan git archive, hitung SHA-256, lalu ekstrak ke direktori bersih tanpa .git atau env lokal. Cocokkan server.php dan Dockerfile dengan Git. Unggah root arsip ke layanan DEV yang sama dengan Railway CLI. Parameter message mengikat SHA ke deployment bila unggahan CLI tidak menampilkan commit Git pada metadata.
3. **Verifikasi guard dari origin langsung.** Setelah status SUCCESS, catat deployment ID, waktu, SHA arsip dan identitas build. Pastikan /up dan /healthz HTTP 200 segera. Periksa daftar dan detail lama Sekolah/LPK dengan GET, HEAD, permintaan conditional, dan satu variasi encoded dari HTTP probe lokal. Path asli dan cache-buster harus sama-sama 503 + no-store, private tanpa 304, redirect, cookie baru, atau data profil. Untuk HEAD body kosong. Jika ada cache API lama, purge list/detail dan periksa kembali header Age/indikator cache bila tersedia. Header no-store tidak otomatis membersihkan objek lama.
4. **UAT guard.** Pastikan GET /admin/login dan /admin/activate sehat. Aga dan Benito menguji login masing-masing dari sesi baru, sesi yang masih berlaku, daftar Filament, dan akses diagnostik sesuai role tanpa membagikan kredensial. Cek /api/berita list serta detail dari artikel publik yang sudah tersedia. Tidak ada tindakan mutasi maupun penerbitan kode. Hentikan cutover jika guard atau UAT gagal.
5. **Simpan titik pemulihan.** Catat deployment guard yang lulus sebagai satu-satunya fallback BE untuk P0. Pastikan deployment itu terdaftar, dapat dipilih untuk rollback sesuai retensi, dan arsip yang sama siap diunggah ulang. Bukti ini **belum diuji di Railway DEV**.
6. **Gate P0 terpisah.** Setelah guard dan UAT lulus, terapkan FE aman terlebih dulu dari PR #32. FE harus menampilkan kondisi gagal/tertutup tanpa data demo saat guard menjawab 503. Berikutnya terapkan BE P0 dari PR #14 sesudah approval. Purge cache terkait dan verifikasi origin serta FE memberi daftar kosong dan detail 404 sesuai kebijakan. Pemeriksaan tersendiri juga mencakup halaman yang merujuk data dan sitemap.

Contoh bentuk perintah unggah untuk langkah 2 setelah GO. Isi payload dengan root hasil ekstraksi dan SHA dengan full commit yang disetujui, lalu jalankan dari konteks CLI DEV yang sudah diverifikasi.

~~~powershell
railway up $payload --path-as-root --project 77e47620-6ea9-4cbf-9882-2bb572bc697c --environment 69ac85c2-4427-4e36-a403-10ad1d07536e --service 86562782-e249-40ee-9ddc-f2e726ae100a --message "DEV recovery guard $guardSha"
~~~

[Dokumentasi unggahan Railway](https://docs.railway.com/cli/up)

## Jika terjadi kegagalan

- Jika guard gagal sebelum dinyatakan sehat, hentikan FE dan BE P0. Origin lama mungkin masih melayani data. Laporkan status dan eskalasikan ke Aga agar perbaikan atau containment origin langsung diputuskan. Guard yang belum lulus UAT belum layak menjadi fallback.
- Jika FE gagal setelah guard sehat, pertahankan deployment guard dan tahan P0. Pulihkan FE ke revisi fail-closed atau maintenance yang sudah ditinjau. Hindari revisi FE yang memuat fallback demo.
- Jika BE P0 gagal, pilih ID deployment guard yang telah lulus atau unggah ulang arsip persis yang sama pada layanan DEV. Rollback dashboard dapat memulihkan variabel historis, sehingga pilihan itu memerlukan verifikasi kesetaraan konfigurasi lebih dulu. Gunakan unggahan ulang arsip dengan konfigurasi DEV berjalan bila kesetaraan tidak dapat dibuktikan. Jangan memilih deployment baseline 1a503a2 yang masih membuka API lama.
- Setelah pemulihan, ulangi health, origin GET/HEAD, conditional, header cache, artikel, dan UAT kedua role. Catat deployment ID pemulihan. Tidak ada migrasi balik, restore DB, perubahan role atau variabel sebagai langkah otomatis.

[Dokumentasi aksi deployment dan retensi Railway](https://docs.railway.com/deployments/deployment-actions)
