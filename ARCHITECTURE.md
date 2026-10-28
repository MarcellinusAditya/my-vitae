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

(Section ini menggambarkan kondisi per Phase 5. Lihat bagian bawah untuk catatan Phase 1–5.)

- Model: `User`, `CareerProfile` + 6 section profil, `ProfileDocument` (P3), `JobApplication`, `JobRequirementAnalysis` (P4), `GeneratedDocument`, `AiUsageLog` (P5).
- Controller: `Auth/*`, `ProfileController`, `CareerProfileController`, 6 section controller, `ProfileDocumentController`, `JobApplicationController`, `GeneratedDocumentController`.
- Policy: `CareerProfilePolicy`, `ProfileDocumentPolicy`, `JobApplicationPolicy`, `GeneratedDocumentPolicy`.
- Service: `CareerProfileService`, `ProfileCompletenessService`, `ProfileSortOrderService`, `CvTextExtractor`, `CvProfileMergeService`, `JobApplicationService`, `ProfileSnapshotService`, `DocumentGenerationService`, `Ai\*`.
- Job: `ExtractCvDocumentJob`, `AnalyzeJobApplicationJob`, `GenerateDocumentJob`.
- Migration: 16 tabel domain (7 profil, `profile_documents`, `job_applications`, `job_requirement_analyses`, `generated_documents`, `ai_usage_logs`) + tabel framework.
- Halaman: `Welcome`, `Dashboard`, auth, profile, `CareerProfile/Index`, `CareerProfile/Import/*`, `Applications/*`, `Documents/*`.

## 4a. Catatan Phase 1–5

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
- **Queue:** `ExtractCvDocumentJob` — status `pending → processing → completed|failed`; error disimpan sebagai kode dan diterjemahkan ke pesan Indonesia di UI.
- **Review:** `GET /career-profile/documents/{document}` merender `CareerProfile/Import/Review` — halaman polling status via `?status=1` (JSON), lalu form per section: tiap item punya checkbox "Sertakan", field bisa diedit, submit memakai `transform()` agar payload dibangun dari yang dipilih/diedit.
- **Merge:** `POST .../confirm` (`CvProfileMergeService`) — hanya section yang dikirim yang diterapkan; contact/summary di-overwrite, baris list **di-append** (sort_order setelah max existing), data lama tidak dihapus; validasi ulang server-side.
- **Hapus:** `DELETE .../{document}` menghapus record + file (`ProfileDocument::booted` → `Storage::disk('local')->delete`). **Download:** route terotorisasi (private disk), owner-only.

### Phase 4 — job application intake & job-fit analysis
- **Intake:** CRUD lamaran (`JobApplicationController`) dengan status workflow `draft → ready_to_apply → applied → interview → rejected → offer` (label Indonesia di `JobApplication::STATUSES`). JD wajib ≥ 50 karakter (`JobApplicationRequest`). Duplikasi lamaran menyalin isi lowongan tanpa analisis.
- **Klien LLM bersama:** `Services/Ai/ChatCompletionClient` — satu jalur untuk semua panggilan chat completion; menangani **SSE stream dari gateway** (9Router mengirim `data:` deltas walau `stream:false`); `OpenAiCompatibleCvExtraction` (Phase 3) kini memakainya juga.
- **Analisis:** `JobFitAnalysisProvider` → `OpenAiCompatibleJobFitAnalysis`. Output berisi `requirements[]` (`category`, `priority`, `match_status` matched/partial/not_evidenced, `evidence_refs[]`, `recommendation`), `keyword_suggestions[]`, `seniority`, `summary`. Tidak ada skor tunggal/prediksi ATS.
- **GUARDRAIL BUKTI (penting):** `JobFitAnalysisValidator::normalize()` membuang setiap `evidence_ref` yang tidak menunjuk baris nyata di snapshot; requirement yang kehilangan semua bukti **diturunkan ke `not_evidenced`** (`demoted` dihitung); `supported_by_profile` pada keyword dipaksa `false` bila tidak ada bukti valid. Job memanggil `normalize()` **dua kali** (provider juga memanggilnya) sebagai defense-in-depth — provider yang melewati validator tetap tidak bisa menyimpan klaim tak terbukti. Jangan hapus langkah ini.
- **Snapshot reproducibility:** `ProfileSnapshotService` membangun snapshot profil ber-ID saat analisis diminta (`JobApplicationService::queueAnalysis`); snapshot + `job_snapshot` disimpan di row analisis, jadi hasil lama tetap menjelaskan dirinya walau profil/JD kemudian diubah. Profil kosong → `empty_profile`.
- **Queue & polling:** `AnalyzeJobApplicationJob` (status `pending → processing → completed|failed`), UI halaman `Applications/Show` polling `GET /applications/{application}/analysis-status`.
- **UI:** tab **Kecocokan** (3 grup kartu: cocok/sebagian/belum ada bukti, bukti + saran per requirement, kata kunci didukung vs belum) dan **Detail lowongan**. Dashboard memakai hitungan lamaran nyata + 5 lamaran terbaru.
- **Catatan implementasi:** hindari `latestOfMany()` untuk daftar (kolom ambigu saat select terbatas) — gunakan `analyses()->first()` (`analyses()` sudah `latest('id')`).
- Test: `JobApplicationTest` (12 tc) + `JobFitAnalysisTest` (7 tc) + `JobFitAnalysisValidatorTest` (7 tc).

### Phase 5 — AI CV & cover letter generation
- **Skema:** `generated_documents` (type resume/cover_letter, version naik per generate, `content_json`, `job_requirement_analysis_id` sebagai konteks, status/error) + `ai_usage_logs` (operation, provider, model, token, durasi, status — **tanpa prompt/PII**).
- **Provider:** `DocumentGenerationProvider` → `OpenAiCompatibleDocumentGeneration` (prompt `v2`, tersimpan di `content_json.prompt_version`). Dua operasi: resume & cover letter; analisis job-fit disuntik sebagai prioritas target.
- **GUARDRAIL FAKTA (`DocumentFactValidator`) — inti fase ini:**
  1. **Drop keras** — baris dengan `organization`/`role` (atau institution/project/certification name) yang tidak ada di snapshot dibuang; model tidak bisa mengarang perusahaan/jabatan. Tanggal SELALU diambil dari snapshot, bukan dari model.
  2. **Skill tanpa bukti** dibuang (bukan diisi `skill:` palsu).
  3. **Flag metrik** — angka di bullet/paragraf yang tidak muncul di teks profil masuk `unverified_claims` dan ditampilkan ke user (bukan dihapus diam-diam).
  4. **Cover letter** — sapaan dipaksa netral (`Kepada Tim Rekrutmen,` / `Dear Hiring Manager,`) bila nama penerima tidak diberikan atau model mengarang nama.
  - Job memanggil validator **dua kali** (provider + job) sebagai defense-in-depth.
- **Versi:** regenerate membuat row baru dengan `version+1`; versi lama tidak disentuh. Edit manual menyimpan ke versi aktif dan **mempertahankan** `unverified_claims` sebagai metadata.
- **UI:** `Documents/Index` (semua dokumen, filter tipe, badge jumlah klaim perlu dicek), `Documents/Show` (editor per bagian + badge bukti per pengalaman + tombol regenerate per bagian/versi baru), tab **Dokumen** di halaman lamaran (tombol Buat CV / Cover Letter, disabled sampai analisis selesai).
- **Prasyarat:** generate butuh analisis job-fit `completed` → kalau belum, status `analysis_required`.
- Test: `DocumentFactValidatorTest` (8 tc) + `DocumentGenerationTest` (10 tc).

## 5. Risiko terbuka

| Risiko | Dampak | Rencana |
|---|---|---|
| Renderer PDF belum dipilih | Menentukan kualitas ekspor | Evaluasi di Phase 7; uji selectable text + page break |
| Queue worker tidak otomatis jalan di lokal | Job AI menumpuk di tabel `jobs` | Dokumentasikan `php artisan queue:work` (sudah di AGENTS.md) |
| Provider gateway bisa balas SSE tanpa diminta | Parser provider gagal bila dihapus | Jalur SSE di `ChatCompletionClient` + catatan §4a |
| AI usage/rate-limit belum dibatasi | Biaya tak terkontrol saat banyak impor/analisis | Phase 5/8 (§4.10 PROJECT_PLAN) |
| PDF scan tanpa OCR | Import gagal dengan pesan jelas | OCR = Phase 9; saat ini `no_text` by design |
| Analisis memakai model yang sama dengan ekstraksi | Kualitas job-fit bergantung `OPENAI_MODEL` | Ganti model cukup lewat env; prompt terpisah per operasi |
| Metrik tanpa bukti tetap tersimpan (hanya di-flag) | User bisa lupa menghapus klaim | Badge `unverified_claims` di UI; ekspor (P7) harus memblokir/metandai |
| Regenerate per bagian masih membuat versi penuh | Boros token, versi menumpuk | Phase 6 (version history proper) / optimasi nanti |
| Belum ada kuota AI per user | Biaya tak terkontrol | `ai_usage_logs` sudah mencatat; enforcement di Phase 8 |
