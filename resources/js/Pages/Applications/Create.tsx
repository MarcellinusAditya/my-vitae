import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import axios from 'axios';
import { FormEvent, useState } from 'react';

interface FormProps {
    languages: string[];
    defaultLanguage: string;
}

interface ImportedPosting {
    title: string;
    company: string;
    location: string;
    description: string;
    qualifications: string;
}

const languageLabels: Record<string, string> = {
    id: 'Bahasa Indonesia',
    en: 'English',
    ms: 'Bahasa Melayu',
};

export default function ApplicationCreate({ languages, defaultLanguage }: FormProps) {
    const { data, setData, post, processing, errors } = useForm({
        company_name: '',
        job_title: '',
        job_location: '',
        job_description: '',
        job_qualifications: '',
        language: defaultLanguage,
        notes: '',
    });

    const [url, setUrl] = useState('');
    const [importing, setImporting] = useState(false);
    const [importError, setImportError] = useState<string | null>(null);
    const [importedFrom, setImportedFrom] = useState<string | null>(null);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('applications.store'));
    };

    /**
     * Prefills the form from a posting URL. Nothing is saved here: the user
     * reviews and edits the extracted text before submitting.
     */
    const importFromUrl = async () => {
        if (url.trim() === '' || importing) {
            return;
        }

        setImporting(true);
        setImportError(null);

        try {
            const { data: payload } = await axios.post<{ posting: ImportedPosting }>(
                route('applications.import-url'),
                { url },
            );

            const posting = payload.posting;

            setData((current) => ({
                ...current,
                company_name: current.company_name || posting.company,
                job_title: current.job_title || posting.title,
                job_location: current.job_location || posting.location,
                job_description: posting.description || current.job_description,
                job_qualifications: posting.qualifications || current.job_qualifications,
            }));

            setImportedFrom(url);
        } catch (error) {
            const message =
                axios.isAxiosError(error) && error.response?.status === 422
                    ? ((error.response.data as { message?: string }).message ??
                      'Gagal mengimpor lowongan.')
                    : 'Tidak dapat menghubungi server. Coba lagi.';

            setImportError(message);
        } finally {
            setImporting(false);
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-h1">Lamaran baru</h1>
                        <p className="mt-1 text-small text-foreground-muted">
                            Tempel deskripsi lowongan, lalu sistem akan memetakan persyaratannya ke profil Anda.
                        </p>
                    </div>
                    <Link href={route('applications.index')} className="btn-secondary shrink-0">
                        Kembali
                    </Link>
                </div>
            }
        >
            <Head title="Lamaran baru" />

            <form onSubmit={submit} className="space-y-6">
                <section className="card p-5 sm:p-6">
                    <h2 className="text-h3">Impor dari URL lowongan</h2>
                    <p className="mt-1 text-small text-foreground-muted">
                        Tempel tautan iklan lowongan, lalu periksa hasilnya sebelum menyimpan.
                    </p>

                    <div className="mt-4 flex flex-col gap-3 sm:flex-row">
                        <input
                            type="url"
                            className="field-input flex-1"
                            value={url}
                            onChange={(e) => setUrl(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    void importFromUrl();
                                }
                            }}
                            placeholder="https://contoh.com/lowongan/backend-engineer"
                            inputMode="url"
                            autoComplete="url"
                        />
                        <button
                            type="button"
                            onClick={() => void importFromUrl()}
                            disabled={importing || url.trim() === ''}
                            className="btn-secondary shrink-0"
                        >
                            {importing ? 'Mengambil…' : 'Ambil isi lowongan'}
                        </button>
                    </div>

                    {importError && <p className="field-error mt-2">{importError}</p>}

                    {importedFrom && !importError && (
                        <p className="mt-2 text-small text-foreground-muted">
                            Terisi dari <span className="break-all font-medium">{importedFrom}</span>. Periksa dan
                            sesuaikan sebelum menyimpan.
                        </p>
                    )}
                </section>

                <section className="card p-5 sm:p-6">
                    <h2 className="text-h3">Detail lowongan</h2>

                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="field-label" htmlFor="company_name">Nama perusahaan *</label>
                            <input
                                id="company_name"
                                className="field-input"
                                value={data.company_name}
                                onChange={(e) => setData('company_name', e.target.value)}
                                required
                                maxLength={255}
                            />
                            {errors.company_name && <p className="field-error">{errors.company_name}</p>}
                        </div>

                        <div>
                            <label className="field-label" htmlFor="job_title">Posisi yang dilamar *</label>
                            <input
                                id="job_title"
                                className="field-input"
                                value={data.job_title}
                                onChange={(e) => setData('job_title', e.target.value)}
                                required
                                maxLength={255}
                            />
                            {errors.job_title && <p className="field-error">{errors.job_title}</p>}
                        </div>

                        <div>
                            <label className="field-label" htmlFor="job_location">Lokasi (opsional)</label>
                            <input
                                id="job_location"
                                className="field-input"
                                value={data.job_location}
                                onChange={(e) => setData('job_location', e.target.value)}
                                maxLength={255}
                                placeholder="Jakarta / Remote"
                            />
                            {errors.job_location && <p className="field-error">{errors.job_location}</p>}
                        </div>

                        <div>
                            <label className="field-label" htmlFor="language">Bahasa dokumen</label>
                            <select
                                id="language"
                                className="field-input"
                                value={data.language}
                                onChange={(e) => setData('language', e.target.value)}
                            >
                                {languages.map((language) => (
                                    <option key={language} value={language}>
                                        {languageLabels[language] ?? language}
                                    </option>
                                ))}
                            </select>
                            {errors.language && <p className="field-error">{errors.language}</p>}
                        </div>
                    </div>
                </section>

                <section className="card p-5 sm:p-6">
                    <h2 className="text-h3">Isi lowongan</h2>

                    <div className="mt-4 space-y-4">
                        <div>
                            <label className="field-label" htmlFor="job_description">Deskripsi lowongan (JD) *</label>
                            <textarea
                                id="job_description"
                                rows={10}
                                className="field-input"
                                value={data.job_description}
                                onChange={(e) => setData('job_description', e.target.value)}
                                required
                                maxLength={20000}
                                placeholder="Tempel deskripsi pekerjaan lengkap dari iklan lowongan di sini…"
                            />
                            <p className="field-hint">
                                {data.job_description.length.toLocaleString('id-ID')} / 20.000 karakter — min. 50 karakter
                            </p>
                            {errors.job_description && <p className="field-error">{errors.job_description}</p>}
                        </div>

                        <div>
                            <label className="field-label" htmlFor="job_qualifications">Kualifikasi (opsional)</label>
                            <textarea
                                id="job_qualifications"
                                rows={5}
                                className="field-input"
                                value={data.job_qualifications}
                                onChange={(e) => setData('job_qualifications', e.target.value)}
                                maxLength={20000}
                                placeholder="Pisahkan bagian kualifikasi/kualifikasi wajib jika ada."
                            />
                            {errors.job_qualifications && <p className="field-error">{errors.job_qualifications}</p>}
                        </div>

                        <div>
                            <label className="field-label" htmlFor="notes">Catatan pribadi (opsional)</label>
                            <textarea
                                id="notes"
                                rows={3}
                                className="field-input"
                                value={data.notes}
                                onChange={(e) => setData('notes', e.target.value)}
                                maxLength={4000}
                                placeholder="Deadline, kontak recruiter, link lowongan, dll."
                            />
                            {errors.notes && <p className="field-error">{errors.notes}</p>}
                        </div>
                    </div>
                </section>

                <div className="flex flex-wrap items-center justify-end gap-3">
                    <Link href={route('applications.index')} className="btn-secondary">Batal</Link>
                    <button type="submit" className="btn-primary" disabled={processing}>
                        {processing ? 'Menyimpan…' : 'Simpan & analisis'}
                    </button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}