# Boom Click Handler

Boom Click Handler adalah plugin WordPress yang mendeteksi dan mencegah klik iklan berlebihan dari pengguna dengan perangkat dan IP yang sama menggunakan metode deteksi canggih.

## Fitur

- Mendeteksi klik iklan lebih dari 2 kali dalam waktu kurang dari 1 menit.
- Mendeteksi klik iklan lebih dari 5 kali dalam waktu 30 menit.
- Mendeteksi klik cepat berulang (5 klik dalam 10 detik).
- Mengkarantina pengguna yang melanggar batas klik selama 1 jam.
- Menambahkan pengguna yang mencurigakan ke daftar hitam.
- Mencegah pengguna yang dikarantina atau daftar hitam untuk mengklik iklan.

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

## Lisensi

Plugin ini dilisensikan di bawah @luffynas.