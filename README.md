# SatSet

SatSet adalah antrean virtual untuk restoran, cafe, bazaar, dan tenant makanan. Customer memindai QR outlet, mendaftar dengan email, dan mendapat email saat antreannya maju, saat giliran tiba, atau saat antrean dibatalkan. Tidak perlu login.

Ada dua mode per outlet:

- **Antrean meja** (`seating`): customer mengisi jumlah tamu dan email. Antrean untuk kursi.
- **Ambil di counter** (`pickup`): customer hanya mengisi email. Antrean untuk pesanan yang diambil di counter, tanpa kursi.

## Stack

- Laravel 13, Blade, HTML, CSS, JavaScript biasa
- Database: SQLite untuk development dan test, MySQL bisa dipakai untuk development utama
- Email: kanal mail bawaan Laravel
- Antrean (queue): `database`, dijalankan dengan `queue:work`, atau `sync` untuk demo

## Requirements

- PHP 8.3 atau lebih baru, dengan `pdo_sqlite` (test) atau `pdo_mysql`, `fileinfo`
- Composer
- SMTP untuk mengirim email (lihat bagian Email)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Untuk SQLite, set di `.env`:

```env
DB_CONNECTION=sqlite
```

Lalu buat file database dan jalankan migration:

```bash
touch database/database.sqlite   # Windows PowerShell: New-Item database\database.sqlite -Force
php artisan migrate
php artisan db:seed --class=RestaurantSeeder
```

Untuk MySQL, atur `DB_CONNECTION=mysql` dan kredensial database di `.env`, lalu jalankan `php artisan migrate` dan seeder yang sama.

Seeder membuat tiga outlet: Ayam Bakar Nusantara, Ayam Geprek Mantap, dan Rice Bowl & More.

Akun admin tidak dibuat otomatis. Buat dengan perintah ini, lalu isi password minimal 12 karakter saat diminta:

```bash
php artisan satset:admin email-anda@contoh.com
```

## Email

Isi `MAIL_*` di `.env`. Untuk demo, penyedia email dengan sandbox seperti Mailtrap cukup dan emailnya bisa dilihat di dashboard tanpa risiko masuk spam. Untuk produksi, pakai domain sendiri dengan SPF, DKIM, dan DMARC, atau penyedia email transaksional.

```env
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=...
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="hello@contoh.com"
MAIL_FROM_NAME="SatSet"

EMAIL_ALERTS_ENABLED=true
EMAIL_ALERTS_MAX_ACTIVE_PER_EMAIL=3
```

`EMAIL_ALERTS_ENABLED=false` menghentikan pengiriman tanpa mengganggu antrean. Setiap email dikirim lewat queue, dengan percobaan ulang saat gagal.

## Menjalankan

```bash
php artisan serve
```

Buka `http://127.0.0.1:8000`.

Untuk operasional, dua proses tambahan diperlukan:

- **Queue worker:** email dikirim lewat queue. Jalankan `php artisan queue:work`. Untuk demo, `QUEUE_CONNECTION=sync` cukup dan tidak perlu worker.
- **Scheduler:** membersihkan failed jobs setiap hari. Jalankan `php artisan schedule:run` lewat cron atau Task Scheduler setiap menit.

## Halaman

Customer:

- `/` daftar restoran
- `/r/{slug}` halaman outlet, titik masuk dari QR
- `/r/{slug}/join` form pendaftaran dengan email (dan jumlah tamu untuk mode meja)
- `/queue/{public_token}` tiket customer, diperbarui otomatis
- `/restaurants/{id}/recommendations` rekomendasi saat antrean panjang

Admin, setelah login di `/admin/login`:

- `/admin/dashboard`
- `/admin/queues` daftar semua antrean, kontak yang disamarkan, dan jumlah email terkirim
- `/admin/restaurants` kelola outlet, termasuk mode antrean dan status pendaftaran
- `/admin/restaurants/{id}/queue` panggil berikutnya, selesai, lewati, dan batalkan
- `/admin/qr` QR code untuk setiap outlet

## Privasi

- Email dan nomor telepon dihapus dari tiket saat tiket selesai, dilewati, atau dibatalkan.
- Di admin, kontak tampil disamarkan, misalnya `t***@contoh.com`.
- Failed jobs dibersihkan setiap hari, karena payload-nya bisa memuat email.
- Customer tidak perlu login, dan halaman tiket hanya bisa dibuka lewat `public_token`.

## Testing

```bash
php artisan test
```

Test mencakup alur pendaftaran, mode meja dan ambil, batas antrean per email, retensi data, pengiriman email, kegagalan SMTP, dan test chaos. Test chaos menjalankan operasi acak dengan SMTP yang gagal secara terkontrol, lalu memeriksa bahwa setiap email terkirim tepat sekali.

## Catatan

- Project ini tidak memakai React, Vue, Redis, Docker, atau WebSocket. Halaman tiket memperbarui diri dengan polling.
- Tabel `whatsapp_logs` dan migration WhatsApp dari riwayat sebelumnya tidak dihapus. Kode WhatsApp sudah dihapus dari branch ini.
- Belum ada role selain admin. Semua akun admin memiliki akses penuh.
