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

(Section ini menggambarkan kondisi per Phase 3. Lihat bagian bawah untuk catatan Phase 1–3.)

- Model: `User`, `CareerProfile` + 6 section profil, `ProfileDocument` (Phase 3).
- Controller: `ProfileController` + `Auth/*` (Breeze), `CareerProfileController`, 6 section controller, `ProfileDocumentController` (import CV).
- Policy: `CareerProfilePolicy`, `ProfileDocumentPolicy`. Service: `CareerProfileService`, `ProfileCompletenessService`, `ProfileSortOrderService`, `CvTextExtractor`, `CvProfileMergeService`, `Ai\*` (Phase 3).
- Job: `ExtractCvDocumentJob` (queue `database`, timeout 300s).
- Migration: `users`, `cache`, `jobs`, 7 tabel profil, `profile_documents`.
- Halaman: `Welcome`, `Dashboard`, auth, profile, `CareerProfile/Index`, `CareerProfile/Import/{Index,Review}`.

## 4a. Catatan Phase 1–3

### Phase 1 — fondasi UI
- Design tokens di `tailwind.config.js` + `resources/css/app.css` (`btn-*`, `card`, `field-*`, `badge-*`), `AuthenticatedLayout` (sidebar + mobile drawer), `GuestLayout` disamakan, landing page produk.

### Phase 2 — career profile
- Satu `career_profiles` per user (unique `user_id`), 6 section child table dengan `sort_order` + index `(career_profile_id, sort_order)`.
- Otorisasi per-baris lewat `AuthorizesProfileSection::ensureOwned()`; reorder memvalidasi daftar id persis milik profil (`ProfileSortOrderService`).
- `ProfileCompletenessService` menghitung 7 section (termasuk kontak dianggap lengkap hanya bila ada nama + minimal email/telepon).

### Phase 3 — CV import & extraction review
- **Upload:** `POST /career-profile/documents` menerima `pdf,docx` maks 10 MB (`StoreProfileDocumentRequest`), disimpan di disk `local` (`cv-uploads/`) dengan nama acak; nama asli hanya metadata.
- **Ekstraksi teks:** `CvTextExtractor` — PDF via `smalot/pdfparser` (text layer; scan → string kosong → status `failed` dengan kode `no_text`), DOCX via `phpoffice/phpword` (walk sections/tabel/cells). Teks < 100 karakter dianggap tidak cukup.
- **AI:** binding provider-agnostic `CvExtractionProvider` → `OpenAiCompatibleCvExtraction` (config `services.ai`, OpenAI-compatible, 9Router OK). Output divalidasi ketat oleh `CvDraftValidator` (schema, cap panjang, URL/email di-null-kan jika invalid, tanggal unparseable → null, confidence 0–100) — model tidak dipercaya; data yang tidak sesuai schema dibuang.
  - **Catatan gateway:** 9Router membalas **SSE stream** walau request memakai `stream:false`; provider mendeteksi dan merakit `choices[].delta.content` dari baris `data:` sebelum validasi. Jangan hapus jalur ini.
- **Queue:** `ExtractCvDocumentJob` — status `pending → processing → completed|failed`; error disimpan sebagai kode (`no_text`, `provider_unavailable`, `invalid_output`, `extraction_failed`, `file_missing`) dan diterjemahkan ke pesan Indonesia di UI. Worker harus jalan manual: `php artisan queue:work --tries=1`.
- **Review:** `GET /career-profile/documents/{document}` merender `CareerProfile/Import/Review` — halaman polling status via `?status=1` (JSON), lalu form per section: tiap item punya checkbox "Sertakan", field bisa diedit, submit memakai `transform()` agar payload dibangun dari yang dipilih/diedit.
- **Merge:** `POST .../confirm` (`CvProfileMergeService`) — hanya section yang dikirim yang diterapkan; contact/summary di-overwrite, baris list **di-append** (sort_order setelah max existing), data lama tidak dihapus; validasi ulang server-side.
- **Hapus:** `DELETE .../{document}` menghapus record + file (`ProfileDocument::booted` → `Storage::disk('local')->delete`).
- **Download:** `GET .../download` lewat route terotorisasi (private disk), owner-only.
- **Nav:** item "Import CV" ditambahkan di `AuthenticatedLayout`.
- Flash message di-share lewat `HandleInertiaRequests` (`flash.success` / `flash.error`) — sebelumnya halaman Phase 2 sudah memakai tapi belum pernah terisi.
- Test: `tests/Feature/CvImportTest.php` (11 tc) + `tests/Unit/CvDraftValidatorTest.php` (6 tc).

## 5. Risiko terbuka

| Risiko | Dampak | Rencana |
|---|---|---|
| Renderer PDF belum dipilih | Menentukan kualitas ekspor | Evaluasi di Phase 7; uji selectable text + page break |
| Queue worker tidak otomatis jalan di lokal | Job AI menumpuk di tabel `jobs` | Dokumentasikan `php artisan queue:work` (sudah di AGENTS.md) |
| Provider gateway bisa balas SSE tanpa diminta | Parser provider gagal bila dihapus | Jalur SSE di `messageContent()` sudah ada + catatan di §4a |
| AI usage/rate-limit belum dibatasi | Biaya tak terkontrol saat banyak impor | Phase 8 (§4.10 PROJECT_PLAN) |
| PDF scan tanpa OCR | Import gagal dengan pesan jelas | OCR = Phase 9; saat ini `no_text` by design |
