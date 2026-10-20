# Panduan Menjalankan SatSet untuk Presentasi

Panduan ini untuk mendemokan SatSet di depan kelas atau juri: laptop tersambung ke proyektor, dan HP dipakai untuk scan QR sebagai customer. WhatsApp sungguhan tidak dipakai; chat customer disimulasikan dari terminal.

---

## A. Persiapan H-1 (di rumah)

Lakukan sehari sebelumnya, supaya di hari H tinggal menyalakan.

### A1. Kalau memakai komputer lain (belum pernah menjalankan SatSet)

Ikuti README bagian **Instalasi pertama kali** sampai selesai (Composer, `.env`, database, migrate, akun pemilik). Kalau memakai laptop yang sudah biasa dipakai, lewati ke A2.

### A2. Atur `.env` untuk demo

```env
APP_URL=http://localhost:8000
WHATSAPP_ENABLED=false
```

Lalu jalankan:

```bash
php artisan config:clear
```

### A3. (Opsional) Mulai dari data bersih

Supaya data uji coba lama tidak ikut tampil saat demo. **Perintah ini menghapus semua data**, termasuk akun admin:

```bash
php artisan migrate:fresh --seed
```

```bash
php artisan satset:admin email@anda.com --name="Nama Anda"
```

Hasilnya 3 outlet contoh (Ayam Bakar Nusantara, Ayam Geprek Mantap, Rice Bowl & More) dengan beberapa antrean contoh.

### A4. Siapkan akun kasir

Login sebagai pemilik → **Akun admin → Tambah akun**, lalu buat satu akun **Admin outlet** untuk Ayam Bakar Nusantara. Akun ini dipakai saat menjelaskan peran kasir.

### A5. Gladi resik sekali

Jalankan semua langkah di bagian **B** dan **C** satu kali dari awal sampai akhir. Kalau ada yang macet, masih ada waktu untuk memperbaikinya.

### A6. Siapkan cadangan

- Rekam layar (screen record) satu kali demo yang berhasil, untuk jaga-jaga kalau di hari H laptop atau jaringan bermasalah.
- Isi baterai laptop dan HP penuh, dan bawa charger.

---

## B. Hari H: menyalakan SatSet (±5 menit sebelum tampil)

### B1. Sambungkan jaringan

Laptop dan HP harus **satu jaringan**. Wi-Fi kampus sering memblokir koneksi antar perangkat, jadi **paling aman memakai hotspot HP**:

1. Nyalakan hotspot di HP.
2. Sambungkan laptop ke hotspot tersebut.
3. HP yang menjadi hotspot tetap bisa membuka alamat laptop.

### B2. Pastikan MySQL menyala

Kalau memakai XAMPP, klik **Start** pada MySQL.

### B3. Terminal 1: nyalakan server

Buka terminal di folder proyek, lalu jalankan:

```bash
php artisan satset:lan
```

Catat alamat yang muncul, misalnya `http://172.20.10.3:8000`. Alamat ini disebut **`ALAMAT`**. Kalau muncul pop-up Windows Firewall, pilih **Allow** (Private).

### B4. Terminal 2: siapkan "simulator WhatsApp"

```bash
php artisan tinker
```

Tempel fungsi bantu ini:

```php
$wa = function ($teks, $hp = '6281111111111') { app(App\Services\WhatsAppChatbotService::class)->receive(['id' => 'sim-'.uniqid(), 'from' => $hp, 'text' => ['body' => $teks]]); echo App\Models\WhatsappLog::where('direction', 'outbound')->where('phone_number', $hp)->latest('id')->value('message'), "\n"; };
$inbox = function ($hp = '6281111111111') { App\Models\WhatsappLog::where('direction', 'outbound')->where('phone_number', $hp)->oldest('id')->get()->each(fn ($m) => print("---\n{$m->message}\n")); };
```

Perbesar ukuran huruf terminal (**Ctrl + scroll** atau **Ctrl + =**) supaya terbaca di proyektor.

### B5. Buka tab browser di laptop

1. `ALAMAT/admin` → login sebagai pemilik.
2. Buka **Dashboard → Kelola antrean** untuk Ayam Bakar Nusantara di tab yang sama.
3. Tab baru: **QR Code → Lihat QR** untuk Ayam Bakar Nusantara.

### B6. Cek cepat dari HP

Scan QR dari layar laptop. Kalau halaman outlet terbuka di HP, semuanya siap.

> Kalau HP tidak bisa membuka halaman: pastikan laptop tersambung ke hotspot HP, jalankan ulang `php artisan satset:lan`, lalu buka QR lewat alamat yang baru. Kalau tetap tidak bisa, pakai **rencana cadangan** di bagian D.

---

## C. Urutan demo (±7 menit)

### 1. Masalahnya (30 detik)
"Di bazar dan restoran ramai, customer harus berdiri lama di antrean. SatSet membuat mereka bisa antre tanpa berdiri."

### 2. Customer scan QR (1 menit)
- Tunjukkan **poster QR** di layar laptop (tab QR).
- Scan dengan HP, dan perlihatkan HP ke audiens (atau mirror layar HP).
- Jelaskan halaman outlet: **sedang dilayani**, **jumlah menunggu**, **estimasi tunggu**, semuanya tanpa login dan tanpa install aplikasi.

### 3. Daftar lewat WhatsApp (1,5 menit)
Jelaskan: "Customer klik *Lanjut via WhatsApp* dan chat dengan bot SatSet. Untuk demo, chat-nya kami simulasikan di sini."

Pindah ke **terminal 2**, lalu ketik satu per satu:

```php
$wa('ANTRE ayam-bakar-nusantara-a');
```

```php
$wa('2');
```

```php
$wa('YA');
```

Perlihatkan balasan bot: sapaan, konfirmasi, lalu **nomor antrean** dan link **Lihat antrean**.

### 4. Tiket digital di HP (1 menit)
- Buka link tiket di HP. Ganti `localhost:8000` di link dengan `ALAMAT`.
- Perlihatkan nomor antrean besar, **antrean di depan**, dan **estimasi**. "Customer bisa pergi jalan-jalan, halaman ini ter-update sendiri."

### 5. Kasir memanggil antrean (2 menit)
Pindah ke tab **Kelola antrean** di laptop:
- Klik **Panggil berikutnya** lalu **Tandai selesai** beberapa kali.
- Setiap kali, tunjukkan **HP**: jumlah antrean di depan berkurang sendiri (±10 detik).
- Di terminal 2, ketik `$inbox();` untuk memperlihatkan notifikasi WhatsApp yang dikirim otomatis, misalnya "Tinggal 3 antrean lagi…" dan "Tinggal 1 antrean lagi…".
- Saat giliran Anda dipanggil, **kartu tiket di HP berubah gelap dengan nomor menyala**. Ini momen yang paling menarik untuk ditunjukkan.

### 6. Fitur pemilik (1 menit)
- **Dashboard**: kondisi semua outlet.
- **Akun admin**: setiap kasir punya akun sendiri dan hanya bisa mengelola outletnya.
- **QR Code**: cetak poster untuk ditempel di outlet.

### 7. Penutup (30 detik)
"Customer tidak perlu berdiri, outlet tidak perlu kertas nomor antrean, dan semuanya cukup dengan scan QR dan WhatsApp."

---

## D. Rencana cadangan

| Masalah saat demo | Solusi cepat |
|---|---|
| HP tidak bisa membuka halaman | Tunjukkan alur customer di **laptop**: buka `http://localhost:8000/r/ayam-bakar-nusantara-a` dan link tiket di jendela browser kedua, berdampingan dengan halaman admin |
| Laptop gagal menyalakan server | Putar rekaman demo dari langkah A6 |
| Error database (`No connection could be made`) | MySQL belum menyala. Start MySQL lalu refresh |
| Tampilan berantakan atau terlihat lama | Tekan **Ctrl + F5** |
| Lupa password admin | Buat akun pemilik baru: `php artisan satset:admin email-lain@anda.com` |
| Tinker tertutup | Jalankan lagi `php artisan tinker` dan tempel ulang fungsi bantu di B4 |

---

## E. Setelah presentasi

- Tekan **Ctrl + C** di terminal 1, dan ketik `exit` di terminal 2.
- Matikan hotspot HP.
