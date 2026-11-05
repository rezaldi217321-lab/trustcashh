# TRUSTCASH
Sistem Informasi Pencatatan Pemasukan dan Pengeluaran Pribadi.

## Teknologi
PHP 8+, MySQL/MariaDB, HTML, CSS, JavaScript (Chart.js via CDN).

## Menjalankan di XAMPP
1. Ekstrak folder `trustcash` ke `htdocs`.
2. Jalankan Apache dan MySQL dari XAMPP.
3. Buka phpMyAdmin, impor `database/trustcash.sql`.
4. Buka `http://localhost/trustcash/`.
5. Daftar akun melalui tombol **Buat akun**.

## Fitur
- Registrasi, login, logout dengan password hashing.
- Dashboard saldo, pemasukan, pengeluaran.
- CRUD transaksi dan kategori.
- Filter riwayat berdasarkan jenis dan rentang tanggal.
- Anggaran bulanan sederhana.
- Grafik pemasukan vs pengeluaran.
- Ekspor transaksi CSV.
- Prepared statements, CSRF token, validasi input, dan pembatasan data per pengguna.

## Konfigurasi database
Ubah `config/database.php` jika username/password MySQL berbeda.

## Upload ke GitHub
Buat repository baru di GitHub, lalu dari folder proyek jalankan:
```bash
git init
git add .
git commit -m "Initial TRUSTCASH app"
git branch -M main
git remote add origin https://github.com/USERNAME/NAMA-REPO.git
git push -u origin main
```

Jangan unggah kredensial produksi. Untuk hosting publik, gunakan HTTPS, konfigurasi database hosting, dan nonaktifkan error display.
