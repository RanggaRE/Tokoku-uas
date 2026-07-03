# TokoKu — Sistem Kasir & Gudang (Point of Sale)

[![Laravel](https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![MySQL](https://img.shields.io/badge/MySQL-00758F?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com)
[![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white)](https://developer.mozilla.org/en-US/docs/Glossary/HTML5)
[![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white)](https://developer.mozilla.org/en-US/docs/Web/CSS)
[![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)

TokoKu adalah sistem manajemen kasir (Point of Sale) dan pergudangan modern berbasis web yang dirancang untuk mempermudah operasional toko ritel. Proyek ini dibuat sebagai pemenuhan tugas **Ujian Akhir Semester (UAS)**.

Sistem ini memisahkan arsitektur menjadi **Frontend Single-Page Application (SPA)** yang dinamis dan interaktif menggunakan Vanilla JS & CSS, serta **Backend API** berbasis RESTful Service menggunakan framework Laravel 11 dan database MySQL.

---

## 🚀 Fitur Utama

Sistem TokoKu memiliki fitur komprehensif untuk melayani transaksi kasir dan pengelolaan barang di gudang:

1. **Dashboard Statistik**
   * Ringkasan pendapatan harian & bulanan.
   * Grafik visual penjualan 7 hari terakhir.
   * Indikator cepat untuk total produk aktif dan jumlah produk berstok kritis.
   * Daftar transaksi terbaru.
2. **Kasir / Point of Sale (POS)**
   * Pencarian produk secara instan berdasarkan nama, kode, atau barcode.
   * Filter produk berdasarkan kategori.
   * Keranjang belanja interaktif dengan opsi diskon rupiah dan kalkulasi pajak PPN otomatis.
   * Pencetakan struk pembayaran fisik/PDF setelah transaksi berhasil diselesaikan.
3. **Manajemen Produk (CRUD)**
   * Input produk baru lengkap dengan kode produk, barcode, satuan, harga beli, harga jual, dan stok minimum.
   * Hapus produk secara aman menggunakan mekanisme *Soft Deletes*.
4. **Manajemen Kategori (CRUD)**
   * Pengelompokan produk dengan relasi database dinamis.
5. **Pembelian & Restock (Purchase Order)**
   * Pencatatan PO dari supplier untuk menambah persediaan produk di gudang secara otomatis.
6. **Mutasi & Adjust Stok**
   * Penyesuaian stok manual dengan 3 tipe pergerakan: Masuk (*In*), Keluar (*Out*), dan Penyesuaian nilai mutlak (*Adjustment*).
   * Log mutasi mencatat riwayat pergerakan stok lengkap dengan referensi dokumen dan alasan/keterangan.
7. **Monitoring Stok Rendah**
   * Halaman khusus yang memantau produk dengan stok di bawah stok minimum (*safety stock*).
   * Tombol tindakan cepat (*quick restock*) untuk menambah persediaan langsung dari halaman warning.
8. **Laporan & Riwayat Transaksi**
   * Laporan riwayat seluruh transaksi penjualan dan pembelian.
   * Pencarian transaksi berdasarkan nomor invoice atau nama pelanggan.
   * Fitur batalkan transaksi (*cancel order*) yang otomatis mengembalikan jumlah stok produk ke kondisi semula.

---

## 🛠️ Struktur Project

```text
UAS/
├── index.html                   # Single-Page Frontend Application (HTML/CSS/JS)
├── tokoku-backend/              # RESTful API Backend (Laravel 11 Project)
│   ├── app/
│   │   ├── Http/Controllers/    # Controller API (Product, Category, Transaction, Dashboard, dll)
│   │   └── Models/              # Model Eloquent Database
│   ├── database/
│   │   ├── migrations/          # Migrasi Skema Database MySQL
│   │   └── seeders/             # Seeders untuk Data Dummy Awal
│   ├── routes/
│   │   └── api.php              # Definisi Rute Endpoint REST API
│   └── .env                     # Konfigurasi Environment Backend
└── README.md                    # Dokumentasi Project (File Ini)
```

---

## 💻 Panduan Instalasi & Menjalankan Proyek

### Prasyarat System
* PHP >= 8.2
* Composer
* MySQL / MariaDB Server
* Web Browser modern (Chrome/Firefox/Edge)

---

### Langkah 1: Setup Backend (Laravel)

1. Masuk ke folder backend proyek:
   ```bash
   cd tokoku-backend
   ```
2. Instal semua dependensi Composer:
   ```bash
   composer install
   ```
3. Buat salinan file konfigurasi `.env`:
   ```bash
   cp .env.example .env
   ```
4. Generate Application Key:
   ```bash
   php artisan key:generate
   ```
5. Sesuaikan konfigurasi database MySQL di file `.env` Anda:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=tokoku_db
   DB_USERNAME=username_database_anda
   DB_PASSWORD=password_database_anda
   ```
6. Jalankan migrasi tabel database sekaligus pengisian data awal (*seeding*):
   ```bash
   php artisan migrate:fresh --seed
   ```
7. Jalankan server lokal Laravel:
   ```bash
   php artisan serve
   ```
   *Secara default server backend akan berjalan di alamat **`http://127.0.0.1:8000`**.*

---

### Langkah 2: Setup Frontend

1. Buka file **`index.html`** yang terletak pada root direktori project UAS langsung di browser Anda, atau jalankan menggunakan ekstensi **Live Server** di VS Code.
2. Di bagian sidebar kiri bawah aplikasi TokoKu, perhatikan input **API Base URL**.
3. Pastikan input URL tersebut mengarah ke alamat backend Laravel Anda:
   ```text
   http://localhost:8000/api
   ```
4. Jika status di pojok kanan atas menunjukkan **🟢 Terhubung (Connected)**, sistem TokoKu siap digunakan untuk testing!

---

## 🗄️ Skema Database

Berikut adalah relasi tabel database utama dalam sistem TokoKu:

* **`users`**: Menyimpan kredensial pengguna (seperti *Admin Gudang* dan *Kasir*).
* **`categories`**: Menyimpan kategori produk.
* **`products`**: Menyimpan detail data produk (terhubung ke `categories` via `category_id`).
* **`sales`**: Menyimpan transaksi penjualan kasir (POS).
* **`sale_items`**: Menyimpan detail produk yang dibeli pada transaksi penjualan.
* **`purchases`**: Menyimpan transaksi pembelian/restock dari supplier.
* **`purchase_items`**: Menyimpan detail produk yang dimasukkan dari transaksi pembelian.
* **`stock_movements`**: Mencatat histori keluar-masuk stok barang untuk audit pergudangan.

---

## 📌 Data Uji Coba Default (Seeded Data)

Saat Anda menjalankan perintah `--seed`, sistem secara otomatis membuat data dummy berikut:

### Kredensial Pengguna
* **Admin Gudang**: `admin@tokoku.com` (password: `password`)
* **Kasir**: `kasir@tokoku.com` (password: `password`)

### Daftar Kategori
* Makanan, Minuman, Sembako, Alat Tulis, Kebutuhan Rumah Tangga.

### Produk Siap Jual
* Aqua Botol 600ml, Coca Cola 250ml, Indomie Goreng, Beras Pandan Wangi 5kg, dll.

---

## 📝 Identitas Mahasiswa (UAS)
* **Nama**: [Isi Nama Anda]
* **NIM**: [Isi NIM Anda]
* **Mata Kuliah**: Pemrograman Berbasis Web / Tugas Akhir Semester
* **Dosen Pengampu**: [Nama Dosen Pengampu]
