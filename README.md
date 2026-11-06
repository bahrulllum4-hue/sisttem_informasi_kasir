# Kasir UMKM — PHP + MySQL + SAW

Sistem informasi kasir untuk UMKM dengan tampilan "Noir & Gold": kasir cepat, struk, laporan, dan
**prioritas restok memakai metode SAW (Simple Additive Weighting)**.

## Cara menjalankan (XAMPP)
1. Salin folder ini ke `C:\xampp\htdocs\Web_kasir`.
2. Jalankan **Apache** dan **MySQL** di XAMPP.
3. Buka phpMyAdmin → tab **Import** → pilih `database.sql` → Go. (Database dibuat otomatis bernama **`db_kasirweb`**.)
4. Bila user/password MySQL berbeda dari default (`root`, kosong), ubah di `config/database.php`.
5. Buka `http://localhost/Web_kasir/` dan masuk.

| Peran | Username | Password |
|---|---|---|
| Admin | `admin` | `admin123` |
| Kasir | `kasir` | `kasir123` |

Segera ganti password lewat menu **Pengguna / Akun Saya**.

## Struktur folder
```
config/database.php   koneksi database + identitas toko (nama, alamat, telepon)
includes/             auth.php, functions.php (helper, ikon, grafik SVG), header.php, footer.php, saw.php (logika SAW)
assets/css/style.css  seluruh desain
assets/js/            app.js (umum), kasir.js (keranjang & pembayaran)
tests/saw_test.php    pengujian logika SAW (php tests/saw_test.php)
dashboard.php  kasir.php  api_simpan.php  struk.php
produk.php  produk_form.php  kategori.php  laporan.php  saw.php  pengguna.php
login.php  logout.php  index.php  database.sql
```

## Fitur
- **Kasir**: grid produk, filter kategori, pencarian, Enter untuk menambah (siap scanner barcode), pecahan uang cepat, kembalian otomatis, struk cetak.
- **Produk & kategori**: CRUD, stok minimum per produk, margin otomatis; produk yang pernah terjual diarsipkan (bukan dihapus) agar laporan tetap utuh.
- **Laporan**: filter periode, omzet/laba, grafik harian, produk terlaris, paginasi, ekspor CSV, cetak.
- **Prioritas restok (SAW)**: bobot dapat diatur (total 100%), tampilan langkah matriks X → normalisasi R → nilai V, periode data dapat dipilih.
- **Peran**: Admin (semua menu) dan Kasir (dashboard, kasir, akun sendiri).
- **Keamanan**: password di-hash, token CSRF, prepared statement, harga & stok divalidasi di server, batas percobaan login, folder `config/`, `includes/`, `tests/` dikunci `.htaccess`.
- Grafik dan ikon dibuat langsung (SVG), jadi tetap jalan tanpa internet. Hanya font (Inter, Playfair Display) yang diambil dari Google Fonts; bila offline otomatis memakai font sistem.

## Metode SAW
Kriteria (bobot awal): C1 Jumlah terjual 35% (benefit), C2 Total laba 25% (benefit), C3 Sisa stok 25% (cost), C4 Frekuensi transaksi 15% (benefit).
- Benefit: `r = x / max(x)` · Cost: `r = (min(x)+1) / (x+1)`
- Nilai preferensi: `V = Σ (bobot × r)`; V ≥ 0.65 *Restok segera*, V ≥ 0.40 *Perlu dipantau*, selain itu *Aman*.
