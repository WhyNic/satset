# SatSet

SatSet adalah web app antrean virtual untuk bazaar, food festival, dan restoran. Customer dapat memilih restoran, mengambil tiket digital tanpa login, melihat Active Queue, menerima Web Push Notification saat antrean mendekat, dan mendapat rekomendasi restoran alternatif saat antrean terlalu panjang.

## Stack

- Backend: Laravel 13
- Frontend: Laravel Blade, HTML, CSS, vanilla JavaScript
- Database: MySQL untuk development utama, SQLite untuk automated test
- Push notification: `laravel-notification-channels/webpush`
- Customer notification runtime: Service Worker, Web Push API, Web App Manifest

## Requirements

- PHP 8.3 atau lebih baru
- Composer
- MySQL atau MariaDB
- PHP extension `ext-curl` untuk pengiriman Web Push asli
- Browser secure context: `localhost` atau `127.0.0.1` untuk development

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
```

## Database Setup

Buat database MySQL:

```sql
CREATE DATABASE satset CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Pastikan `.env` memakai koneksi MySQL:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=satset
DB_USERNAME=root
DB_PASSWORD=
```

Lalu jalankan migration dan seeder:

```bash
php artisan migrate --seed
```

Seeder membuat:

- Restoran A: Ayam Bakar Nusantara
- Restoran B: Ayam Geprek Mantap
- Restoran C: Rice Bowl & More
- Sample queue agar dashboard terlihat hidup
- Admin: `admin@satset.local` / `password`

## Web Push Setup

SatSet memakai package:

```bash
composer require laravel-notification-channels/webpush
php artisan vendor:publish --provider="NotificationChannels\WebPush\WebPushServiceProvider" --tag="config"
php artisan vendor:publish --provider="NotificationChannels\WebPush\WebPushServiceProvider" --tag="migrations"
php artisan webpush:vapid
```

Pastikan `.env` memiliki VAPID keys. Private key jangan di-hardcode ke frontend.

```env
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=
VAPID_SUBJECT=http://localhost
```

Untuk local development, `VAPID_SUBJECT=http://localhost` valid. Browser tetap dapat membuka app dari `http://127.0.0.1:8000`.

## Web Push Architecture

Customer tidak login dan tidak mengisi nomor telepon. Subscription browser dihubungkan ke `QueueTicket` lewat trait `HasPushSubscriptions` dari package webpush.

Flow customer:

1. Customer membuka `/`.
2. Customer memilih restoran.
3. Jika antrean panjang, customer melihat rekomendasi restoran alternatif.
4. Customer klik `Ambil Antrean`.
5. Backend membuat `QueueTicket` dengan `public_token`.
6. Customer diarahkan ke `/queue/{public_token}`.
7. Customer klik `Aktifkan Notifikasi`.
8. Browser meminta permission, mendaftarkan `public/sw.js`, membuat Push subscription, lalu mengirim subscription ke Laravel.
9. Saat admin memanggil antrean berikutnya, backend mengirim Web Push ke ticket yang relevan.

`/queue/{public_token}` tetap menjadi URL publik customer. Database ID tidak diekspos.

## Service Worker And Manifest

File utama:

- `public/sw.js`
- `public/manifest.json`
- `public/icons/icon-192.png`
- `public/icons/icon-512.png`
- `public/icons/badge-72.png`

Service worker menangani:

- `push` event untuk menampilkan notification
- `notificationclick` untuk membuka `/queue/{public_token}`

Manifest membuat SatSet siap dipasang sebagai Home Screen web app, terutama untuk dukungan Web Push di iOS/iPadOS 16.4+.

## Customer Routes

- `GET /`
- `GET /restaurants/{id}`
- `GET /restaurants/{id}/recommendations`
- `GET /restaurants/{id}/queue/new`
- `POST /restaurants/{id}/queue`
- `GET /queue/{ticket:public_token}`
- `GET /queue/{ticket:public_token}/status`
- `POST /queue/{ticket:public_token}/cancel`
- `POST /queue/{ticket:public_token}/push-subscription`
- `DELETE /queue/{ticket:public_token}/push-subscription`

## Admin Routes

- `GET /admin`
- `GET /admin/login`
- `POST /admin/login`
- `POST /admin/logout`
- `GET /admin/dashboard`
- `GET /admin/queues`
- `GET /admin/restaurants`
- `GET /admin/restaurants/create`
- `POST /admin/restaurants`
- `GET /admin/restaurants/{id}/edit`
- `PUT /admin/restaurants/{id}`
- `DELETE /admin/restaurants/{id}`
- `GET /admin/restaurants/{id}/queue`
- `POST /admin/restaurants/{id}/queue/call-next`
- `POST /admin/restaurants/{id}/queue/complete`
- `POST /admin/restaurants/{id}/queue/skip`
- `POST /admin/restaurants/{id}/queue/reset`
- `POST /admin/restaurants/{id}/queue/{ticket}/cancel`

## Notification Triggers

Saat admin menjalankan `Panggil Berikutnya`, SatSet:

- menandai ticket berikutnya sebagai `called`
- mengirim `QueueCalledNotification` jika belum pernah terkirim
- menghitung ulang posisi semua ticket `waiting`
- mengirim `QueueAlmostTurnNotification` saat posisi tinggal 3
- mengirim `QueueAlmostTurnNotification` saat posisi tinggal 1

Timestamp anti-duplikasi:

- `three_ahead_notified_at`
- `one_ahead_notified_at`
- `called_notified_at`

Cancellation menggunakan `QueueCancelledNotification`.

## Localhost Testing

Jalankan:

```bash
php artisan serve
```

Buka:

- Customer home: `http://127.0.0.1:8000`
- Admin login: `http://127.0.0.1:8000/admin/login`

Manual demo:

1. Customer: buka `/`, pilih restoran, klik `Ambil Antrean`.
2. Customer: di Active Queue klik `Aktifkan Notifikasi`, lalu allow permission.
3. Admin: login, buka Queue Management restoran, klik `Panggil Berikutnya` beberapa kali.
4. Customer menerima notification saat tinggal 3 antrean, tinggal 1 antrean, dan saat dipanggil.
5. Klik notification membuka `/queue/{public_token}`.

## Mobile Support

- Android Chrome: Web Push didukung pada secure context.
- Desktop Chrome/Edge: Web Push didukung pada secure context.
- iPhone/iPad: Web Push berlaku untuk Home Screen web apps di iOS/iPadOS 16.4+. Tambahkan SatSet ke Home Screen, lalu minta permission dari app yang sudah terpasang.

Jangan mengasumsikan Safari iPhone biasa bekerja sama seperti Android browser tab.

## Troubleshooting Notification Permission

- Jika browser menampilkan `Notifikasi diblokir`, customer tetap bisa memantau antrean dari Active Queue.
- Jika tombol mengatakan browser belum mendukung push, gunakan Chrome/Edge terbaru.
- Jika permission pernah ditolak, ubah izin site dari browser settings.
- Jika notification tidak terkirim dari server, cek `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`, dan pastikan PHP `ext-curl` aktif.
- Setelah mengubah `.env`, jalankan `php artisan config:clear`.

## Testing

```bash
php artisan test
```

Test mencakup:

- Customer dapat membuat queue ticket tanpa login
- Queue number generated correctly
- Estimated waiting time correctly calculated
- Admin can call next customer
- Cancelled customer tidak lagi masuk active queue
- Recommendation memilih restoran kategori sama dengan waktu lebih pendek
- Push subscription dapat disimpan
- Invalid public token ditolak
- Notification tinggal 3 antrean hanya ditandai/terkirim sekali
- Notification tinggal 1 antrean hanya ditandai/terkirim sekali
- Called notification hanya ditandai/terkirim sekali
- Refresh Active Queue tidak membuat ticket baru

## Database Tables

- `users`
- `restaurants`
- `queue_tickets`
- `push_subscriptions`
- tabel bawaan Laravel untuk sessions, cache, jobs, password reset

## Notes

Project ini tidak memakai React, Next.js, Vue, Redis, Docker, atau WebSocket. Active Queue memakai AJAX polling setiap 7 detik dari halaman tiket digital.
