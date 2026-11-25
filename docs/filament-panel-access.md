# Akses Filament eMPK

Issue #9 membatasi `/admin` untuk akun aktif dengan role `super_moderator` (Aga) atau `super_admin` (Benito). Kedua role dapat mengelola sekolah, yayasan/LPK, dan berita melalui Filament. Diagnostik sistem memerlukan SuperModerator. Perubahan akses akun dilakukan oleh operator yang memiliki akses shell terotorisasi dan persetujuan Aga. Panel belum memiliki halaman pengelolaan akun atau konfigurasi sistem.

## Audit Railway DEV

- Layanan `empk-be` di `development` terhubung ke branch `dev` dan menggunakan PostgreSQL. Inventaris pada 24 November 2026 dijalankan dengan `BEGIN READ ONLY` dan `ROLLBACK`. Ada dua akun, nol email bertanda terverifikasi, dan nol akun demo seeder. Tabel `users` belum memiliki kolom role dan migrasi terakhir yang tercatat adalah `2026_01_12_065938_create_news_posts_table`.
- `/admin/login` mengembalikan HTTP 200 melalui HTTPS dengan cookie sesi `Secure`, `HttpOnly`, dan `SameSite=Lax`. Konfigurasi DEV menunjukkan debug nonaktif dan cache database atau Redis. Aktivasi manual untuk akun baru Benito tersedia khusus Railway `development` sebagai jalur yang disetujui selama SMTP belum siap.
- Saat kode baru aktif, dua akun lama akan ditolak sampai operator memberikan akses ke akun yang telah disetujui. Migrasi menambahkan kolom nullable dan tidak mengisi role otomatis.
- Pembaruan Filament dalam seri 3.3 mencakup perbaikan untuk halaman autentikasi dan RichEditor. Build menerbitkan aset Filament dari dependency yang dikunci. Audit Composer atas lockfile hasil pembaruan tidak melaporkan advisory.

## UAT dan penerapan DEV

1. Tinjau PR dan dapatkan persetujuan eksekusi Aga. Cocokkan akun Aga dengan ID `1` secara privat, konfirmasi alamat milik Benito, dan catat bahwa ID `2` adalah akun percobaan tanpa akses. Verifikasi target `development`, cadangan PostgreSQL DEV, prosedur pemulihan, `APP_URL` HTTPS, dan cookie sesi aman. Snapshot manual `b024606a-8704-4fbc-b16f-0fd13154cca3` telah terdaftar di Railway. Restore snapshot tersebut belum diuji.
2. Dengan revisi lama masih melayani trafik, gunakan checkout PR dengan konfigurasi Railway DEV. Periksa `php artisan migrate:status`, tinjau SQL melalui `php artisan migrate --pretend`, lalu jalankan hanya migrasi penambahan akses panel yang disetujui. Berikan role `super_moderator` kepada Aga dengan `--existing-user-id=1` setelah mencocokkan ID dan alamat. Jalur ini mempertahankan kredensial. Verifikasi ID `1` aktif sebagai SuperModerator dan ID `2` tetap tanpa role. Hentikan penerapan bila identitas, target, backup, migrasi, atau verifikasi role gagal.
3. Setelah persetujuan deploy, periksa `/admin/login` dan `/admin/activate` melalui HTTPS. Aga menguji login dari sesi baru dan membuka diagnostik. Lanjutkan ke Benito setelah UAT Aga lulus.
4. Buka shell Bash interaktif langsung ke layanan `empk-be` di Railway `development` dengan terminal yang tidak direkam. Hindari tee, redirection, perekaman sesi, CI, dan command runner yang menyimpan output. Masukkan alamat yang dikonfirmasi lewat prompt tersembunyi. Variabel berikut hanya berlaku pada satu proses dan tidak perlu disimpan sebagai variabel layanan Railway.

```sh
set +x
read -rsp 'Alamat Benito yang sudah diverifikasi: ' approved_email
printf '\n'
EMPK_DEV_MANUAL_ACTIVATION_EMAIL="$approved_email" php artisan empk:provision-panel-user @approved-dev super_admin --target=development --name=Benito --manual-activation
unset approved_email
```

5. Konfirmasi kepemilikan alamat dan privasi terminal ketika diminta. Kirim kode yang ditampilkan sekali kepada Benito melalui kanal privat yang disetujui. Jangan salin kode ke tiket, GitHub, catatan terminal, atau bukti UAT. Benito memasukkan email dan kode di `/admin/activate`, lalu menetapkan kata sandinya sendiri. Kode berlaku 60 menit. Jika perlu diganti, ulangi dengan `--resend` setelah minimal satu menit dan konfirmasi baru. Penerbitan ulang membatalkan kode sebelumnya. Pengulangan biasa tidak menampilkan kode lagi.
6. Uji login dan logout Benito serta akses baca dan ubah data operasional yang disetujui. Pastikan diagnostik tetap khusus Aga dan ID `2` ditolak. Gunakan ID internal akun atau request ID saat mencocokkan bukti dengan log `panel.login.*`, `panel.logout`, `panel.provision.*`, dan `panel.activation.*`. Simpan bukti tanpa email, kode, kata sandi, atau cookie. Pendaftaran staf selanjutnya memakai alur email setelah transport pengiriman yang disetujui teruji.

## Jalur laporan MPK

Benito dapat mencari dan memfilter data di daftar **Sekolah**, **Yayasan**, dan **Berita**. Gunakan tabel tersebut sebagai sumber rekap manual, termasuk senarai sekolah per yayasan dan status publikasi yang dibutuhkan laporan. Angka dan grafik demo pada dashboard serta berkas CSV seeder adalah data contoh. Fitur Filament yang ada belum menyediakan ekspor atau generator laporan.

## Pemulihan

- Jika satu akses perlu dicabut, operator yang disetujui dapat mengosongkan `role` dan `panel_activated_at` berdasarkan ID akun yang telah dicocokkan, menghapus kode aktivasi yang masih tersimpan, serta mencabut sesi dan token ingat saya akun tersebut. Gunakan mekanisme pencabutan yang sesuai dengan driver sesi environment. Permintaan Filament berikutnya menolak akun yang aksesnya dicabut. Catat perubahan secara terbatas tanpa menyertakan kredensial.
- Jika migrasi sudah berhasil dan kode perlu ditarik, prioritaskan memperbaiki atau menarik kode sambil membatasi akses `/admin` selama pemulihan. Versi lama mengizinkan semua akun yang berhasil login. Bila kolom perlu dihapus, pastikan hanya migrasi ini yang dipilih, simpan catatan assignment role yang disetujui, dan tinjau `php artisan migrate:rollback --path=database/migrations/2026_11_24_000000_add_panel_access_to_users_table.php --pretend` sebelum mengeksekusi rollback migrasi itu. Rollback menghapus kolom role dan aktivasi, sementara baris akun serta kata sandi tetap tersimpan.
- Deployment, migrasi, pemberian akses akun, dan pengiriman kredensial production memerlukan keputusan terpisah dari Aga setelah UAT DEV diterima.
