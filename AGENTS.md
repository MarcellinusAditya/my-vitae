# Project Instructions — MyVitae

AI CV & cover letter builder. Product spec: `PROJECT_PLAN.md`.

## Stack

- PHP 8.2 · Laravel 12 · MySQL (`my_vitae`)
- React 18 + TypeScript + Inertia 2 · Vite 7
- Tailwind CSS **v3** — jangan tambahkan plugin/konfigurasi v4
- PHPUnit 11 untuk test
- Queue & session driver: `database`

## Perintah kerja

```bash
php artisan test          # wajib sebelum menutup task backend
npm run build             # wajib setelah perubahan frontend (tsc + vite)
php artisan migrate       # jangan pernah migrate:fresh / db:wipe
php artisan queue:work    # jalankan manual saat menguji job
```

## Git

- Branch fitur dipotong dari `develop`, bukan `main`.
- Jangan force push, jangan rewrite history yang sudah dibagikan.
- Review `git diff` sebelum commit; format pesan `feat(scope): ...` / `fix(scope): ...` / `chore(scope): ...`.
- Jangan commit `.env`, kredensial, API key, atau data pengguna.

## Database

- Perubahan skema hanya lewat migration baru. Jangan mengedit migration yang sudah dijalankan.
- Jangan jalankan perintah destruktif pada database berisi data.
- Semua data milik pengguna wajib punya foreign key + index yang sesuai.

## Arsitektur

- Controller tipis; domain logic di service/action class (`app/Services`).
- Validasi di Form Request, otorisasi di Policy — bukan hanya disembunyikan di UI.
- Proses lama (ekstraksi dokumen, AI, ekspor) lewat queue job dengan status `pending|processing|completed|failed`.
- File pengguna masuk disk `local` (private), **bukan** disk `public`. Unduhan lewat route terotorisasi.
- Output AI selalu tidak tepercaya sampai lolos validasi schema di server.

## Aturan produk AI

- Jangan mengarang fakta kandidat: pengalaman, perusahaan, jabatan, pendidikan, sertifikasi, tanggal, metrik, atau skill.
- Setiap klaim di dokumen hasil generasi harus terlacak ke profil terverifikasi atau input pengguna.
- Jika bukti tidak ada, tandai sebagai gap dan ajukan pertanyaan — jangan diisi karangan.
- Jangan menyatakan skor/analisis sebagai prediksi lolos ATS atau keputusan perekrut.

## Frontend

- Semua halaman responsif mulai 360px, tanpa horizontal scroll.
- Gunakan token dari `tailwind.config.js` (`bg-surface`, `text-foreground`, `border-border`, dst).
  Jangan pakai `gray-*`/`indigo-*` mentah di komponen baru.
- Komponen siap pakai: `.btn-primary`, `.btn-secondary`, `.btn-ghost`, `.card`,
  `.field-input`, `.field-label`, `.badge-*` (lihat `resources/css/app.css`).
- Skala tipografi: `text-display`, `text-h1`, `text-h2`, `text-h3`, `text-body`, `text-small`, `text-caption`.
- Navigasi mobile wajib: sidebar jadi drawer dengan tombol hamburger (sudah ada di `AuthenticatedLayout`).
- Tabel lebar harus punya `overflow-x-auto`.
- `darkMode: 'class'` sudah disiapkan tetapi palet gelap belum diisi — jangan menambah `dark:` ad hoc
  sebelum token CSS variable tersedia.

## Sebelum melaporkan task selesai

1. `git status` + review `git diff`
2. Jalankan test relevan (dan `php artisan test` bila praktis)
3. `npm run build` bila frontend berubah
4. Laporkan: file yang berubah, perilaku yang diverifikasi, hasil test, dan yang belum bisa diverifikasi
