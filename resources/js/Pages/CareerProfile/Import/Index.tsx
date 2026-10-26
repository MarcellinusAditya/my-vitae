import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ChangeEvent, FormEvent, useState } from 'react';

interface DocumentRow {
    id: number;
    original_filename: string;
    file_size: number;
    extraction_status: 'pending' | 'processing' | 'completed' | 'failed';
    applied_at: string | null;
    created_at: string;
    error_message: string | null;
}

interface ImportIndexProps {
    documents: DocumentRow[];
    maxFileSizeMb: number;
    flash?: { success?: string };
}

const statusBadge: Record<string, { label: string; className: string }> = {
    pending: { label: 'Menunggu', className: 'badge-warning' },
    processing: { label: 'Diproses', className: 'badge-warning' },
    completed: { label: 'Siap direview', className: 'badge-success' },
    failed: { label: 'Gagal', className: 'badge-danger' },
};

function formatSize(bytes: number): string {
    if (!bytes) return '—';
    const mb = bytes / (1024 * 1024);
    if (mb >= 1) return `${mb.toFixed(1)} MB`;
    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

export default function ImportIndex({ documents, maxFileSizeMb, flash }: ImportIndexProps) {
    const { data, setData, post, processing, errors, reset } = useForm<{ document: File | null }>({
        document: null,
    });
    const [dragging, setDragging] = useState(false);
    const [fileName, setFileName] = useState<string | null>(null);

    const pickFile = (file: File | null) => {
        setData('document', file);
        setFileName(file?.name ?? null);
    };

    const onDrop = (e: React.DragEvent) => {
        e.preventDefault();
        setDragging(false);
        const file = e.dataTransfer.files?.[0] ?? null;
        if (file) pickFile(file);
    };

    const onFileChange = (e: ChangeEvent<HTMLInputElement>) => {
        pickFile(e.target.files?.[0] ?? null);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('career-profile.documents.store'), {
            onSuccess: () => {
                reset();
                setFileName(null);
            },
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-h1">Import CV</h1>
                    <p className="mt-1 text-small text-foreground-muted">
                        Unggah CV lama (PDF atau DOCX). Hasil pembacaan akan ditampilkan untuk Anda review dulu sebelum masuk ke profil.
                    </p>
                </div>
            }
        >
            <Head title="Import CV" />

            {flash?.success && (
                <div className="mb-6 text-small font-medium text-success">{flash.success}</div>
            )}

            <div className="space-y-6">
                <section className="card p-5 sm:p-6">
                    <h2 className="text-h3">Unggah file</h2>
                    <p className="mt-0.5 text-small text-foreground-muted">
                        Format PDF (berisi teks, bukan hasil scan) atau DOCX. Maksimal {maxFileSizeMb} MB.
                    </p>

                    <form onSubmit={submit} className="mt-4 space-y-4">
                        <label
                            htmlFor="document"
                            onDragOver={(e) => { e.preventDefault(); setDragging(true); }}
                            onDragLeave={() => setDragging(false)}
                            onDrop={onDrop}
                            className={`flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-4 py-8 text-center transition-colors ${
                                dragging ? 'border-primary bg-primary-soft' : 'border-border-strong bg-surface-muted'
                            }`}
                        >
                            <svg className="h-8 w-8 text-foreground-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v12m0-12L7 9m5-5l5 5" />
                            </svg>
                            <p className="mt-3 text-small text-foreground">
                                {fileName ? (
                                    <span className="font-medium text-primary">{fileName}</span>
                                ) : (
                                    <>Tarik file ke sini atau <span className="font-medium text-primary">pilih file</span></>
                                )}
                            </p>
                            <p className="mt-1 text-caption text-foreground-muted">PDF atau DOCX, maks {maxFileSizeMb} MB</p>
                            <input
                                id="document"
                                type="file"
                                accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                className="sr-only"
                                onChange={onFileChange}
                            />
                        </label>

                        {errors.document && <p className="field-error">{errors.document}</p>}

                        <button type="submit" className="btn-primary" disabled={processing || !data.document}>
                            {processing ? 'Mengunggah...' : 'Unggah & proses'}
                        </button>
                    </form>
                </section>

                <section className="card p-5 sm:p-6">
                    <h2 className="text-h3">Riwayat impor</h2>

                    {documents.length === 0 ? (
                        <p className="mt-3 text-small text-foreground-muted">Belum ada file yang diimpor.</p>
                    ) : (
                        <div className="mt-4 overflow-x-auto">
                            <table className="w-full min-w-[560px] text-left text-small">
                                <thead>
                                    <tr className="border-b border-border text-caption uppercase tracking-wide text-foreground-subtle">
                                        <th className="py-2 pr-4 font-medium">File</th>
                                        <th className="py-2 pr-4 font-medium">Ukuran</th>
                                        <th className="py-2 pr-4 font-medium">Status</th>
                                        <th className="py-2 pr-4 font-medium">Tanggal</th>
                                        <th className="py-2 font-medium text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {documents.map((doc) => {
                                        const badge = statusBadge[doc.extraction_status] ?? statusBadge.pending;
                                        const badgeClass = doc.applied_at && doc.extraction_status === 'completed' ? 'badge-neutral' : badge.className;
                                        const badgeLabel = doc.applied_at && doc.extraction_status === 'completed' ? 'Sudah diimpor' : badge.label;
                                        const canReview = doc.extraction_status === 'completed' && !doc.applied_at;

                                        return (
                                            <tr key={doc.id} className="border-b border-border last:border-0">
                                                <td className="py-3 pr-4">
                                                    <Link
                                                        href={route('career-profile.import.show', doc.id)}
                                                        className="font-medium text-foreground hover:text-primary"
                                                    >
                                                        {doc.original_filename}
                                                    </Link>
                                                    {doc.extraction_status === 'failed' && doc.error_message && (
                                                        <p className="mt-0.5 text-caption text-danger">{doc.error_message}</p>
                                                    )}
                                                </td>
                                                <td className="py-3 pr-4 text-foreground-muted">{formatSize(doc.file_size)}</td>
                                                <td className="py-3 pr-4">
                                                    <span className={badgeClass}>{badgeLabel}</span>
                                                </td>
                                                <td className="py-3 pr-4 text-foreground-muted">
                                                    {new Date(doc.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })}
                                                </td>
                                                <td className="py-3 text-right">
                                                    {canReview && (
                                                        <Link href={route('career-profile.import.show', doc.id)} className="btn-secondary">
                                                            Review
                                                        </Link>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}