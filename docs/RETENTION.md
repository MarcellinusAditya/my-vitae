# Kebijakan Retensi Data

MyVitae menyimpan data sesedikit mungkin selama waktu sesingkat mungkin. Kebijakan ini
diterapkan otomatis oleh perintah `php artisan app:prune-files`, yang dijalankan harian
pukul 02:30 (`routes/console.php`, `withoutOverlapping`).

## Jendela retensi

| Data | Lokasi | Retensi | Tindakan saat kedaluwarsa |
|---|---|---|---|
| File CV yang diunggah | disk `local`, `cv-uploads/` | 30 hari sejak unggah, hanya bila ekstraksi sudah **completed/failed** | File dihapus; baris `profile_documents` juga dihapus |
| File ekspor (PDF/DOCX) | disk `local`, `document-exports/` | 30 hari sejak dibuat | File dan baris `document_exports` dihapus |
| Job gagal (`failed_jobs`) | database | 14-30 hari (`--days`) | Baris dihapus (payload memuat model terserialisasi) |
| Log AI (`ai_usage_logs`) | database | tanpa batas (audit) | Tidak dihapus; tidak memuat teks CV/JD |
| Profil karier & dokumen | database | selama akun aktif | Dihapus saat pengguna menghapus akun |

Unggahan yang masih `pending`/`processing` **tidak** dipangkas — filenya mungkin masih
dibutuhkan oleh job yang berjalan.

## Menjalankan manual

```bash
php artisan app:prune-files --dry-run   # laporan tanpa menghapus
php artisan app:prune-files --days=30   # ubah jendela
```

## Retensi file vs. baris database

Karena nilai audit, sebagian baris tetap ada sebagai jejak (misalnya status ekstraksi).
Pendekatan yang dipakai di project ini: bila baris hanya berfungsi sebagai penampung file,
seluruh baris dihapus; bila baris menyimpan hasil (teks terstruktur, analisis), baris tetap
dan hanya file binary yang dihapus.
