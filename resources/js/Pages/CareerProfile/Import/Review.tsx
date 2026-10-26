import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useEffect, useRef, useState } from 'react';

type Status = 'pending' | 'processing' | 'completed' | 'failed';

interface DocumentInfo {
    id: number;
    original_filename: string;
    file_size: number;
    extraction_status: Status;
    error_message: string | null;
    model_name: string | null;
    applied_at: string | null;
    created_at: string;
}

interface ContactDraft {
    full_name: string;
    email: string;
    phone: string;
    location: string;
    linkedin_url: string;
    portfolio_url: string;
    github_url: string;
}

interface SectionEntry {
    data?: Record<string, unknown>;
    items?: Record<string, unknown>[];
    confidence?: number;
}

type DraftSections = Record<string, SectionEntry>;
type RowMap = Record<string, Record<string, unknown>[]>;

interface ReviewProps {
    document: DocumentInfo;
    draft: DraftSections | null;
    sections: Record<string, string>;
    profile: { has_contact: boolean; has_summary: boolean };
    flash?: { success?: string };
}

const EMPTY_CONTACT: ContactDraft = {
    full_name: '', email: '', phone: '', location: '',
    linkedin_url: '', portfolio_url: '', github_url: '',
};

const confidenceClass = (confidence?: number): string => {
    if (confidence === undefined) return 'badge-neutral';
    if (confidence >= 70) return 'badge-success';
    if (confidence >= 40) return 'badge-warning';
    return 'badge-danger';
};

const str = (value: unknown): string => (typeof value === 'string' ? value : value == null ? '' : String(value));

export default function ImportReview({ document, draft, sections, profile, flash }: ReviewProps) {
    const [polling, setPolling] = useState(
        document.extraction_status === 'pending' || document.extraction_status === 'processing',
    );

    // Editable working copies of the draft — nothing leaves the browser until "Impor".
    const [contact, setContact] = useState<ContactDraft>(EMPTY_CONTACT);
    const [summary, setSummary] = useState('');
    const [rows, setRows] = useState<RowMap>({});
    const [keptSections, setKeptSections] = useState<Record<string, boolean>>({});
    const [keptRows, setKeptRows] = useState<Record<string, boolean[]>>({});
    const initialised = useRef(false);

    useEffect(() => {
        if (!draft || initialised.current) return;
        initialised.current = true;

        setContact({ ...EMPTY_CONTACT, ...(draft.contact?.data as Partial<ContactDraft> ?? {}) });
        setSummary(str(draft.summary?.data?.text));

        const nextRows: RowMap = {};
        const nextKept: Record<string, boolean[]> = {};

        Object.entries(draft).forEach(([key, entry]) => {
            if (key === 'contact' || key === 'summary') return;
            const items = (entry.items ?? []) as Record<string, unknown>[];
            nextRows[key] = items.map((item) => ({ ...item }));
            nextKept[key] = items.map(() => true);
        });

        setRows(nextRows);
        setKeptRows(nextKept);
        setKeptSections({
            contact: !profile.has_contact,
            summary: !profile.has_summary,
        });
    }, [draft, profile.has_contact, profile.has_summary]);

    // Poll the JSON status endpoint while extraction runs, then reload the page.
    useEffect(() => {
        if (!polling) return;
        const timer = setInterval(async () => {
            try {
                const res = await fetch(`${route('career-profile.import.show', document.id)}?status=1`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const payload = await res.json();
                if (payload.status === 'completed' || payload.status === 'failed') {
                    setPolling(false);
                    router.reload();
                }
            } catch {
                // transient errors: keep polling
            }
        }, 3000);
        return () => clearInterval(timer);
    }, [polling, document.id]);

    const { transform, post, processing, errors } = useForm<{ sections: Record<string, string | number | boolean | null | undefined | object> }>({ sections: {} });

    const sectionKeys = Object.keys(draft ?? {});
    const rowKeys = sectionKeys.filter((key) => key !== 'contact' && key !== 'summary');

    const buildPayload = (): Record<string, unknown> => {
        const payload: Record<string, unknown> = {};

        if (keptSections.contact) {
            payload.contact = { ...contact };
        }

        if (keptSections.summary && summary.trim() !== '') {
            payload.summary = { text: summary };
        }

        rowKeys.forEach((key) => {
            const selected = (rows[key] ?? [])
                .map((row, index) => ({ row, keep: keptRows[key]?.[index] ?? false }))
                .filter(({ keep }) => keep)
                .map(({ row }) => row);

            if (selected.length > 0) {
                payload[key] = selected;
            }
        });

        return payload;
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        transform(() => ({ sections: buildPayload() }));
        post(route('career-profile.import.confirm', document.id), { preserveScroll: true });
    };

    const updateRow = (key: string, index: number, field: string, value: unknown) => {
        setRows((prev) => {
            const list = [...(prev[key] ?? [])];
            list[index] = { ...(list[index] ?? {}), [field]: value };
            return { ...prev, [key]: list };
        });
    };

    const toggleRow = (key: string, index: number) => {
        setKeptRows((prev) => {
            const list = [...(prev[key] ?? [])];
            list[index] = !list[index];
            return { ...prev, [key]: list };
        });
    };

    const field = (key: string, index: number, name: string, label: string, type = 'text') => {
        const row = rows[key]?.[index] ?? {};
        const raw = row[name];
        const value = Array.isArray(raw) ? (raw as string[]).join(', ') : str(raw);

        return (
            <label className="block" key={`${key}-${index}-${name}`}>
                <span className="field-label">{label}</span>
                <input
                    type={type}
                    className="field-input"
                    value={value}
                    onChange={(e) => {
                        const next = name === 'technologies' || name === 'achievements'
                            ? e.target.value.split(',').map((s) => s.trim()).filter(Boolean)
                            : e.target.value;
                        updateRow(key, index, name, next);
                    }}
                />
            </label>
        );
    };

    const isRunning = document.extraction_status === 'pending' || document.extraction_status === 'processing';
    const contactIsEmpty = Object.values(contact).every((v) => v === '');
    const summaryIsEmpty = summary.trim() === '';

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="min-w-0">
                        <h1 className="text-h1">Review hasil ekstraksi</h1>
                        <p className="mt-1 truncate text-small text-foreground-muted">{document.original_filename}</p>
                    </div>
                    <Link href={route('career-profile.import.index')} className="btn-secondary shrink-0">
                        Kembali ke Import
                    </Link>
                </div>
            }
        >
            <Head title="Review CV" />

            {flash?.success && <div className="mb-6 text-small font-medium text-success">{flash.success}</div>}
            {errors.sections && <div className="mb-6 text-small font-medium text-danger">{errors.sections}</div>}

            {isRunning && (
                <section className="card p-5 sm:p-6">
                    <div className="flex items-center gap-3">
                        <svg className="h-5 w-5 animate-spin text-primary" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                        </svg>
                        <div>
                            <p className="text-h3">Sedang membaca CV Anda...</p>
                            <p className="mt-0.5 text-small text-foreground-muted">
                                Proses ini memakai AI dan bisa memakan waktu hingga satu menit. Halaman memperbarui sendiri.
                            </p>
                        </div>
                    </div>
                </section>
            )}

            {document.extraction_status === 'failed' && (
                <section className="card p-5 sm:p-6">
                    <div className="flex items-start gap-3">
                        <svg className="mt-0.5 h-5 w-5 shrink-0 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v4m0 4h.01M10.29 3.86l-8.02 13.9A1.5 1.5 0 003.58 20h16.84a1.5 1.5 0 001.3-2.24l-8.02-13.9a1.5 1.5 0 00-2.6 0z" />
                        </svg>
                        <div>
                            <p className="text-h3 text-danger">Ekstraksi gagal</p>
                            <p className="mt-1 text-small text-foreground-muted">
                                {document.error_message ?? 'Terjadi kesalahan saat membaca file.'}
                            </p>
                            <Link href={route('career-profile.import.index')} className="btn-secondary mt-3">
                                Coba unggah lagi
                            </Link>
                        </div>
                    </div>
                </section>
            )}

            {document.extraction_status === 'completed' && !draft && (
                <section className="card p-5 sm:p-6">
                    <p className="text-small text-foreground-muted">Tidak ada hasil ekstraksi pada dokumen ini.</p>
                </section>
            )}

            {document.extraction_status === 'completed' && draft && (
                <form onSubmit={submit}>
                    <div className="mb-6 flex flex-wrap items-center gap-3">
                        <span className="badge-neutral">Model: {document.model_name ?? 'AI'}</span>
                        <span className="badge-warning">Periksa semua data sebelum impor — AI bisa salah membaca</span>
                        {document.applied_at && (
                            <span className="badge-success">
                                Sudah diimpor pada {new Date(document.applied_at).toLocaleString('id-ID')}
                            </span>
                        )}
                    </div>

                    <div className="space-y-6">
                        {sectionKeys.map((key) => {
                            const entry = draft[key];
                            const isContact = key === 'contact';
                            const isSummary = key === 'summary';
                            const list = rows[key] ?? [];

                            return (
                                <section key={key} className={`card p-5 sm:p-6 ${keptSections[key] ? 'ring-1 ring-primary' : ''}`}>
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h2 className="text-h3">{sections[key] ?? key}</h2>
                                            <span className={confidenceClass(entry.confidence)}>
                                                Keyakinan {entry.confidence ?? '—'}%
                                            </span>
                                        </div>
                                        {(isContact || isSummary) && (
                                            <label className="flex cursor-pointer items-center gap-2 text-small">
                                                <input
                                                    type="checkbox"
                                                    checked={keptSections[key] ?? false}
                                                    onChange={() =>
                                                        setKeptSections((prev) => ({ ...prev, [key]: !prev[key] }))
                                                    }
                                                />
                                                Impor bagian ini
                                            </label>
                                        )}
                                        {!isContact && !isSummary && list.length > 0 && (
                                            <span className="text-caption text-foreground-muted">
                                                {list.filter((_, i) => keptRows[key]?.[i] ?? false).length} dari {list.length} item disertakan
                                            </span>
                                        )}
                                    </div>

                                    {isContact && (contactIsEmpty ? (
                                        <p className="mt-3 text-small text-foreground-muted">Tidak ada data kontak yang terdeteksi.</p>
                                    ) : (
                                        <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                            <label className="block sm:col-span-2">
                                                <span className="field-label">Nama lengkap</span>
                                                <input className="field-input" value={contact.full_name} onChange={(e) => setContact({ ...contact, full_name: e.target.value })} />
                                            </label>
                                            <label className="block">
                                                <span className="field-label">Email</span>
                                                <input className="field-input" value={contact.email} onChange={(e) => setContact({ ...contact, email: e.target.value })} />
                                            </label>
                                            <label className="block">
                                                <span className="field-label">Telepon</span>
                                                <input className="field-input" value={contact.phone} onChange={(e) => setContact({ ...contact, phone: e.target.value })} />
                                            </label>
                                            <label className="block">
                                                <span className="field-label">Lokasi</span>
                                                <input className="field-input" value={contact.location} onChange={(e) => setContact({ ...contact, location: e.target.value })} />
                                            </label>
                                            <label className="block">
                                                <span className="field-label">LinkedIn</span>
                                                <input className="field-input" value={contact.linkedin_url} onChange={(e) => setContact({ ...contact, linkedin_url: e.target.value })} />
                                            </label>
                                            <label className="block">
                                                <span className="field-label">Portofolio</span>
                                                <input className="field-input" value={contact.portfolio_url} onChange={(e) => setContact({ ...contact, portfolio_url: e.target.value })} />
                                            </label>
                                            <label className="block">
                                                <span className="field-label">GitHub</span>
                                                <input className="field-input" value={contact.github_url} onChange={(e) => setContact({ ...contact, github_url: e.target.value })} />
                                            </label>
                                        </div>
                                    ))}

                                    {isSummary && (summaryIsEmpty ? (
                                        <p className="mt-3 text-small text-foreground-muted">Tidak ada ringkasan yang terdeteksi.</p>
                                    ) : (
                                        <textarea
                                            rows={4}
                                            className="field-input mt-4"
                                            value={summary}
                                            onChange={(e) => setSummary(e.target.value)}
                                        />
                                    ))}

                                    {!isContact && !isSummary && (list.length === 0 ? (
                                        <p className="mt-3 text-small text-foreground-muted">Tidak ada data yang terdeteksi di bagian ini.</p>
                                    ) : (
                                        <div className="mt-4 space-y-4">
                                            {list.map((_, index) => (
                                                <div
                                                    key={`${key}-${index}`}
                                                    className={`rounded-xl border border-border p-4 ${keptRows[key]?.[index] ? 'bg-surface-muted' : 'bg-surface opacity-60'}`}
                                                >
                                                    <div className="flex items-center justify-between gap-3">
                                                        <p className="text-small font-medium text-foreground-muted">Item {index + 1}</p>
                                                        <label className="flex cursor-pointer items-center gap-2 text-small">
                                                            <input
                                                                type="checkbox"
                                                                checked={keptRows[key]?.[index] ?? false}
                                                                onChange={() => toggleRow(key, index)}
                                                            />
                                                            Sertakan
                                                        </label>
                                                    </div>

                                                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                                        {key === 'experiences' && (
                                                            <>
                                                                {field(key, index, 'organization', 'Perusahaan/Organisasi')}
                                                                {field(key, index, 'job_title', 'Jabatan')}
                                                                {field(key, index, 'employment_type', 'Tipe kerja')}
                                                                {field(key, index, 'location', 'Lokasi')}
                                                                {field(key, index, 'start_date', 'Mulai (YYYY-MM-DD)')}
                                                                {!Boolean(rows[key]?.[index]?.is_current) && field(key, index, 'end_date', 'Selesai (YYYY-MM-DD)')}
                                                                <label className="flex items-center gap-2 text-small sm:col-span-2">
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={Boolean(rows[key]?.[index]?.is_current)}
                                                                        onChange={(e) => updateRow(key, index, 'is_current', e.target.checked)}
                                                                    />
                                                                    Masih bekerja di sini
                                                                </label>
                                                                <label className="block sm:col-span-2">
                                                                    <span className="field-label">Deskripsi</span>
                                                                    <textarea
                                                                        rows={2}
                                                                        className="field-input"
                                                                        value={str(rows[key]?.[index]?.description)}
                                                                        onChange={(e) => updateRow(key, index, 'description', e.target.value)}
                                                                    />
                                                                </label>
                                                                {field(key, index, 'achievements', 'Pencapaian (pisahkan dengan koma)')}
                                                            </>
                                                        )}

                                                        {key === 'educations' && (
                                                            <>
                                                                {field(key, index, 'institution', 'Institusi')}
                                                                {field(key, index, 'degree', 'Jenjang')}
                                                                {field(key, index, 'field_of_study', 'Jurusan')}
                                                                {field(key, index, 'start_date', 'Mulai (YYYY-MM-DD)')}
                                                                {field(key, index, 'end_date', 'Selesai (YYYY-MM-DD)')}
                                                            </>
                                                        )}

                                                        {key === 'skills' && (
                                                            <>
                                                                {field(key, index, 'name', 'Skill')}
                                                                {field(key, index, 'category', 'Kategori')}
                                                                {field(key, index, 'proficiency', 'Tingkat')}
                                                            </>
                                                        )}

                                                        {key === 'projects' && (
                                                            <>
                                                                {field(key, index, 'name', 'Nama proyek')}
                                                                {field(key, index, 'role', 'Peran')}
                                                                {field(key, index, 'technologies', 'Teknologi (pisahkan dengan koma)')}
                                                                {field(key, index, 'url', 'URL')}
                                                                {field(key, index, 'start_date', 'Mulai (YYYY-MM-DD)')}
                                                                {field(key, index, 'end_date', 'Selesai (YYYY-MM-DD)')}
                                                            </>
                                                        )}

                                                        {key === 'certifications' && (
                                                            <>
                                                                {field(key, index, 'name', 'Nama sertifikasi')}
                                                                {field(key, index, 'issuer', 'Penerbit')}
                                                                {field(key, index, 'issue_date', 'Tanggal terbit (YYYY-MM-DD)')}
                                                                {field(key, index, 'expiry_date', 'Kedaluwarsa (YYYY-MM-DD)')}
                                                                {field(key, index, 'credential_url', 'URL kredensial')}
                                                            </>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ))}
                                </section>
                            );
                        })}
                    </div>

                    <div className="sticky bottom-0 mt-6 border-t border-border bg-background/95 py-4 backdrop-blur">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="text-small text-foreground-muted">
                                Bagian yang dipilih akan ditambahkan ke profil karier. Data lama tetap ada, tidak ditimpa.
                            </p>
                            <button type="submit" className="btn-primary" disabled={processing}>
                                {processing ? 'Mengimpor...' : 'Impor ke profil'}
                            </button>
                        </div>
                    </div>
                </form>
            )}
        </AuthenticatedLayout>
    );
}