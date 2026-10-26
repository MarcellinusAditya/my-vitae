# Architecture & Technical Decisions — CareerCraft AI (my-vitae)

Baseline hasil Phase 0. Dokumen ini mencatat kondisi teknis saat ini, keputusan yang
sudah diambil, dan risiko yang masih terbuka. Perbarui setiap kali ada keputusan
arsitektur baru.

## 1. Stack baseline

| Komponen | Versi terpasang |
|---|---|
| PHP | 8.2.34 |
| Laravel | 12.70.1 |
| Node.js | 26.7.0 |
| npm | 11.19.0 |
| Database | MySQL, schema `my_vitae` |
| Queue | `database` |
| Session | `database` |
| Frontend | React 18 + Inertia 2 + TypeScript 5 |
| Build | Vite 7 + `laravel-vite-plugin` 2 |
| CSS | Tailwind CSS 3.4.19 + `@tailwindcss/forms` 0.5.11 |
| Test | Pest 3 (wrapper PHPUnit) |

## 2. Perintah kerja

```bash
composer install
npm install
php artisan migrate
php artisan storage:link

# development
php artisan serve            # http://127.0.0.1:8000
npm run dev                  # Vite HMR

# verifikasi sebelum menutup task
php artisan test             # baseline: 25 passed (61 assertions)
npm run build                # tsc + vite build
```

## 3. Keputusan teknis

### 3.1 Tailwind: v3 (bukan v4)

`package.json` sebelumnya memasang `tailwindcss@^3.2.1` **dan** `@tailwindcss/vite@^4.0.0`
sekaligus. Keduanya tidak kompatibel: v4 memakai `@import "tailwindcss"` + plugin Vite,
sedangkan repo ini memakai `@tailwind base/components/utilities` + PostCSS.

**Keputusan:** pakai Tailwind v3 secara penuh.

- Alasan: seluruh scaffold Breeze (49 pemakaian `dark:`), `tailwind.config.js` berbasis
  `content`/`theme.extend`, dan `postcss.config.js` sudah v3. Migrasi ke v4 akan menyentuh
  hampir semua file UI tanpa manfaat produk.
- Tindakan: `@tailwindcss/vite` dihapus, `tailwindcss` dipin ke `^3.4.19` (versi terpasang).
- Konsekuensi: konfigurasi tema, plugin, dan `darkMode: 'class'` mengikuti API v3.

### 3.2 Storage: private untuk dokumen, public hanya untuk aset publik

Disk `local` mengarah ke `storage/app/private` (default Laravel 12) dan dipakai untuk
semua dokumen pengguna (CV sumber, hasil ekstraksi, dokumen hasil generasi, file ekspor).

- **CV dan turunannya tidak boleh masuk disk `public`** — isinya PII.
- `public/storage` → `storage/app/public` sudah dibuat via `php artisan storage:link`,
  hanya untuk aset non-sensitif (mis. avatar atau contoh template).
- Unduhan file privat harus lewat route terotorisasi (`Storage::disk('local')->download()`
  di dalam controller yang memeriksa ownership), bukan URL langsung.
- Disk `local` punya `serve => true`, artinya Laravel bisa menyajikan file privat lewat
  route bertanda tangan; jangan mengandalkan ini tanpa authorization policy.

### 3.3 Struktur kode

Mengikuti `PROJECT_PLAN.md` §7: controller tipis, domain logic di service/action class,
validasi di Form Request, otorisasi di Policy, proses lama lewat queue job.

## 4. Kondisi implementasi saat ini

- Model: `User` saja.
- Controller: `ProfileController` + `Auth/*` (Breeze).
- Policy / Service / Job / Form Request domain: belum ada.
- Migration: `users`, `cache`, `jobs` (semua `Ran`).
- Halaman: `Welcome` (default Laravel), `Dashboard` (placeholder), auth, profile.

## 5. Risiko terbuka

| Risiko | Dampak | Rencana |
|---|---|---|
| Belum ada `AGENTS.md` | Agent berikutnya tidak tahu konvensi repo | Tulis sebelum Phase 2 |
| Library PDF/DOCX belum dipilih | Menentukan desain Phase 3 & 7 | Evaluasi di awal Phase 3 (smalot/pdfparser, PhpWord) |
| Renderer PDF belum dipilih | Menentukan kualitas ekspor | Evaluasi di Phase 7; uji selectable text + page break |
| Provider LLM belum dikonfigurasi | Phase 3 & 5 terblokir | Tentukan provider + env key sebelum Phase 3 |
| Queue worker tidak otomatis jalan di lokal | Job AI menumpuk di tabel `jobs` | Dokumentasikan `php artisan queue:work` |
| Branch `develop` belum ada | Alur kerja berbeda dari proyek lain | Buat saat Phase 1 dimulai |
