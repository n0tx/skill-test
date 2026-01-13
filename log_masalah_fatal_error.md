# Log Masalah & Solusi: `Fatal Error Class "env" does not exist`

Catatan ini merangkum proses identifikasi dan penyelesaian masalah fatal error yang terjadi saat menjalankan `php artisan serve`.

### 1. Gejala Masalah

Saat mencoba menjalankan server pengembangan dengan `php artisan serve`, aplikasi gagal total dengan pesan error:
```
PHP Fatal error: Uncaught ReflectionException: Class "env" does not exist
```
Masalah ini menghentikan aplikasi bahkan sebelum bisa berjalan.

### 2. Proses Investigasi (Gagal)

Beberapa solusi standar dicoba namun tidak berhasil, yang menandakan masalahnya tidak biasa:
- **Membersihkan Cache:** `php artisan optimize:clear` tidak memberikan efek.
- **Instal Ulang Dependensi:** Menghapus folder `vendor` dan `composer.lock` lalu menjalankan `composer install` juga tidak berhasil.
- **Membuat Ulang File `.env`:** Menghapus dan membuat ulang file `.env` dari `.env.example` juga tidak menyelesaikan masalah.

### 3. Akar Masalah Ditemukan

Setelah analisis mendalam pada *stack trace* (jejak error), ditemukan bahwa error berasal dari file `bootstrap/app.php`.

Penyebabnya adalah **perubahan yang dilakukan sebelumnya untuk memperbaiki kegagalan pada unit test**. Kode `if (! app()->environment('testing'))` ditambahkan ke dalam file tersebut.

**Kesalahan fatalnya adalah:** Kode tersebut dieksekusi **terlalu dini** dalam proses *startup* (booting) Laravel. Pada tahap itu, *Service Container* Laravel (yang diakses melalui fungsi `app()`) belum sepenuhnya siap. Memanggilnya pada saat itu menyebabkan "korsleting" internal yang berujung pada error fatal yang aneh dan menyesatkan.

### 4. Solusi Final

Solusinya adalah **mengembalikan file `bootstrap/app.php` ke kondisi aslinya**, yaitu dengan menghapus blok `if` yang ditambahkan sebelumnya. Ini menghilangkan panggilan fungsi yang bermasalah dan membiarkan Laravel melakukan proses booting dengan benar.

### 5. Pelajaran Penting

Memodifikasi file-file inti Laravel (seperti di dalam folder `bootstrap/`) harus dilakukan dengan sangat hati-hati. Ada urutan proses startup yang ketat, dan memanggil fungsionalitas canggih (seperti `app()`) sebelum waktunya dapat menyebabkan error fatal yang sulit dilacak.
