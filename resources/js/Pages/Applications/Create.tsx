import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface FormProps {
    languages: string[];
    defaultLanguage: string;
}

const languageLabels: Record<string, string> = {
    id: 'Bahasa Indonesia',
    en: 'English',
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

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('applications.store'));
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