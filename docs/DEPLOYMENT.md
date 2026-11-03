# Deployment & Rollback

Referensi operasional untuk men-deploy MyVitae (Laravel 12 + Inertia/React).

## Prasyarat

- PHP 8.2+, Composer, Node 20+/npm.
- Database (MySQL/PostgreSQL; pengembangan memakai SQLite).
- Redis atau database sebagai queue backend (proyek ini memakai `QUEUE_CONNECTION=database`).
- Web server (nginx + php-fpm) yang mengarah ke `public/`.

## Konfigurasi production wajib

Set di `.env` (jangan commit `.env`):

```
APP_ENV=production
APP_DEBUG=false        # WAJIB false: debug mode membocorkan stack trace & env
APP_URL=https://domain-anda
LOG_LEVEL=warning

QUEUE_CONNECTION=database

# Batas biaya AI (config/ai.php)
AI_RATE_PER_HOUR=10
AI_UPLOADS_PER_HOUR=20
AI_EXPORTS_PER_HOUR=30
AI_DAILY_LIMIT=50

# Ambang monitoring queue (config/ai.php)
QUEUE_MAX_PENDING=100
QUEUE_MAX_OLDEST_SECONDS=600
```

Verifikasi: `php artisan about` harus menampilkan environment `production` dan debug
`OFF`. Bila `APP_DEBUG=true` di production, hentikan deploy.

## Langkah deploy

```bash
# 1. Ambil kode & dependency
git pull --ff-only origin <tag-atau-branch-rilis>
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 2. Migrasi (tanpa menghapus data)
php artisan migrate --force

# 3. Cache konfigurasi/route/view
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Symlink storage bila belum ada
php artisan storage:link

# 5. Restart worker (wajib: worker lama memegang kode lama)
php artisan queue:restart
```

## Queue worker

Jalankan worker sebagai service (systemd/supervisor) agar job AI (ekstraksi CV, analisis
kecocokan, generasi dokumen) berjalan. **Setiap kali ada perubahan kode/DI binding,
`php artisan queue:restart`** — worker yang basi gagal dengan `BindingResolutionException`.

```bash
php artisan queue:work --tries=3 --timeout=300
```

## Scheduler

Daftarkan cron agar retensi & ringkasan berjalan:

```
* * * * * cd /path/app && php artisan schedule:run >> /dev/null 2>&1
```

`app:prune-files` terjadwal harian 02:30 (`routes/console.php`).

## Monitoring

- `GET /up` — health check framework.
- `GET /health/queue` — JSON `{healthy, queue:{pending, failed, oldest_pending_seconds}}`;
  HTTP 200 sehat, 503 bila melewati ambang. Publik, hanya angka agregat.
- `php artisan app:queue-health --json` — versi CLI, exit code non-zero bila tidak sehat
  (cocok untuk cron + alerting).

## Backup

- **Database** harian, retensi ≥ 7 hari: `mysqldump` / `pg_dump` / salinan file SQLite.
- **`storage/app/`** (berkas privat CV & ekspor) — ikutkan dalam backup atau andalkan
  retensi 30 hari (`docs/RETENTION.md`).
- Uji restore berkala, jangan hanya menyalin.

## Rollback

```bash
# 1. Kembalikan kode ke tag rilis sebelumnya
git checkout <tag-sebelumnya>
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 2. Kembalikan skema BILA rilis ini menambah migration yang tidak kompatibel
php artisan migrate:rollback --step=<jumlah>   # TIDAK PERNAH migrate:fresh

# 3. Bersihkan cache & restart worker
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
```

Migration yang menghapus/mengubah kolom bersifat destruktif: siapkan backup sebelum
deploy dan uji rollback di staging lebih dulu.
