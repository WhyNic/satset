# SatSet — Team Progress

File ini adalah pusat koordinasi pengembangan SatSet.

Semua anggota tim cukup melihat dan meng-update file ini untuk mengetahui:

- konsep SatSet saat ini
- fitur yang sudah selesai
- fitur yang sedang dikerjakan
- pembagian tugas
- blocker
- next priority
- cara menjalankan project
- aturan Git

---

# 1. Project Overview

**SatSet** adalah platform virtual queue untuk bisnis F&B seperti:

- restoran
- cafe
- food stall
- dessert shop
- beverage shop
- bisnis F&B lainnya

SatSet membantu customer mengantre tanpa harus berdiri dan menunggu secara fisik.

---

# 2. Final Customer Flow

Flow utama SatSet:

```text
Customer datang ke restoran
        ↓
Scan QR SatSet
        ↓
Masuk ke halaman restoran SatSet
        ↓
Lihat jumlah antrean + estimasi waktu
        ↓
Klik "Lanjut via WhatsApp"
        ↓
Chatbot SatSet terbuka
        ↓
Chatbot mengetahui restoran
        ↓
Customer memilih jumlah orang / party size
        ↓
Customer konfirmasi
        ↓
Queue Ticket dibuat
        ↓
Customer mendapatkan nomor antrean
        ↓
Update antrean dikirim lewat WhatsApp
        ↓
Customer kembali ketika giliran tiba