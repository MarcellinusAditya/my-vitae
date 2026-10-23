# AI CV & Cover Letter Builder — Product Requirements dan Roadmap

> Dokumen ini adalah spesifikasi awal untuk AI coding agent. Bangun aplikasi secara bertahap per fase, bukan sekaligus. Sebelum mengubah kode, periksa repository dan dokumentasikan keputusan teknis yang relevan.

## 1. Ringkasan Produk

**Nama sementara:** CareerCraft AI

Aplikasi web untuk membantu pengguna menyusun CV/resume dan surat lamaran kerja yang relevan dengan lowongan tertentu. Pengguna dapat memasukkan data secara manual atau mengimpor CV lama. Sistem mengekstrak informasi, meminta pengguna memverifikasi hasil ekstraksi, lalu menggunakan LLM untuk menyesuaikan dokumen dengan job description (JD) dan job qualifications.

### Tujuan utama
- Mengurangi waktu yang dibutuhkan untuk membuat CV dan surat lamaran yang spesifik untuk setiap lowongan.
- Menyajikan CV yang mudah dipindai oleh Applicant Tracking System (ATS): struktur sederhana, heading standar, teks yang dapat dibaca, dan kata kunci relevan yang benar-benar didukung pengalaman pengguna.
- Membantu pengguna menemukan kecocokan dan kesenjangan antara profil mereka dan kualifikasi lowongan.
- Menghasilkan dokumen yang dapat diedit, disimpan, diduplikasi untuk lowongan lain, dan diekspor.

### Batasan dan prinsip produk
- **Jangan menjanjikan CV pasti lolos ATS atau meningkatkan peluang dengan angka tertentu.** Sistem hanya membantu meningkatkan relevansi, keterbacaan, dan kualitas dokumen.
- Jangan pernah mengarang pengalaman, perusahaan, jabatan, pendidikan, sertifikasi, tanggal, metrik pencapaian, atau skill.
- Kata kunci lowongan hanya boleh dimasukkan bila didukung data pengguna. Tampilkan saran untuk mengisi bukti yang belum tersedia, bukan mengarangnya.
- Pengguna harus dapat melihat, mengedit, dan menyetujui hasil ekstraksi dan konten AI sebelum ekspor.
- Pisahkan fakta profil pengguna dari teks hasil generasi AI agar sumber data tetap terlacak.
- CV dan data pribadi adalah data sensitif: terapkan akses privat, validasi file, pembatasan ukuran, dan penghapusan data.

## 2. Target Pengguna dan Use Case

1. Fresh graduate yang membuat CV pertama atau memperbarui CV lama.
2. Job seeker yang melamar ke banyak perusahaan dan perlu menyesuaikan dokumen untuk tiap lowongan.
3. Profesional yang ingin mengubah penekanan pengalaman sesuai posisi target.
4. Pengguna yang membutuhkan CV berbahasa Indonesia atau Inggris.

### Alur utama pengguna
1. Daftar/login.
2. Buat profil karier atau impor CV lama.
3. Sistem mengekstrak data ke dalam field terstruktur.
4. Pengguna meninjau, memperbaiki, dan mengonfirmasi data.
5. Pengguna membuat aplikasi lamaran baru dengan menempelkan JD dan kualifikasi, atau memasukkan URL lowongan (fitur URL dapat ditunda).
6. Sistem menganalisis persyaratan, memetakan persyaratan terhadap bukti di profil, dan menunjukkan skill/keyword yang cocok serta gap.
7. Pengguna memilih template, bahasa, dan preferensi dokumen.
8. LLM membuat draft CV yang ditargetkan dan surat lamaran.
9. Pengguna mengedit, membandingkan, menyimpan versi, lalu mengekspor ke PDF dan DOCX.
10. Pengguna dapat menduplikasi aplikasi lamaran untuk lowongan lain.

## 3. Ruang Lingkup MVP

### Wajib untuk MVP
- Autentikasi pengguna.
- Profil karier terstruktur yang dapat digunakan ulang.
- Impor CV PDF dan DOCX.
- Ekstraksi informasi dari CV ke data terstruktur, disertai halaman review.
- Form untuk memasukkan detail lowongan: nama perusahaan, posisi, JD, dan kualifikasi.
- Analisis kecocokan profil dengan lowongan.
- Generasi CV tertarget dan surat lamaran menggunakan LLM.
- Editor teks untuk mengubah hasil generasi.
- Template CV ATS-friendly yang sederhana.
- Simpan draft, riwayat versi dasar, dan daftar lamaran.
- Ekspor PDF dan DOCX.
- Batas pemakaian AI, status proses, error handling, dan logging tanpa menyimpan rahasia.
- Penghapusan profil, dokumen sumber, dan lamaran oleh pengguna.

### Bukan bagian MVP (dapat dikembangkan kemudian)
- Scraping lowongan dari berbagai situs.
- Auto-apply ke portal lowongan kerja.
- Integrasi LinkedIn atau job board.
- Optimasi berdasarkan klaim skor ATS dari vendor eksternal.
- Kolaborasi recruiter, review CV oleh manusia, dan marketplace template.
- Pembayaran/subscription, kecuali dibutuhkan untuk validasi bisnis awal.
- Analitik prediksi peluang diterima.
- OCR untuk semua jenis scan. Tambahkan hanya jika ekstraksi PDF/DOCX biasa terbukti tidak cukup.

## 4. Fitur Produk

### 4.1 Akun dan Dashboard
- Registrasi, login, logout, reset password.
- Dashboard berisi profil karier, lamaran terbaru, dan dokumen yang pernah dibuat.
- Daftar lamaran dengan perusahaan, posisi, tanggal, status, dan aksi duplikasi.
- Status lamaran opsional: Draft, Ready to Apply, Applied, Interview, Rejected, Offer.

### 4.2 Career Profile / Master Resume
Simpan fakta profil yang dapat digunakan kembali lintas lamaran:
- Informasi kontak: nama, email, telepon, kota/lokasi, tautan portofolio/GitHub/LinkedIn (opsional).
- Ringkasan profesional.
- Pengalaman kerja/magang/proyek: perusahaan/organisasi, jabatan, lokasi, tanggal, deskripsi, pencapaian.
- Pendidikan.
- Skill, dikelompokkan bila diperlukan.
- Sertifikasi dan pelatihan.
- Proyek, organisasi, penghargaan, bahasa, dan publikasi (opsional).
- Preferensi: bahasa dokumen, gaya penulisan, panjang CV, format tanggal.

**Ketentuan:** setiap bagian dapat dibuat, diedit, dihapus, dan disusun ulang. Jangan mewajibkan field opsional. Simpan fakta terstruktur sebagai sumber kebenaran, bukan hanya teks CV hasil render.

### 4.3 Import CV Lama
- Unggah PDF atau DOCX; validasi MIME type, ekstensi, ukuran, dan kepemilikan.
- MVP: batasi file, misalnya 10 MB per file; nilai final dapat dikonfigurasi.
- Ekstrak teks dan parsing ke struktur profil.
- Tampilkan hasil per bagian dengan status confidence bila provider mendukungnya.
- Tandai field yang tidak yakin atau ambigu untuk review.
- Jangan otomatis menimpa data profil lama. Beri opsi untuk menggabungkan, mengganti field tertentu, atau membatalkan.
- Simpan dokumen sumber hanya selama diperlukan; berikan aksi hapus.
- Bila PDF berupa scan dan tidak memiliki teks, tampilkan pesan jelas; OCR menjadi peningkatan terpisah.
- Tangani file rusak, terenkripsi, kosong, atau format yang tidak didukung dengan error yang mudah dipahami.

### 4.4 Job Description & Qualification Intake
- Input nama perusahaan, posisi, lokasi (opsional), JD, kualifikasi, bahasa, dan catatan tambahan.
- Pisahkan tanggung jawab, kualifikasi wajib, kualifikasi yang diutamakan, skill, tools, dan senioritas jika dapat diidentifikasi.
- Simpan teks lowongan apa adanya sebagai sumber.
- Jika parsing AI tidak yakin, tampilkan hasil sebagai saran yang dapat diedit.
- Fitur impor URL lowongan hanya fase lanjutan. Jangan mengandalkan scraping yang melanggar ketentuan situs; utamakan paste teks dan dukungan URL yang eksplisit serta aman.

### 4.5 Job Fit Analysis
Tampilkan analisis yang transparan, bukan klaim prediksi diterima:
- **Matched:** persyaratan yang didukung bukti di profil.
- **Partially matched:** ada bukti yang berdekatan, tetapi perlu konfirmasi pengguna.
- **Missing / not evidenced:** belum ditemukan bukti di profil.
- Kata kunci yang relevan dan konteks pengguna yang mendukungnya.
- Rekomendasi perbaikan yang jujur, misalnya menambahkan detail proyek yang memang pernah dikerjakan.
- Bukti sumber untuk tiap kecocokan, misalnya pengalaman/proyek/skill yang menjadi dasar.
- Hindari satu skor numerik sebagai klaim “peluang lolos ATS”. Jika skor ringkasan ditambahkan, jelaskan bahwa itu heuristik internal dan tampilkan komponennya.

### 4.6 AI CV Generator
- Generate berdasarkan profil terverifikasi + persyaratan lowongan + preferensi pengguna.
- Pilih bahasa Indonesia atau Inggris.
- Pilih panjang: ringkas satu halaman atau detail dua halaman (opsi, bukan jaminan layout).
- Susun bagian sesuai relevansi dan pengalaman pengguna.
- Prioritaskan bullet yang menunjukkan tindakan, konteks, dan hasil bila data hasil tersedia.
- Optimasi istilah agar selaras dengan JD tanpa memasukkan skill/pengalaman yang tidak benar.
- Sediakan regenerasi per bagian, bukan harus mengulang seluruh CV.
- Sediakan “lihat sumber” atau referensi profil untuk klaim yang dihasilkan.
- Jangan mengubah fakta seperti tanggal, angka, jabatan, dan nama perusahaan tanpa persetujuan.
- Template ATS-friendly: satu kolom, heading standar, tanpa informasi penting di header/footer, tanpa tabel kompleks, grafik, ikon, atau skill bar.

### 4.7 AI Cover Letter Generator
- Menggunakan profil terverifikasi, posisi, perusahaan, dan JD.
- Dapat mengatur bahasa dan tone: profesional, formal, atau hangat-profesional.
- Membuat surat yang spesifik untuk lowongan, tidak generik.
- Menjelaskan hubungan antara pengalaman yang benar-benar dimiliki dan kebutuhan posisi.
- Jangan mengarang nama perekrut, pengalaman, referensi, atau klaim perusahaan. Jika nama penerima tidak ada, gunakan sapaan netral.
- Editor teks, regenerasi paragraf, dan ekspor.

### 4.8 Editor, Template, dan Versioning
- Editor yang nyaman untuk CV dan surat lamaran.
- Simpan otomatis dengan indikator “Saving / Saved / Failed”.
- Undo/redo bila editor mendukungnya.
- Riwayat versi dengan timestamp dan opsi restore.
- Preview hasil sebelum ekspor.
- Pengaturan urutan bagian dan pilihan template ATS.
- Versi tiap lamaran harus independen: perubahan pada master profile tidak otomatis mengubah dokumen lamaran lama tanpa persetujuan.

### 4.9 Export
- PDF untuk dibagikan.
- DOCX agar pengguna bisa melanjutkan edit di Microsoft Word/LibreOffice.
- Pastikan teks dapat dipilih dan diekstrak dari PDF, urutan baca logis, font terbaca, dan tautan tidak rusak.
- Lakukan uji ekstraksi teks pada file hasil ekspor.
- Tampilkan peringatan bila konten berpotensi terlalu panjang atau terpotong.
- Nama file yang aman dan tidak memuat karakter ilegal.

### 4.10 AI Usage dan Observability
- Batas ukuran input dan output.
- Rate limit per pengguna.
- Catat status, durasi, model/provider, token usage jika tersedia, dan kategori error.
- Jangan log API key, CV lengkap, atau data pribadi mentah.
- Pesan error yang dapat ditindaklanjuti; retry hanya untuk error sementara.
- Siapkan antarmuka provider agar model LLM dapat diganti tanpa mengubah domain logic.

## 5. UX / Halaman

1. **Landing page:** manfaat, cara kerja, contoh dokumen, CTA.
2. **Auth:** register, login, reset password.
3. **Dashboard:** daftar lamaran, ringkasan profil, tombol buat lamaran.
4. **Career Profile:** formulir profil terstruktur.
5. **Import CV:** upload, status ekstraksi, review dan konfirmasi data.
6. **Create Application Wizard:**
   - Step 1: detail perusahaan dan posisi.
   - Step 2: paste JD dan kualifikasi.
   - Step 3: review hasil analisis fit.
   - Step 4: pilih bahasa/template dan generate.
7. **Application Workspace:** tab CV, Cover Letter, Job Fit, dan Job Details.
8. **Document Editor:** edit, preview, version history, export.
9. **Settings:** bahasa default, privasi, hapus akun/data, preferensi AI.

### UX principles
- Proses dibuat bertahap dengan progress yang jelas.
- Jangan mengunci pengguna pada hasil AI; semua konten dapat direvisi.
- Tampilkan sumber klaim dan gap dengan bahasa netral, tidak menghakimi.
- Bedakan fakta terverifikasi, saran AI, dan field yang belum diisi.
- Responsive desktop dan mobile; optimalkan editor untuk desktop tetapi tetap usable di mobile.
- Accessibility dasar: label form, keyboard navigation, kontras, focus states, dan pesan validasi.

## 6. Tech Stack yang Direkomendasikan

Pilih satu repository Laravel + Inertia + React, agar tidak perlu memelihara backend API dan frontend terpisah pada MVP.

### Backend
- **PHP 8.2**
- **Laravel 12** (cek kompatibilitas dependency saat setup; bila environment tidak mendukung, dokumentasikan versi alternatif yang kompatibel)
- Laravel Breeze atau starter kit Laravel yang sesuai untuk autentikasi Inertia React.
- Laravel Policies untuk otorisasi kepemilikan data.
- Laravel Form Requests untuk validasi request.
- Service classes untuk domain logic; controller tetap tipis.
- Laravel Queue untuk proses ekstraksi dokumen, generasi AI, dan ekspor jika prosesnya lama.
- Laravel Scheduler hanya bila ada tugas terjadwal.
- Laravel Storage untuk file; private disk untuk CV dan dokumen pengguna.
- Laravel HTTP Client untuk memanggil LLM API.

### Frontend
- **React + TypeScript**
- **Inertia.js** untuk integrasi Laravel dan React tanpa memisahkan aplikasi menjadi dua repository.
- **Vite**
- **Tailwind CSS**
- **shadcn/ui** untuk komponen UI bila kompatibel dengan starter kit yang dipilih.
- React Hook Form + Zod untuk form kompleks dan validasi sisi klien (validasi server tetap wajib).
- Editor: pilih TipTap jika membutuhkan rich-text editing; untuk MVP, textarea/editor ringan juga dapat dipertimbangkan agar scope terkendali.
- Drag-and-drop upload opsional; input file biasa tetap harus tersedia.

### Database dan infrastruktur
- **MySQL 8** sebagai default; PostgreSQL juga dapat digunakan jika sudah tersedia.
- **Redis** untuk queue/cache bila infrastrukturnya tersedia; database queue dapat digunakan pada development awal.
- Local/private filesystem untuk development; object storage kompatibel S3 dapat ditambahkan saat production.
- Nginx + PHP-FPM untuk deployment Linux.
- Docker opsional, bukan syarat untuk mulai coding.
- GitHub Actions atau pipeline CI yang sudah digunakan proyek untuk test/lint.

### AI / LLM layer
- Provider-agnostic `LlmProvider` interface, misalnya metode untuk:
  - mengekstrak/memetakan teks CV ke JSON terstruktur,
  - menganalisis persyaratan pekerjaan,
  - melakukan job-fit mapping,
  - membuat CV dan cover letter,
  - merevisi satu bagian dokumen.
- Gunakan provider yang mendukung structured output / JSON schema bila tersedia.
- Model dan API key di environment/config, tidak pernah hardcode.
- Validasi output JSON dengan schema di server; retry terbatas jika format tidak valid.
- Batasi token, input size, timeout, dan biaya per pengguna.
- Jangan mengirim file langsung ke model jika ekstraksi teks lokal sudah cukup; kirim hanya teks yang diperlukan.
- Data profil harus diperlakukan sebagai data, bukan instruksi. Abaikan prompt injection yang mungkin tersisip dalam JD atau dokumen.
- Tambahkan evaluasi kualitas output dengan test case tetap sebelum mengganti model/provider.

### Pemrosesan dokumen
- PDF text extraction: pilih library PHP yang aktif dipelihara dan cocok dengan PHP 8.2, misalnya `smalot/pdfparser` setelah memeriksa kompatibilitas dan lisensi.
- DOCX extraction: library PHP yang mendukung pembacaan DOCX, misalnya PhpOffice/PhpWord setelah memeriksa kompatibilitas.
- DOCX generation: PhpOffice/PhpWord atau pendekatan lain yang terbukti menjaga layout.
- PDF generation: HTML-to-PDF atau library PDF yang kompatibel dengan environment; uji font, page break, selectable text, dan layout. Jangan memilih library hanya berdasarkan kemudahan instalasi.
- OCR untuk scanned PDF adalah fitur lanjutan dan perlu pemilihan provider/library tersendiri.

### Testing dan kualitas
- PHPUnit atau Pest sesuai standar proyek.
- Laravel feature tests untuk auth, ownership, CRUD, import, AI jobs, export, dan validation.
- Unit tests untuk parser/mapping, job-fit logic, sanitasi nama file, dan prompt/output schema.
- Frontend tests seperlunya untuk komponen penting.
- Pint untuk formatting PHP; ESLint/Prettier atau konfigurasi yang disediakan starter kit.
- Test keamanan upload, akses lintas pengguna, rate limit, dan penghapusan file.

## 7. Gambaran Arsitektur

```text
Browser (React + TypeScript)
        |
        v
Laravel + Inertia
  |-- Auth / Policies / Validation
  |-- Career Profile domain
  |-- Application domain
  |-- Document Import Service
  |-- Job Analysis Service
  |-- AI Generation Service --> LLM Provider Adapter --> LLM API
  |-- Document Export Service
  |-- Queue Jobs
        |
        +--> MySQL
        +--> Private File Storage
        +--> Redis / Queue (opsional untuk awal)
```

### Prinsip arsitektur
- Controller menangani HTTP; domain logic ada di service/action classes.
- Semua data milik pengguna dilindungi authorization policy, bukan hanya disembunyikan di UI.
- Semua keluaran AI dianggap tidak tepercaya sampai lolos validasi.
- Proses yang lama berjalan melalui queue dan memiliki status: pending, processing, completed, failed.
- Simpan input lowongan dan snapshot profil yang digunakan untuk menghasilkan dokumen agar versi lama dapat direproduksi.
- Hindari overengineering: jangan membuat microservices untuk MVP.

## 8. Model Data Awal

Nama tabel bersifat usulan dan dapat disesuaikan setelah inspeksi repository.

### `users`
- `id`, `name`, `email`, `password`, timestamps.

### `career_profiles`
- `id`, `user_id`, `title`, `default_language`, timestamps.
- Satu user dapat memiliki satu atau beberapa profil karier jika dibutuhkan.

### `profile_contacts`
- `id`, `career_profile_id`, `full_name`, `email`, `phone`, `location`, `linkedin_url`, `portfolio_url`, `github_url`, timestamps.

### `profile_experiences`
- `id`, `career_profile_id`, `organization`, `job_title`, `employment_type`, `location`, `start_date`, `end_date`, `is_current`, `description`, `achievements` (JSON atau child table), `sort_order`, timestamps.

### `profile_educations`
- `id`, `career_profile_id`, `institution`, `degree`, `field_of_study`, `start_date`, `end_date`, `description`, `sort_order`, timestamps.

### `profile_skills`
- `id`, `career_profile_id`, `name`, `category`, `proficiency` (opsional), `sort_order`, timestamps.

### `profile_projects`
- `id`, `career_profile_id`, `name`, `role`, `description`, `technologies` (JSON atau child table), `url`, `start_date`, `end_date`, `sort_order`, timestamps.

### `profile_certifications`
- `id`, `career_profile_id`, `name`, `issuer`, `issue_date`, `expiry_date`, `credential_url`, timestamps.

### `profile_documents`
- `id`, `career_profile_id`, `user_id`, `original_filename`, `storage_path`, `mime_type`, `file_size`, `extraction_status`, `extraction_error_code`, timestamps.
- Jangan menyimpan isi CV mentah di log. Hapus file dari storage saat dokumen dihapus.

### `job_applications`
- `id`, `user_id`, `career_profile_id`, `company_name`, `job_title`, `job_location`, `job_description`, `job_qualifications`, `language`, `status`, timestamps.

### `job_requirement_analyses`
- `id`, `job_application_id`, `analysis_json`, `model_name`, `status`, timestamps.
- JSON memuat persyaratan, kategori, tingkat prioritas, status match, dan evidence references.

### `generated_documents`
- `id`, `job_application_id`, `type` (`resume` / `cover_letter`), `title`, `content_json` atau `content_text`, `template_key`, `language`, `version`, `generation_status`, `model_name`, timestamps.
- Simpan konten yang dapat diedit, bukan hanya file hasil ekspor.

### `document_exports`
- `id`, `generated_document_id`, `format`, `storage_path`, `status`, timestamps.
- Terapkan kebijakan retensi file ekspor dan hapus file saat parent dihapus.

### `ai_usage_logs`
- `id`, `user_id`, `job_application_id` nullable, `provider`, `model`, `operation`, `input_tokens` nullable, `output_tokens` nullable, `duration_ms`, `status`, `error_code`, timestamps.
- Jangan masukkan prompt lengkap atau PII ke log.

### Relasi dan integritas
- Gunakan foreign key dan index yang sesuai.
- Terapkan cascade/restrict secara sadar; penghapusan data harus konsisten sampai file di storage.
- Setiap query resource milik pengguna harus dibatasi berdasarkan `user_id` atau relasi ownership yang tervalidasi.
- Tambahkan soft delete hanya bila ada kebutuhan pemulihan yang jelas; pastikan file retention sesuai kebijakan.

## 9. Alur AI dan Kontrak Output

### Pipeline import CV
1. Validasi upload dan simpan sementara pada private storage.
2. Ekstrak teks dari PDF/DOCX.
3. Bersihkan whitespace dan batas panjang teks.
4. Kirim teks yang diperlukan ke LLM untuk dipetakan ke JSON schema.
5. Validasi JSON dan field.
6. Simpan sebagai draft extraction, bukan langsung ke profil aktif.
7. Tampilkan review UI.
8. Setelah dikonfirmasi pengguna, gabungkan ke profil.
9. Hapus file sementara sesuai kebijakan retensi.

### Pipeline job-fit
1. Normalisasi teks lowongan.
2. LLM mengidentifikasi persyaratan, jenis (required/preferred), dan kategori.
3. Sistem mencocokkan setiap persyaratan dengan evidence pada profil.
4. LLM boleh membantu mengklasifikasikan relevansi, tetapi tidak boleh menciptakan evidence.
5. Tampilkan match, partial, dan not evidenced dengan referensi sumber.

### Pipeline generasi dokumen
1. Ambil snapshot profil terverifikasi dan data lowongan.
2. Ambil analisis job-fit.
3. Buat structured draft per bagian.
4. Validasi bahwa klaim faktual memiliki referensi ke profil atau telah ditulis/diizinkan pengguna.
5. Tampilkan draft untuk review.
6. Simpan versi setelah generate dan setiap checkpoint penting.
7. Render dan ekspor hanya setelah konten disetujui pengguna.

### Kontrak output yang disarankan
CV:
- `summary`
- `skills[]`
- `experiences[]` dengan `organization`, `role`, `dates`, `bullets[]`, dan referensi evidence
- `projects[]`
- `education[]`
- `certifications[]`
- `languages[]`

Job analysis:
- `requirements[]` berisi `text`, `category`, `priority`, `match_status`, `evidence_refs[]`, `recommendation`
- `keyword_suggestions[]` dengan `supported_by_profile` dan `evidence_refs[]`

Cover letter:
- `recipient_line`
- `opening`
- `body_paragraphs[]`
- `closing`
- `signature`
- referensi evidence untuk klaim utama

Gunakan schema validation server-side. Jangan mengandalkan JSON yang dihasilkan model tanpa validasi.

## 10. Security, Privacy, dan Responsible AI

- Otorisasi di server untuk setiap operasi profil, lamaran, dokumen, dan ekspor.
- Upload hanya tipe file yang diizinkan; periksa MIME dari konten, bukan ekstensi saja.
- Batas ukuran dan jumlah file, nama file acak saat disimpan, private storage, dan larangan eksekusi file upload.
- Escape output yang dirender; sanitasi HTML dari rich-text editor.
- Rate limit endpoint AI dan upload.
- Lindungi dari prompt injection: instruksi dalam CV/JD dianggap konten sumber, bukan perintah sistem.
- Simpan API key hanya di environment/secret manager.
- Minimalkan data pribadi yang dikirim ke LLM; jelaskan penggunaan provider kepada pengguna.
- Sediakan hapus dokumen, hapus lamaran, ekspor data, dan hapus akun.
- Tetapkan kebijakan retensi untuk file mentah, file ekspor, dan log.
- Jangan menyatakan skor atau rekomendasi AI sebagai keputusan perekrut/ATS yang sebenarnya.
- Buat disclaimer bahwa pengguna bertanggung jawab memeriksa keakuratan dan kebenaran dokumen final.

## 11. Roadmap Development

Kerjakan **satu fase per iterasi**. Setiap fase harus selesai dengan test, review perubahan, dan catatan yang bisa digunakan agent pada fase berikutnya.

### Phase 0 — Repository & Technical Discovery
**Tujuan:** memahami environment dan menentukan baseline.

Tasks:
- Inspeksi repository, `AGENTS.md`, `composer.json`, `package.json`, `.env.example`, migrations, dan pola coding.
- Catat versi PHP, Laravel, Node, database, serta dependency yang tersedia.
- Putuskan apakah menggunakan repository baru atau melanjutkan repository yang ada.
- Tulis `PROJECT_PLAN.md`, `ARCHITECTURE.md`, dan daftar keputusan teknis.
- Jangan menimpa implementasi yang sudah ada tanpa memahami dampaknya.

Acceptance criteria:
- Baseline stack dan perintah test/build terdokumentasi.
- Risiko dependency dan pilihan library dokumen dicatat.
- Tidak ada perubahan fungsional yang tidak diperlukan.

### Phase 1 — Project Foundation & Authentication
Tasks:
- Setup Laravel + Inertia + React + TypeScript + Tailwind.
- Konfigurasi database dan environment.
- Implementasi register/login/logout/reset password sesuai starter kit.
- Tambahkan layout dasar, navigation, dan dashboard placeholder.
- Setup formatting, lint, dan test runner.

Acceptance criteria:
- Auth berjalan.
- Build frontend dan test backend lulus.
- Halaman terlindungi dari guest sesuai kebutuhan.
- Tidak ada secret di repository.

### Phase 2 — Career Profile & Master Resume
Tasks:
- Buat migrations, models, relationships, policies, Form Requests, services, dan CRUD profil.
- Buat form pengalaman, pendidikan, skill, proyek, sertifikasi, kontak.
- Implementasi reorder bagian atau item jika diperlukan.
- Validasi ownership untuk semua operasi.
- Tambahkan test CRUD dan akses lintas pengguna.

Acceptance criteria:
- Pengguna dapat membuat dan mengedit master profile.
- User A tidak bisa membaca atau mengubah data User B.
- Field opsional dan data kosong ditangani dengan baik.

### Phase 3 — CV Import & Extraction Review
Tasks:
- Implementasi upload PDF/DOCX ke private storage.
- Implementasi ekstraksi teks.
- Tambahkan LLM parser melalui provider interface dan structured schema.
- Buat queue job bila pemrosesan lama.
- Buat halaman status proses dan review hasil ekstraksi.
- Implementasi merge dengan profil setelah konfirmasi.
- Tangani file gagal, tidak didukung, kosong, dan PDF scan.

Acceptance criteria:
- File valid menghasilkan draft extraction yang dapat diedit.
- Data tidak otomatis menimpa profil tanpa konfirmasi.
- File milik user lain tidak dapat diakses.
- Error dipresentasikan dengan aman dan jelas.

### Phase 4 — Job Application Intake & Job-Fit Analysis
Tasks:
- CRUD lamaran dan form JD/kualifikasi.
- Parsing persyaratan ke kategori dan prioritas.
- Pemetaan persyaratan ke evidence profil.
- Tampilan match/partial/not evidenced beserta bukti.
- Simpan snapshot input dan analisis.

Acceptance criteria:
- Pengguna bisa membuat beberapa lamaran independen.
- Tiap kecocokan menampilkan bukti profil atau menyatakan tidak ada bukti.
- Analisis tidak menyatakan prediksi pasti lolos screening.
- Validasi dan authorization test lulus.

### Phase 5 — AI CV & Cover Letter Generation
Tasks:
- Implementasikan adapter LLM dan prompt versi terkontrol.
- Generate CV terarah berdasarkan snapshot profil dan lowongan.
- Generate cover letter berdasarkan data yang sama.
- Tambahkan schema validation, retry terbatas, timeout, usage limits, dan error state.
- Tambahkan regenerate per bagian.
- Buat guardrail untuk fakta tanpa evidence.
- Simpan generated content dan versi.

Acceptance criteria:
- Hasil mengikuti schema yang ditetapkan.
- Fakta penting dapat dilacak ke profil atau input pengguna.
- Model tidak boleh mengarang pengalaman atau metrik.
- Pengguna dapat mengedit hasil sebelum ekspor.
- Test dengan contoh lowongan dan profil anonim lulus.

### Phase 6 — Document Editor & ATS Templates
Tasks:
- Implementasi editor dokumen dan preview.
- Template ATS satu kolom dengan heading standar.
- Pengaturan urutan bagian.
- Autosave dengan indikator status.
- Version history dan restore.
- Validasi panjang dokumen dan konten overflow.

Acceptance criteria:
- Hasil dapat diedit dan dipreview.
- Draft tidak hilang diam-diam saat save gagal.
- Perubahan master profile tidak diam-diam mengubah dokumen yang sudah dibuat.
- Tampilan responsif dan aksesibilitas dasar diuji.

### Phase 7 — PDF/DOCX Export
Tasks:
- Implementasi PDF dan DOCX export.
- Jalankan ekspor di queue bila proses lama.
- Simpan file secara privat dan gunakan download yang terotorisasi.
- Uji page breaks, font, URL, selectable text, dan urutan baca.
- Tambahkan penghapusan ekspor dan retensi file.

Acceptance criteria:
- PDF dan DOCX dapat dibuka dengan benar.
- Isi hasil ekspor sesuai konten editor yang disetujui.
- File tidak dapat diunduh oleh pengguna lain.
- Teks PDF dapat diekstrak dan dibaca dalam urutan yang wajar.

### Phase 8 — Hardening, Evaluation & Production Readiness
Tasks:
- Review authorization, upload, prompt injection, XSS, CSRF, rate limits, dan secret handling.
- Evaluasi AI dengan kumpulan test case anonim dan kriteria kualitas.
- Tambahkan monitoring status queue dan error.
- Uji beban ringan untuk endpoint umum dan AI.
- Tulis kebijakan privasi, retensi, dan disclaimer.
- Setup deployment, queue worker, backup database, dan logging.
- Pastikan konfigurasi production tidak menggunakan debug mode.

Acceptance criteria:
- Test suite dan production build lulus.
- Tidak ada endpoint data privat tanpa authorization.
- Error dan retry tidak membocorkan data pribadi.
- Deployment dan rollback steps terdokumentasi.

### Phase 9 — Post-MVP Enhancements
Prioritas berdasarkan feedback pengguna:
- Import lowongan dari URL yang didukung.
- OCR untuk scanned CV.
- Beberapa template visual dengan tetap menjaga ATS readability.
- Bahasa tambahan.
- Pengaturan preferensi gaya dan target role.
- Integrasi job board jika tersedia dan sesuai ketentuan.
- Subscription/usage plans.
- Analytics produk yang meminimalkan data pribadi.

## 12. Prioritas Implementasi

Urutan nilai produk:
1. Profil karier terstruktur dan import CV yang dapat direview.
2. Input JD + analisis kecocokan berbasis evidence.
3. CV dan surat lamaran yang spesifik, dapat diedit.
4. Ekspor dokumen yang andal.
5. Security, privasi, evaluasi AI, dan kesiapan produksi.
6. Peningkatan non-MVP setelah alur inti tervalidasi.

Jangan mulai dari banyak template atau animasi UI. Prioritaskan akurasi fakta, alur review, dan kualitas ekspor.

## 13. Definition of Done untuk Setiap Phase

Sebuah fase dianggap selesai bila:
- Semua acceptance criteria pada fase tersebut terpenuhi.
- Test baru ditambahkan untuk perilaku penting dan test relevan lulus.
- Tidak ada perubahan yang tidak terkait atau refactor besar tanpa alasan.
- Migration aman dan tidak menggunakan perintah destruktif seperti `migrate:fresh` atau `db:wipe` pada database yang berisi data.
- Validasi server dan authorization diterapkan untuk fitur baru.
- Error/loading/empty states dibuat untuk UI yang relevan.
- Dokumentasi diperbarui.
- Ringkasan akhir berisi file utama yang berubah, keputusan teknis, test yang dijalankan, hasilnya, dan risiko yang masih ada.

## 14. Instruksi untuk AI Coding Agent

Salin instruksi ini ke agent ketika mulai mengerjakan proyek:

> Baca `PROJECT_PLAN.md` sepenuhnya sebelum coding. Periksa `AGENTS.md` dan repository untuk memahami stack, konvensi, serta fitur yang sudah ada. Kerjakan **Phase 0 terlebih dahulu**, lalu berhenti dan laporkan temuan, rencana perubahan, dependency, risiko, dan acceptance criteria. Jangan mengimplementasikan semua fase sekaligus.
>
> Untuk setiap fase yang disetujui:
> 1. Pecah pekerjaan menjadi task kecil.
> 2. Implementasikan hanya scope fase aktif.
> 3. Tambahkan migration, validation, policy, error handling, dan test yang relevan.
> 4. Jalankan test, formatter, dan build yang tersedia.
> 5. Perbaiki regression yang disebabkan perubahan.
> 6. Jangan mengarang hasil test; laporkan command dan hasil aktual.
> 7. Jangan menambahkan dependency sebelum memeriksa kompatibilitas versi PHP/Node dan maintenance library.
> 8. Jangan menyimpan secret, CV lengkap, atau PII ke log/repository.
> 9. Jangan menjalankan command destruktif terhadap database.
> 10. Pada akhir fase, laporkan ringkasan implementasi, file yang berubah, test, keputusan, keterbatasan, dan rekomendasi fase berikutnya.
>
> **Aturan produk AI:** jangan mengarang fakta kandidat. Semua klaim tentang pengalaman, pendidikan, sertifikasi, tanggal, dan pencapaian harus didukung data profil atau dikonfirmasi pengguna. Jika bukti tidak tersedia, tandai sebagai gap atau ajukan pertanyaan.
