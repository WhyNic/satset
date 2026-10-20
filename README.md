# SatSet

SatSet adalah antrean virtual untuk bisnis F&B (restoran, cafe, food stall). Customer **scan QR di outlet**, mendaftar lewat **chatbot WhatsApp**, lalu menerima kabar giliran lewat WhatsApp tanpa harus berdiri menunggu.

```text
Scan QR di outlet → halaman outlet (/r/{slug}) → "Lanjut via WhatsApp"
→ chatbot: jumlah tamu → konfirmasi YA → nomor antrean (mis. A-001)
→ notifikasi: tinggal 3 antrean, tinggal 1 antrean, giliran tiba
```

> - Menjalankan SatSet untuk presentasi atau demo: [docs/PANDUAN-PRESENTASI.md](docs/PANDUAN-PRESENTASI.md)
> - Mencoba seluruh fitur langkah demi langkah: [docs/PANDUAN-UJI-COBA.md](docs/PANDUAN-UJI-COBA.md)

## Daftar isi

1. [Teknologi](#teknologi)
2. [Kebutuhan](#kebutuhan)
3. [Instalasi pertama kali](#instalasi-pertama-kali)
4. [Menjalankan aplikasi](#menjalankan-aplikasi)
5. [Akses dari HP dengan scan QR](#akses-dari-hp-dengan-scan-qr)
6. [Akun admin dan peran](#akun-admin-dan-peran)
7. [Mencoba chatbot tanpa WhatsApp](#mencoba-chatbot-tanpa-whatsapp)
8. [Menghubungkan WhatsApp (Twilio / Meta)](#menghubungkan-whatsapp)
9. [Test otomatis](#test-otomatis)
10. [Masalah umum](#masalah-umum)

## Teknologi

- **Backend:** Laravel 13 (PHP 8.3+)
- **Frontend:** Blade, CSS, dan JavaScript biasa (tanpa build step, file ada di `public/css` dan `public/js`)
- **Database:** MySQL untuk development, SQLite in-memory untuk test
- **WhatsApp:** Twilio atau Meta WhatsApp Cloud API (pilih lewat `.env`)
- **QR code:** `endroid/qr-code`

## Kebutuhan

- PHP **8.3 atau lebih baru** dengan ekstensi `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`
- Composer
- MySQL atau MariaDB (MySQL Server atau XAMPP; **pakai salah satu saja** agar port 3306 tidak bentrok)
- Node.js **tidak** diperlukan

## Instalasi pertama kali

Jalankan perintah berikut dari folder proyek (folder yang berisi file `artisan`).

**1. Instal dependency PHP**

```bash
composer install
```

**2. Buat file `.env` dan app key**

```bash
cp .env.example .env
```

```bash
php artisan key:generate
```

**3. Buat database MySQL** bernama `satset`, lewat terminal MySQL atau phpMyAdmin:

```sql
CREATE DATABASE satset CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**4. Isi koneksi database di `.env`**

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=satset
DB_USERNAME=root
DB_PASSWORD=password_mysql_anda
```

Untuk XAMPP, `DB_PASSWORD` biasanya dikosongkan.

**5. Buat tabel dan data contoh** (3 restoran contoh beserta beberapa antrean)

```bash
php artisan migrate --seed
```

**6. Buat akun pemilik SatSet** (Anda akan diminta mengetik password minimal 12 karakter)

```bash
php artisan satset:admin email@anda.com --name="Nama Anda"
```

## Menjalankan aplikasi

```bash
php artisan serve
```

Biarkan terminal tetap terbuka (tekan **Ctrl + C** untuk berhenti), lalu buka:

| Halaman | Alamat |
|---|---|
| Beranda customer | http://localhost:8000 |
| Halaman outlet (hasil scan QR) | http://localhost:8000/r/{slug-outlet} |
| Admin | http://localhost:8000/admin |

Slug outlet bisa dilihat di **Admin → QR Code**.

Jika WhatsApp sudah diaktifkan, jalankan juga worker di terminal kedua supaya pesan keluar terkirim:

```bash
php artisan queue:work
```

## Akses dari HP dengan scan QR

Cara ini dipakai untuk mencoba alur customer dari HP sungguhan: scan QR, lalu buka halaman outlet dan halaman tiket.

**Syarat:** HP dan laptop terhubung ke **Wi-Fi yang sama**.

**1. Jalankan server untuk jaringan lokal** (menggantikan `php artisan serve`)

```bash
php artisan satset:lan
```

Perintah ini menampilkan alamat laptop di jaringan, misalnya:

```text
Admin QR : http://192.168.1.189:8000/admin/qr
```

**2. Buka alamat tersebut di browser laptop**, bukan `localhost`. Login sebagai admin, lalu klik **Lihat QR** pada outlet.

Karena halaman admin dibuka lewat alamat jaringan, QR otomatis berisi alamat yang bisa dibuka HP (misalnya `http://192.168.1.189:8000/r/...`). File `.env` tidak perlu diubah.

**3. Scan QR dengan kamera HP.** Halaman outlet akan terbuka di HP.

**4. Kalau Windows menampilkan pop-up Firewall** saat server dijalankan, pilih **Allow** untuk jaringan **Private**. Kalau HP tetap tidak bisa membuka halaman, lihat [Masalah umum](#masalah-umum).

> Catatan:
> - Alamat jaringan laptop bisa berubah kalau Wi-Fi tersambung ulang. Jalankan `php artisan satset:lan` lagi untuk melihat alamat terbaru.
> - QR yang memakai alamat jaringan hanya berlaku di Wi-Fi tersebut. Untuk QR yang dicetak dan ditempel di outlet, isi `APP_URL` dengan domain HTTPS (misalnya `https://satsetid.my.id`); QR akan selalu memakai `APP_URL` jika isinya bukan `localhost`.
> - Untuk mengakses dari jaringan mana pun (misalnya data seluler), atau untuk menguji WhatsApp, gunakan tunnel. Lihat [Menghubungkan WhatsApp](#menghubungkan-whatsapp).

## Akun admin dan peran

| Peran | Akses |
|---|---|
| **Pemilik SatSet** | Semua outlet, tambah atau nonaktifkan restoran, kelola akun admin, log WhatsApp |
| **Admin outlet** (kasir) | Hanya outlet yang dipilihkan: kelola antrean, QR, dan pengaturan outlet (kecuali nama, prefix, dan status aktif) |

- Akun pemilik dibuat dengan `php artisan satset:admin`.
- Akun kasir dibuat oleh pemilik di **Admin → Akun admin**. Setiap kasir sebaiknya punya akun sendiri. Satu akun boleh memegang beberapa outlet.
- Kasir yang memegang satu outlet langsung diarahkan ke halaman antrean outletnya setelah login.
- Akun yang dinonaktifkan tidak bisa login dan langsung keluar dari sesi yang sedang berjalan.

## Mencoba chatbot tanpa WhatsApp

Alur chatbot bisa disimulasikan dari terminal tanpa akun WhatsApp. Data akan masuk ke database lokal.

```bash
php artisan tinker
```

Tempel baris berikut (sekali saja). `$wa` mengirim pesan sebagai customer, `$inbox` menampilkan pesan yang diterima customer:

```php
$hp = '6281111111111'; $wa = function ($t) use ($hp) { app(App\Services\WhatsAppChatbotService::class)->receive(['id' => 'sim-'.uniqid(), 'from' => $hp, 'text' => ['body' => $t]]); echo App\Models\WhatsappLog::where('direction', 'outbound')->where('phone_number', $hp)->latest('id')->value('message'), "\n"; }; $inbox = function () use ($hp) { App\Models\WhatsappLog::where('direction', 'outbound')->where('phone_number', $hp)->oldest('id')->get()->each(fn ($m) => print("---\n{$m->message}\n")); };
```

Lalu chat seperti di WhatsApp (ganti slug sesuai outlet Anda):

```php
$wa('ANTRE ayam-bakar-nusantara-a');
$wa('2');
$wa('YA');
```

Setelah itu klik **Panggil berikutnya** di halaman antrean admin, lalu jalankan `$inbox();` untuk melihat notifikasi "tinggal 3 antrean", "tinggal 1 antrean", dan "giliran tiba". Untuk mensimulasikan customer lain, jalankan ulang baris `$hp = ...` dengan nomor berbeda.

## Menghubungkan WhatsApp

### 1. Buat alamat publik HTTPS (untuk development)

Provider WhatsApp perlu mengirim pesan masuk (webhook) ke aplikasi Anda. Gunakan Cloudflare Tunnel:

```bash
cloudflared tunnel --url http://localhost:8000
```

Salin alamat `https://….trycloudflare.com` yang muncul, isi ke `APP_URL` di `.env`, lalu jalankan:

```bash
php artisan config:clear
```

Alamat tunnel berubah setiap kali `cloudflared` dijalankan ulang. Perbarui `APP_URL` dan URL webhook di provider setiap kali itu terjadi.

### 2a. Twilio

Isi `.env` (jangan pernah commit atau bagikan token):

```env
WHATSAPP_ENABLED=true
WHATSAPP_PROVIDER=twilio
WHATSAPP_PHONE_NUMBER=14155238886
TWILIO_ACCOUNT_SID=AC...
TWILIO_AUTH_TOKEN=...
```

- `WHATSAPP_PHONE_NUMBER` adalah nomor WhatsApp Twilio Anda (nomor sandbox atau nomor milik sendiri).
- Di Twilio Console → Messaging → Try it out → Send a WhatsApp message → **Sandbox settings**, isi **"When a message comes in"** dengan `https://<alamat-tunnel>/webhooks/twilio` (method **POST**).
- Status pengiriman (Terkirim, Sampai, Dibaca) otomatis dilaporkan ke `/webhooks/twilio/status` jika `APP_URL` memakai HTTPS.
- Pada sandbox, setiap nomor penguji harus mengirim `join <kode-sandbox>` ke nomor Twilio terlebih dahulu.

### 2b. Meta WhatsApp Cloud API

```env
WHATSAPP_ENABLED=true
WHATSAPP_PROVIDER=meta
WHATSAPP_PHONE_NUMBER=62...
WHATSAPP_ACCESS_TOKEN=...
WHATSAPP_PHONE_NUMBER_ID=...
WHATSAPP_BUSINESS_ACCOUNT_ID=...
WHATSAPP_APP_SECRET=...
WHATSAPP_VERIFY_TOKEN=teks-acak-buatan-anda
WHATSAPP_API_VERSION=v23.0
```

Webhook di Meta: `https://<alamat-tunnel>/webhooks/whatsapp`, dengan verify token yang sama, lalu subscribe ke field **messages**.

### 3. Template notifikasi (opsional)

Balasan chatbot bisa dikirim bebas karena customer chat terlebih dahulu. Notifikasi yang dikirim **lebih dari 24 jam** setelah pesan terakhir customer memerlukan template yang sudah disetujui. Dua variabel template diisi otomatis: `{{1}}` nomor antrean, `{{2}}` nama outlet.

```env
WHATSAPP_TEMPLATE_THREE_AHEAD=
WHATSAPP_TEMPLATE_ONE_AHEAD=
WHATSAPP_TEMPLATE_CALLED=
WHATSAPP_TEMPLATE_CANCELLED=
```

Isi dengan **Content SID** (`HX...`) untuk Twilio, atau **nama template** untuk Meta.

### 4. Jalankan

Gunakan tiga terminal: `php artisan serve`, `php artisan queue:work`, dan `cloudflared tunnel --url http://localhost:8000`. Semua pesan masuk dan keluar bisa dipantau di **Admin → Log WhatsApp**.

## Test otomatis

```bash
php artisan test
```

Test memakai database SQLite in-memory dan API WhatsApp tiruan, jadi database lokal tidak tersentuh dan tidak ada pesan yang benar-benar terkirim. Cakupannya meliputi alur chatbot, penomoran antrean dan estimasi waktu, notifikasi, Twilio dan Meta (termasuk verifikasi signature), serta pembagian akses pemilik dan admin outlet.

## Masalah umum

| Masalah | Solusi |
|---|---|
| `Access denied for user 'root'` | `DB_PASSWORD` di `.env` salah |
| `Unknown database 'satset'` | Database belum dibuat (lihat langkah 3 instalasi) |
| `No connection could be made` | MySQL belum berjalan. Start service MySQL atau XAMPP |
| Perubahan `.env` tidak berpengaruh | Jalankan `php artisan config:clear` |
| Tampilan terlihat lama setelah update | Tekan **Ctrl + F5** di browser |
| HP tidak bisa membuka halaman saat scan QR | Pastikan Wi-Fi sama dan server dijalankan dengan `php artisan satset:lan`. Izinkan PHP di Windows Firewall (Private). Beberapa Wi-Fi publik atau kampus memblokir koneksi antar perangkat; coba hotspot HP |
| QR masih berisi `localhost` | Halaman admin dibuka lewat `localhost`. Buka lewat alamat jaringan dari `php artisan satset:lan` |
| Tombol "Lanjut via WhatsApp" abu-abu | WhatsApp belum aktif atau konfigurasinya belum lengkap (lihat bagian WhatsApp) |
| Pesan WhatsApp berstatus "Menunggu kirim" | `php artisan queue:work` belum dijalankan |
| Webhook Twilio mendapat 403 | `APP_URL` tidak sama dengan alamat tunnel, atau `config:clear` belum dijalankan |
