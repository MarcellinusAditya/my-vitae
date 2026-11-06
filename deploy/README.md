# Deployment setup (production server)

Konfigurasi nyata yang dipakai di `myvitae.vitroweb.online` (Ubuntu 24.04, satu server).

## Struktur

| Hal | Nilai |
|---|---|
| Kode produksi | `/var/www/myvitae` (git clone, branch `develop`) |
| Folder development | `/home/ubuntu/projects/my-vitae` (terpisah, tidak tersentuh) |
| Database produksi | `my_vitae_prod` (user `myvitae_prod`) |
| Database development | `my_vitae` (terpisah total) |
| Web server | nginx + php8.2-fpm, HTTPS via Let's Encrypt |
| Queue worker | Supervisor: `myvitae-worker` |
| Backup | `/var/backups/myvitae`, harian 03:15, retensi 7 hari |

Server block ada di `/etc/nginx/sites-available/myvitae`; konfigurasi worker di
`/etc/supervisor/conf.d/myvitae-worker.conf`.

## Alur rilis

Produksi **tidak** memakai folder development. Rilis = tarik kode ke `/var/www/myvitae`:

```bash
cd /var/www/myvitae
git pull origin develop

composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache

# Worker memegang kode lama di memori: tanpa restart, job baru bisa gagal
# dengan BindingResolutionException.
sudo supervisorctl restart myvitae-worker:*
```

Untuk kembalikan versi: `git checkout <tag>` lalu ulangi langkah build + `migrate:rollback`
bila perlu (jangan pernah `migrate:fresh`).

## Izin file

Kode dimiliki `ubuntu:www-data`; `storage/` dan `bootstrap/cache/` mode 775 dengan
setgid (`2775`) supaya berkas baru tetap bisa dibaca web server dan worker sekaligus.

```bash
sudo chown -R ubuntu:www-data /var/www/myvitae
sudo chmod -R 2775 /var/www/myvitae/storage /var/www/myvitae/bootstrap/cache
sudo find /var/www/myvitae/storage -type f -exec chmod 664 {} \;
```

Tanpa setgid, Laravel membuat `storage/app/private/cv-uploads` dengan mode `700` milik
user CLI, dan worker `www-data` gagal dengan `file_missing`.

## Port yang sengaja tertutup

UFW hanya membuka 22, 80, 443. Port development (8001) dan gateway AI (20128) tetap
berjalan tapi **hanya dari localhost** — aplikasi produksi memanggil gateway lewat
`http://127.0.0.1:20128/v1`.

## Perintah operasional

```bash
sudo supervisorctl status myvitae-worker:*
sudo tail -f /var/log/myvitae-worker.log
sudo tail -f /var/log/nginx/myvitae.error.log
sudo /usr/local/bin/backup-myvitae        # backup manual
php artisan app:queue-health              # monitoring queue
curl -s https://myvitae.vitroweb.online/health/queue
```
