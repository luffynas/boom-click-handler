# Boom Click Handler

Boom Click Handler adalah plugin WordPress yang mendeteksi dan mencegah klik iklan berlebihan dari pengguna dengan perangkat dan IP yang sama. Plugin ini membantu melindungi akun Google AdSense Anda dari aktivitas klik yang mencurigakan.

## Fitur

- Mendeteksi klik iklan lebih dari 2 kali dalam waktu kurang dari 1 menit.
- Mendeteksi klik iklan lebih dari 5 kali dalam waktu 30 menit.
- Mengkarantina pengguna yang melanggar batas klik selama 1 jam.
- Mencegah pengguna yang dikarantina untuk mengklik iklan.

## Instalasi

1. **Download** dan **Ekstrak** plugin ini.
2. **Upload** folder `boom-click-handler` ke direktori `/wp-content/plugins/`.
3. **Aktifkan** plugin melalui menu 'Plugins' di WordPress.

## Penggunaan

1. Tambahkan kode iklan AdSense Anda dalam konten seperti berikut:
    ```html
    <div class="adsense-wrapper">
        <ins class="adsbygoogle"
             style="display:block"
             data-ad-client="ca-pub-1234567890123456"
             data-ad-slot="1234567890"
             data-ad-format="auto"></ins>
        <script>
             (adsbygoogle = window.adsbygoogle || []).push({});
        </script>
    </div>
    ```

2. Plugin akan secara otomatis mendeteksi klik dan menerapkan logika karantina jika pengguna melanggar batas klik.

## Kode

### File: `boom-click-handler.php`

```php
<?php
/*
Plugin Name: Boom Click Handler
Description: Mendeteksi double click iklan adsense dari pengguna dengan device dan IP yang sama, dan memasukkan mereka ke dalam karantina jika mereka mengklik lebih dari 2 kali dalam waktu kurang dari 1 menit atau lebih dari 5 kali dalam waktu 30 menit.
Version: 1.3
Author: @luffynas
*/
```

## Lisensi

Plugin ini dilisensikan di bawah @luffynas.