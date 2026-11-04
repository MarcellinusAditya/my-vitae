# Kebijakan Privasi (Ringkasan)

Dokumen ini merangkum bagaimana MyVitae menangani data pribadi. Ini **bukan** nasihat
hukum; sebelum dipakai publik, mintalah tinjauan legal.

## Data yang disimpan

- **Akun**: nama, email, kata sandi (ter-hash).
- **Profil karier**: kontak, ringkasan, pengalaman, pendidikan, skill, proyek, sertifikasi
  yang Anda isi sendiri.
- **Berkas CV yang diunggah** dan teks yang diekstrak darinya.
- **Deskripsi lowongan (JD)** yang Anda tempel, beserta hasil analisis kecocokan.
- **Dokumen terbuat** (CV/surat lamaran) dan hasil ekspornya.
- **Log penggunaan AI** (`ai_usage_logs`): jenis operasi, penyedia, model, durasi, status,
  jumlah token. **Tidak** memuat isi CV atau JD.

## Penggunaan

Data hanya dipakai untuk menghasilkan fitur yang Anda minta (ekstraksi CV, analisis
kecocokan, pembuatan dokumen, ekspor). Tidak dijual atau dibagikan untuk iklan.

## Pemrosesan oleh pihak ketiga (LLM)

Saat Anda memakai fitur AI, teks profil dan JD dikirim ke penyedia model bahasa yang
dikonfigurasi (`OPENAI_API_BASE`). Penyedia tersebut menerima isi yang diperlukan untuk
operasi — kirim hanya data yang memang boleh diproses pihak ketiga. Teks dibungkus
delimiter dan instruksi sistem menegaskan bahwa isinya adalah **data**, bukan perintah.

## Penyimpanan berkas

Semua berkas privat disimpan di disk `local` (di luar web root) dan hanya dapat diunduh
lewat endpoint terotorisasi (kepemilikan diverifikasi per pengguna). Jendela retensi ada di
`RETENTION.md`.

## Pemrosesan OCR

CV hasil scan dibaca dengan **Tesseract di server ini sendiri**; gambar hasil render tidak
dikirim ke layanan pihak ketiga. Berkas sementara (`/tmp/myvitae-ocr-*`) dihapus segera
setelah pemrosesan selesai.

## Impor lowongan dari URL

Saat Anda menempel tautan lowongan, server mengambil halaman tersebut secara langsung.
Alamat privat/loopback ditolak, dan situs yang memblokir pengambilan otomatis akan
menampilkan pesan agar isinya ditempel manual. Yang disimpan hanya teks yang Anda setujui
di formulir.

## Hak Anda

- **Akses & koreksi**: seluruh data profil dapat Anda lihat dan ubah di aplikasi.
- **Penghapusan**: menghapus akun akan menghapus profil, aplikasi, dokumen, ekspor, dan
  berkas terkait (lihat `docs/DEPLOYMENT.md` untuk operasi akun).
