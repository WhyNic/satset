# Panduan Uji Coba SatSet

Panduan ini mencoba **seluruh fitur** SatSet secara berurutan, dari sisi customer, kasir, sampai pemilik. WhatsApp tidak perlu aktif: chatbot disimulasikan dari terminal.

Centang setiap langkah `[ ]` yang hasilnya sesuai. Kalau ada yang tidak sesuai, catat nomor langkahnya.

**Perkiraan waktu:** 30–45 menit.

---

## Bagian 0 — Persiapan

**Yang dibutuhkan:** laptop, HP di Wi-Fi yang sama, dan akun pemilik SatSet.

- [ ] **0.1** Pastikan MySQL menyala.
- [ ] **0.2** Di `.env`, pastikan WhatsApp mati dan `APP_URL` mengarah ke localhost:
  ```env
  APP_URL=http://localhost:8000
  WHATSAPP_ENABLED=false
  ```
- [ ] **0.3** Jalankan di **terminal 1**:
  ```bash
  php artisan config:clear
  ```
  ```bash
  php artisan satset:lan
  ```
  Catat alamat yang muncul, misalnya `http://192.168.1.189:8000`. Panduan ini menyebutnya **`ALAMAT`**.
- [ ] **0.4** Buka **terminal 2**, jalankan `php artisan tinker`, lalu tempel dua fungsi bantu ini (sekali saja):
  ```php
  $wa = function ($teks, $hp = '6281111111111') { app(App\Services\WhatsAppChatbotService::class)->receive(['id' => 'sim-'.uniqid(), 'from' => $hp, 'text' => ['body' => $teks]]); echo App\Models\WhatsappLog::where('direction', 'outbound')->where('phone_number', $hp)->latest('id')->value('message'), "\n"; };
  $inbox = function ($hp = '6281111111111') { App\Models\WhatsappLog::where('direction', 'outbound')->where('phone_number', $hp)->oldest('id')->get()->each(fn ($m) => print("---\n{$m->message}\n")); };
  ```
  - `$wa('pesan', 'nomor')` = customer mengirim WhatsApp ke SatSet (nomor default `6281111111111` adalah "Anda").
  - `$inbox('nomor')` = melihat semua WhatsApp yang diterima customer tersebut.
- [ ] **0.5** Di browser **laptop**, buka `ALAMAT/admin` (bukan `localhost`) dan login sebagai pemilik.
- [ ] **0.6** Buka **Admin → QR Code** dan catat **slug** outlet yang akan diuji (bagian akhir alamatnya, misalnya `ayam-bakar-nusantara-a`). Panduan ini menyebutnya **`SLUG`**.
- [ ] **0.7** Buka **Dashboard → Kelola antrean** pada outlet itu. Kalau masih ada antrean aktif dari percobaan sebelumnya, klik **Batalkan** pada semuanya supaya hitungan di Bagian 3 tepat.

> Tips: kalau ingin data bersih dari awal, jalankan `php artisan migrate:fresh --seed` lalu `php artisan satset:admin email@anda.com`. **Semua data lama akan terhapus.**

---

## Bagian 1 — Halaman customer (scan QR)

- [ ] **1.1** Di laptop, buka **Admin → QR Code → Lihat QR**. Alamat di QR harus diawali `ALAMAT`, bukan `localhost`.
- [ ] **1.2** Scan QR dengan kamera HP. Halaman outlet terbuka dan menampilkan: foto, nama outlet, status **"Antrean sedang dibuka"**, kotak **Sedang dilayani**, jumlah **Sedang menunggu**, dan **Estimasi tunggu**.
- [ ] **1.3** Karena WhatsApp mati, muncul pesan **"Pendaftaran lewat WhatsApp belum tersedia"** (bukan tombol WhatsApp).
- [ ] **1.4** Biarkan halaman outlet tetap terbuka di HP. Angka-angkanya akan diperbarui otomatis setiap ±15 detik pada langkah-langkah berikutnya.
- [ ] **1.5** Buka `ALAMAT` (beranda) di HP. Hanya ada penjelasan cara antre, tanpa daftar restoran.

---

## Bagian 2 — Chatbot: validasi pendaftaran

Semua langkah ini di **terminal 2 (tinker)**. Ganti `SLUG` dengan slug Anda.

| No | Ketik | Hasil yang benar |
|---|---|---|
| ☐ **2.1** | `$wa('halo', '6289999999999');` | Bot meminta scan QR dulu (belum ada outlet terpilih) |
| ☐ **2.2** | `$wa('ANTRE asal-asalan');` | "Outlet tidak ditemukan" |
| ☐ **2.3** | `$wa('ANTRE SLUG');` | Sapaan, jumlah antrean saat ini, estimasi, dan pertanyaan jumlah tamu |
| ☐ **2.4** | `$wa('0');` lalu `$wa('99');` lalu `$wa('abc');` | Ketiganya ditolak: "Jumlah tamu harus 1 sampai …" |
| ☐ **2.5** | `$wa('2');` | Ringkasan konfirmasi, diminta balas YA atau BATAL |
| ☐ **2.6** | `$wa('BATAL');` | "Pendaftaran belum dibuat", diminta jumlah tamu lagi |
| ☐ **2.7** | `$wa('3');` lalu `$wa('YA');` | **"Berhasil!"** beserta nomor antrean, status **Menunggu**, dan link **Lihat antrean** |
| ☐ **2.8** | `$wa('ANTRE SLUG');` (nomor yang sama) | "Anda sudah memiliki antrean aktif", **tidak** membuat nomor baru |

- [ ] **2.9** Di HP, halaman outlet (dari langkah 1.4) menampilkan jumlah **Sedang menunggu** yang bertambah tanpa di-refresh.
- [ ] **2.10** Batalkan tiket uji ini supaya tidak mengganggu bagian berikutnya: buka **Admin → antrean outlet**, klik **Batalkan** pada nomor tadi, lalu konfirmasi.

---

## Bagian 3 — Alur antrean lengkap dan notifikasi

Skenario: 4 customer lain mendaftar duluan, lalu **Anda** mendaftar paling akhir, sehingga Anda bisa melihat notifikasi "tinggal 3", "tinggal 1", dan "giliran tiba".

- [ ] **3.1** Daftarkan 4 customer lain sekaligus (di tinker):
  ```php
  foreach (['6282000000001','6282000000002','6282000000003','6282000000004'] as $n) { $wa('ANTRE SLUG', $n); $wa('2', $n); $wa('YA', $n); }
  ```
- [ ] **3.2** Daftarkan **Anda**:
  ```php
  $wa('ANTRE SLUG'); $wa('2'); $wa('YA');
  ```
  Catat nomor antrean Anda dan link **Lihat antrean**.
- [ ] **3.3** Buka link tiket itu **di HP**. Ganti `localhost:8000` di link dengan `ALAMAT`, misalnya `http://192.168.1.189:8000/queue/...`.
  Halaman tiket menampilkan nomor besar, status **Menunggu**, **Antrean di depan: 4**, dan estimasi (4 × rata-rata waktu layanan outlet).
- [ ] **3.4** Di laptop, buka **Admin → Dashboard**. Outlet menunjukkan 5 kelompok menunggu. Klik **Kelola antrean**.
- [ ] **3.5** Klik **Panggil berikutnya**. Nomor pertama muncul besar di panel gelap **Sedang dilayani**, dan barisnya di tabel diberi warna.
- [ ] **3.6** Klik **Tandai selesai**.
  - Di HP (tunggu ±10 detik): **Antrean di depan** berkurang menjadi **3**.
  - Di tinker, `$inbox();` menampilkan pesan **"Tinggal 3 antrean lagi…"**.
- [ ] **3.7** Klik **Panggil berikutnya**, lalu **Lewati**. Nomor itu berstatus **Dilewati** di Riwayat antrean.
- [ ] **3.8** Klik **Panggil berikutnya**, lalu **Tandai selesai**. Di HP: **Antrean di depan: 1**. `$inbox();` menampilkan **"Tinggal 1 antrean lagi…"**.
- [ ] **3.9** Klik **Panggil berikutnya**, lalu **Tandai selesai**, lalu **Panggil berikutnya** lagi (sekarang giliran Anda).
  - Di HP, kartu tiket berubah **gelap dengan nomor hijau terang** dan status **Giliran Anda**.
  - `$inbox();` menampilkan **"Giliran … sudah tiba!"**.
  - Halaman outlet di HP menampilkan nomor Anda di **Sedang dilayani**.
- [ ] **3.10** Klik **Tandai selesai**. Di HP, tiket menjadi abu-abu dengan status **Selesai**, dan tombol batal hilang.
- [ ] **3.11** Pastikan setiap notifikasi hanya muncul **sekali** di `$inbox();`, tidak ganda.

---

## Bagian 4 — Pembatalan oleh customer

- [ ] **4.1** Daftar lagi sebagai customer baru:
  ```php
  $wa('ANTRE SLUG', '6283000000001'); $wa('1', '6283000000001'); $wa('YA', '6283000000001');
  ```
- [ ] **4.2** Buka link tiketnya di HP (ganti `localhost:8000` dengan `ALAMAT`), klik **Batalkan antrean**, lalu konfirmasi.
- [ ] **4.3** Muncul pesan **"Antrean berhasil dibatalkan"**, dan tiket berubah status menjadi **Dibatalkan**.
- [ ] **4.4** `$inbox('6283000000001');` menampilkan **"Antrean … telah dibatalkan"**.
- [ ] **4.5** Nomor itu tidak lagi muncul di tabel **Antrean aktif** di admin.

---

## Bagian 5 — Buka/tutup pendaftaran outlet

- [ ] **5.1** Admin → **Restoran** → **Edit** outlet → matikan **Buka pendaftaran antrean** → **Simpan perubahan**.
- [ ] **5.2** Di HP, halaman outlet berubah menjadi **"Antrean sedang ditutup"** (tunggu ±15 detik atau refresh).
- [ ] **5.3** Di tinker: `$wa('ANTRE SLUG', '6284000000001');` → bot menjawab **"Antrean … sedang ditutup"**.
- [ ] **5.4** Nyalakan lagi **Buka pendaftaran antrean** dan simpan.

---

## Bagian 6 — Kelola restoran (pemilik)

- [ ] **6.1** **Restoran → Tambah restoran**. Isi nama, kategori, prefix (misalnya `K`), dan pengaturan lain, lalu simpan. Outlet baru muncul di daftar.
- [ ] **6.2** Coba simpan outlet lain dengan **prefix yang sama**. Harus ditolak.
- [ ] **6.3** Edit outlet yang sudah punya riwayat antrean dan coba ubah **prefix**-nya. Harus ditolak ("Prefix tidak dapat diubah…").
- [ ] **6.4** Klik **Nonaktifkan** pada outlet baru, lalu konfirmasi. Statusnya menjadi **Nonaktif** dan barisnya tampil pudar. Klik **Aktifkan** untuk mengembalikan.
- [ ] **6.5** **QR Code → Lihat QR** pada outlet baru. Coba **Salin URL**, **Download SVG**, **Cetak QR** (pratinjau cetak hanya berisi poster), dan **Buka halaman outlet**.

---

## Bagian 7 — Akun kasir (admin outlet)

Gunakan **jendela incognito/private** untuk login sebagai kasir, supaya login pemilik di jendela biasa tetap aktif.

- [ ] **7.1** Sebagai pemilik: **Akun admin → Tambah akun**. Isi nama "Kasir Uji", email, dan password, pilih peran **Admin outlet**, centang **satu** outlet, lalu klik **Buat akun**.
- [ ] **7.2** Coba buat akun **Admin outlet tanpa mencentang outlet**. Harus ditolak ("Pilih minimal satu outlet").
- [ ] **7.3** Di jendela incognito, buka `ALAMAT/admin` dan login sebagai Kasir Uji. Kasir langsung diarahkan ke **halaman antrean outletnya**.
- [ ] **7.4** Menu kasir hanya berisi **Outlet saya, Riwayat antrean, QR Code**. Tidak ada Restoran, Akun admin, atau Log WhatsApp.
- [ ] **7.5** Kasir bisa **Panggil berikutnya / Tandai selesai** di outletnya.
- [ ] **7.6** Kasir klik **Pengaturan outlet**. Nama dan prefix terkunci, dan opsi "Restoran aktif" tidak ada. Ubah deskripsi lalu simpan. Perubahan berhasil, dan kasir kembali ke halaman antrean.
- [ ] **7.7** Di jendela kasir, buka alamat admin outlet **lain**, misalnya ganti angka di `ALAMAT/admin/restaurants/1/queue` ke ID outlet lain. Harus muncul **403 Forbidden**.
- [ ] **7.8** Buka `ALAMAT/admin/users` dan `ALAMAT/admin/whatsapp-logs` sebagai kasir. Keduanya **403**.
- [ ] **7.9** Sebagai pemilik, edit Kasir Uji dan **centang outlet kedua**. Setelah kasir login ulang, ia masuk ke **Outlet saya** yang berisi 2 outlet.
- [ ] **7.10** Sebagai pemilik, matikan **Akun aktif** Kasir Uji lalu simpan. Di jendela kasir, klik menu apa saja. Kasir otomatis keluar dengan pesan akun dinonaktifkan, dan tidak bisa login lagi.
- [ ] **7.11** Sebagai pemilik, buka akun Anda sendiri di **Akun admin**. Peran dan status aktif tidak bisa diubah (terkunci).

---

## Bagian 8 — Riwayat, dashboard, dan log

- [ ] **8.1** **Riwayat antrean**: semua nomor dari uji coba tampil dengan status Indonesia (Menunggu, Dipanggil, Selesai, Dibatalkan, Dilewati).
- [ ] **8.2** Filter status **Selesai** → **Terapkan**. Hanya tiket selesai yang tampil.
- [ ] **8.3** Cari nomor antrean atau nomor WhatsApp (misalnya `6282000000001`). Hasilnya sesuai.
- [ ] **8.4** **Dashboard**: angka Antrean aktif, Tamu hari ini, dan Rata-rata tunggu sesuai dengan uji coba.
- [ ] **8.5** **Log WhatsApp**: muncul peringatan bahwa WhatsApp belum aktif. Semua pesan masuk (↓ Masuk) dan keluar (↑ Keluar) dari uji coba tercatat, dan pesan lengkap terbuka saat diklik.

---

## Bagian 9 — Tampilan di HP

- [ ] **9.1** Buka `ALAMAT/admin` di HP dan login. Menu berubah menjadi baris yang bisa digeser di atas, dan tidak ada bagian yang terpotong atau melebar keluar layar.
- [ ] **9.2** Halaman antrean admin bisa dipakai dari HP: tombol **Panggil berikutnya** besar dan tabel bisa digeser ke samping.
- [ ] **9.3** Halaman outlet dan tiket customer nyaman dibaca di HP tanpa perlu zoom.

---

## Selesai

Kalau semua langkah tercentang, seluruh fitur SatSet (tanpa WhatsApp sungguhan) berjalan dengan benar. Untuk menguji WhatsApp sungguhan, lihat bagian **Menghubungkan WhatsApp** di [README](../README.md).

Untuk menghentikan: tekan **Ctrl + C** di terminal 1, dan ketik `exit` di terminal 2.
