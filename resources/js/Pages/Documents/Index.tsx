import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

interface DocumentRow {
    id: number;
    type: string;
    type_label: string;
    title: string;
    version: number;
    language: string;
    generation_status: 'pending' | 'processing' | 'completed' | 'failed';
    error_message: string | null;
    company_name: string | null;
    job_title: string | null;
    application_id: number;
    unverified_count: number;
    created_at: string;
}

interface IndexProps {
    documents: DocumentRow[];
    types: Record<string, string>;
    filters: { type: string | null };
    flash?: { success?: string; error?: string };
}

const statusBadge = (document: DocumentRow) => {
    switch (document.generation_status) {
        case 'pending':
        case 'processing':
            return <span className="badge-warning">Diproses…</span>;
        case 'failed':
            return <span className="badge-danger" title={document.error_message ?? ''}>Gagal</span>;
        default:
            return <span className="badge-success">Siap diedit</span>;
    }
};

export default function DocumentsIndex({ documents, types, filters, flash }: IndexProps) {
    const filter = (type: string | null) => {
        router.get(route('documents.index'), type ? { type } : {}, { preserveState: true, replace: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-h1">Dokumen</h1>
                    <p className="mt-1 text-small text-foreground-muted">
                        Semua CV dan cover letter yang pernah dibuat, lintas lamaran.
                    </p>
                </div>
            }
        >
            <Head title="Dokumen" />

            {flash?.success && <div className="mb-6 text-small font-medium text-success">{flash.success}</div>}

            <div className="mb-5 flex flex-wrap gap-2">
                <button type="button" onClick={() => filter(null)} className={`btn-secondary ${filters?.type == null ? 'border-primary text-primary' : ''}`}>
                    Semua
                </button>
                {Object.entries(types).map(([value, label]) => (
                    <button key={value} type="button" onClick={() => filter(value)} className={`btn-secondary ${filters?.type === value ? 'border-primary text-primary' : ''}`}>
                        {label}
                    </button>
                ))}
            </div>

            <section className="card p-5 sm:p-6">
                {documents.length === 0 ? (
                    <div className="py-10 text-center">
                        <p className="text-h3">Belum ada dokumen</p>
                        <p className="mt-1 text-small text-foreground-muted">
                            Buka salah satu lamaran, lalu buat CV atau cover letter dari tab Dokumen.
                        </p>
                        <Link href={route('applications.index')} className="btn-primary mt-4">Ke daftar lamaran</Link>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[760px] text-left text-small">
                            <thead>
                                <tr className="border-b border-border text-caption uppercase tracking-wide text-foreground-subtle">
                                    <th className="py-2 pr-4 font-medium">Dokumen</th>
                                    <th className="py-2 pr-4 font-medium">Lamaran</th>
                                    <th className="py-2 pr-4 font-medium">Versi</th>
                                    <th className="py-2 pr-4 font-medium">Status</th>
                                    <th className="py-2 pr-4 font-medium">Dibuat</th>
                                    <th className="py-2 text-right font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {documents.map((document) => (
                                    <tr key={document.id} className="border-b border-border last:border-0 align-top">
                                        <td className="py-3 pr-4">
                                            <Link href={route('documents.show', document.id)} className="font-medium text-foreground hover:text-primary">
                                                {document.type_label}
                                            </Link>
                                            {document.unverified_count > 0 && (
                                                <span className="badge-warning ml-2">{document.unverified_count} klaim perlu dicek</span>
                                            )}
                                        </td>
                                        <td className="py-3 pr-4">
                                            <p className="text-foreground">{document.company_name}</p>
                                            <p className="text-foreground-muted">{document.job_title}</p>
                                        </td>
                                        <td className="py-3 pr-4 text-foreground-muted">v{document.version}</td>
                                        <td className="py-3 pr-4">{statusBadge(document)}</td>
                                        <td className="py-3 pr-4 text-foreground-muted">
                                            {new Date(document.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })}
                                        </td>
                                        <td className="py-3 text-right">
                                            <Link href={route('documents.show', document.id)} className="btn-secondary">Buka</Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
        </AuthenticatedLayout>
    );
}