# Akses Filament eMPK

Issue #9 membatasi `/admin` untuk akun aktif dengan role `super_moderator` (Aga) atau `super_admin` (Benito). Kedua role dapat mengelola sekolah, yayasan/LPK, dan berita melalui Filament. Diagnostik sistem memerlukan SuperModerator. Perubahan akses akun dilakukan oleh operator yang memiliki akses shell terotorisasi dan persetujuan Aga. Panel belum memiliki halaman pengelolaan akun atau konfigurasi sistem.

## Audit Railway DEV

- Layanan `empk-be` di `development` terhubung ke branch `dev` dan menggunakan PostgreSQL. Inventaris pada 24 November 2026 dijalankan dengan `BEGIN READ ONLY` dan `ROLLBACK`. Ada dua akun, nol email bertanda terverifikasi, dan nol akun demo seeder. Tabel `users` belum memiliki kolom role dan migrasi terakhir yang tercatat adalah `2026_01_12_065938_create_news_posts_table`.
- `/admin/login` mengembalikan HTTP 200 melalui HTTPS dengan cookie sesi `Secure`, `HttpOnly`, dan `SameSite=Lax`. Konfigurasi DEV menunjukkan debug nonaktif dan cache database atau Redis. Mailer pengiriman belum dikonfigurasi, sehingga pengiriman kode nyata masih perlu disiapkan dan diuji setelah review.
- Saat kode baru aktif, dua akun lama akan ditolak sampai operator memberikan akses ke akun yang telah disetujui. Migrasi menambahkan kolom nullable dan tidak mengisi role otomatis.
- Pembaruan Filament dalam seri 3.3 mencakup perbaikan untuk halaman autentikasi dan RichEditor. Build menerbitkan aset Filament dari dependency yang dikunci. Audit Composer atas lockfile hasil pembaruan tidak melaporkan advisory.

## UAT dan penerapan DEV

1. Tinjau PR dengan Aga. Tentukan apakah tiap orang menggunakan akun baru atau akun lama yang identitas, kepemilikan email, dan akses login-nya sudah dikonfirmasi melalui kanal privat. Jadwalkan penerapan DEV bersama operator agar operator dapat segera menerapkan akses yang disetujui saat kode aktif.
2. Periksa target Railway `development` dan database-nya. Verifikasi `APP_URL` HTTPS, `APP_DEBUG=false`, cookie sesi aman, cache bersama, dan mailer pengiriman seperti SMTP yang telah diuji ke kotak surat DEV yang dikendalikan. Gunakan transport pengiriman langsung. Mode `log`, `array`, dan failover yang dapat mencatat isi pesan ditolak untuk penerbitan kode di Railway.
3. Sesudah persetujuan DEV, jalankan `php artisan migrate:status`, tinjau SQL dengan `php artisan migrate --pretend`, lalu jalankan `php artisan migrate --force` apabila yang pending hanya migrasi yang disetujui. Catat batch dan revisi deployment. Setelah deploy, periksa bahwa `/admin/login` dan `/admin/activate` merespons melalui HTTPS.
4. Untuk akun baru, gunakan perintah pertama di bawah dengan email yang telah dikonfirmasi. Jalankan perintah kedua hanya bila Aga menyetujui pemakaian akun lama dan operator telah mencocokkan ID dan email secara privat. Setiap eksekusi meminta persetujuan interaktif dan mencocokkan target dengan nama environment Railway. Untuk Aga, gunakan role `super_moderator` dan nama `Aga` saat membuat akun baru.

```sh
php artisan empk:provision-panel-user '<alamat-email-terverifikasi>' super_admin --target=development --name=Benito
php artisan empk:provision-panel-user '<alamat-email-terverifikasi>' super_admin --target=development --existing-user-id='<id-terverifikasi>'
```

5. Akun baru tetap nonaktif sampai pemilik memasukkan kode dari email di `/admin/activate` dan menetapkan kata sandi. Kode berlaku 60 menit dan hanya bisa dipakai sekali. Jika kode kedaluwarsa atau pengiriman gagal, ulangi perintah awal dengan `--resend` setelah jeda minimal satu menit dan persetujuan baru. Pengulangan biasa tidak mengirim email tambahan. Untuk pemberian akses akun lama, perintah mencocokkan ID serta email dan mempertahankan kata sandi, token ingat saya, dan status verifikasi email saat ini. Perintah menolak perubahan role pada akun yang sudah memiliki role lain.
6. Minta pemilik akun melakukan UAT dengan kredensial mereka sendiri. Periksa login Aga dan Benito, akses diagnostik khusus Aga, serta operasi buat, baca, ubah, dan hapus pada data uji operasional. Periksa logout, penolakan akun tanpa role, dan kedaluwarsa kode. Uji throttling dengan akun uji terpisah dari sesi pemilik akun. Cocokkan hasil dengan log `panel.login.*`, `panel.logout`, `panel.provision.*`, dan `panel.activation.*` melalui ID internal akun atau request ID. Simpan bukti UAT tanpa email, kode, kata sandi, atau cookie.

## Jalur laporan MPK

Benito dapat mencari dan memfilter data di daftar **Sekolah**, **Yayasan**, dan **Berita**. Gunakan tabel tersebut sebagai sumber rekap manual, termasuk senarai sekolah per yayasan dan status publikasi yang dibutuhkan laporan. Angka dan grafik demo pada dashboard serta berkas CSV seeder adalah data contoh. Fitur Filament yang ada belum menyediakan ekspor atau generator laporan.

## Pemulihan

- Jika satu akses perlu dicabut, operator yang disetujui dapat mengosongkan `role` dan `panel_activated_at` berdasarkan ID akun yang telah dicocokkan, menghapus kode aktivasi yang masih tersimpan, serta mencabut sesi dan token ingat saya akun tersebut. Gunakan mekanisme pencabutan yang sesuai dengan driver sesi environment. Permintaan Filament berikutnya menolak akun yang aksesnya dicabut. Catat perubahan secara terbatas tanpa menyertakan kredensial.
- Jika migrasi sudah berhasil dan kode perlu ditarik, prioritaskan memperbaiki atau menarik kode sambil membatasi akses `/admin` selama pemulihan. Versi lama mengizinkan semua akun yang berhasil login. Bila kolom perlu dihapus, pastikan hanya migrasi ini yang dipilih, simpan catatan assignment role yang disetujui, dan tinjau `php artisan migrate:rollback --path=database/migrations/2026_11_24_000000_add_panel_access_to_users_table.php --pretend` sebelum mengeksekusi rollback migrasi itu. Rollback menghapus kolom role dan aktivasi, sementara baris akun serta kata sandi tetap tersimpan.
- Deployment, migrasi, pemberian akses akun, dan pengiriman kredensial production memerlukan keputusan terpisah dari Aga setelah UAT DEV diterima.
