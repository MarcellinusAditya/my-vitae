import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface AnalysisSummary {
    id: number;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    matched: number;
    partial: number;
    not_evidenced: number;
    total: number;
}

interface ApplicationRow {
    id: number;
    company_name: string;
    job_title: string;
    job_location: string | null;
    status: string;
    status_label: string;
    created_at: string;
    analysis: AnalysisSummary | null;
}

interface IndexProps {
    applications: ApplicationRow[];
    statuses: Record<string, string>;
    filters: { status: string | null };
    counts: { total: number };
    flash?: { success?: string; error?: string };
}

const statusBadgeClass = (status: string): string => {
    switch (status) {
        case 'offer':
            return 'badge-success';
        case 'rejected':
            return 'badge-danger';
        case 'interview':
            return 'badge-warning';
        case 'applied':
        case 'ready_to_apply':
            return 'badge-neutral';
        default:
            return 'badge-neutral';
    }
};

const analysisBadge = (analysis: AnalysisSummary | null) => {
    if (!analysis) return <span className="badge-neutral">Belum dianalisis</span>;

    switch (analysis.status) {
        case 'pending':
        case 'processing':
            return <span className="badge-warning">Analisis diproses…</span>;
        case 'failed':
            return <span className="badge-danger">Analisis gagal</span>;
        default:
            return (
                <span className="inline-flex flex-wrap items-center gap-1.5">
                    <span className="badge-success">{analysis.matched} cocok</span>
                    <span className="badge-warning">{analysis.partial} sebagian</span>
                    <span className="badge-danger">{analysis.not_evidenced} tanpa bukti</span>
                </span>
            );
    }
};

export default function ApplicationsIndex({ applications, statuses, filters, counts, flash }: IndexProps) {
    const [deleting, setDeleting] = useState<number | null>(null);

    const applyFilter = (status: string | null) => {
        router.get(route('applications.index'), status ? { status } : {}, { preserveState: true, replace: true });
    };

    const duplicate = (id: number) => {
        router.post(route('applications.duplicate', id), {}, { preserveScroll: true });
    };

    const destroy = (id: number) => {
        if (!window.confirm('Hapus lamaran ini beserta hasil analisisnya?')) return;
        setDeleting(id);
        router.delete(route('applications.destroy', id), {
            preserveScroll: true,
            onFinish: () => setDeleting(null),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-h1">Lamaran</h1>
                        <p className="mt-1 text-small text-foreground-muted">
                            Kelola lowongan yang Anda lamar dan lihat kecocokannya dengan profil Anda.
                        </p>
                    </div>
                    <Link href={route('applications.create')} className="btn-primary shrink-0">
                        <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24" aria-hidden="true">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Lamaran baru
                    </Link>
                </div>
            }
        >
            <Head title="Lamaran" />

            {flash?.success && <div className="mb-6 text-small font-medium text-success">{flash.success}</div>}
            {flash?.error && <div className="mb-6 text-small font-medium text-danger">{flash.error}</div>}

            <div className="mb-5 flex flex-wrap gap-2" role="group" aria-label="Filter status">
                <button
                    type="button"
                    onClick={() => applyFilter(null)}
                    className={`btn-secondary ${filters?.status == null ? 'border-primary text-primary' : ''}`}
                >
                    Semua ({counts.total})
                </button>
                {Object.entries(statuses).map(([value, label]) => (
                    <button
                        key={value}
                        type="button"
                        onClick={() => applyFilter(value)}
                        className={`btn-secondary ${filters?.status === value ? 'border-primary text-primary' : ''}`}
                    >
                        {label}
                    </button>
                ))}
            </div>

            <section className="card p-5 sm:p-6">
                {applications.length === 0 ? (
                    <div className="py-10 text-center">
                        <p className="text-h3">Belum ada lamaran</p>
                        <p className="mt-1 text-small text-foreground-muted">
                            Buat lamaran pertama dengan menempelkan deskripsi lowongan (JD).
                        </p>
                        <Link href={route('applications.create')} className="btn-primary mt-4">
                            Lamaran baru
                        </Link>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[720px] text-left text-small">
                            <thead>
                                <tr className="border-b border-border text-caption uppercase tracking-wide text-foreground-subtle">
                                    <th className="py-2 pr-4 font-medium">Perusahaan / Posisi</th>
                                    <th className="py-2 pr-4 font-medium">Status</th>
                                    <th className="py-2 pr-4 font-medium">Analisis kecocokan</th>
                                    <th className="py-2 pr-4 font-medium">Dibuat</th>
                                    <th className="py-2 text-right font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {applications.map((application) => (
                                    <tr key={application.id} className="border-b border-border last:border-0 align-top">
                                        <td className="py-3 pr-4">
                                            <Link
                                                href={route('applications.show', application.id)}
                                                className="font-medium text-foreground hover:text-primary"
                                            >
                                                {application.company_name}
                                            </Link>
                                            <p className="text-foreground-muted">{application.job_title}</p>
                                            {application.job_location && (
                                                <p className="text-caption text-foreground-subtle">{application.job_location}</p>
                                            )}
                                        </td>
                                        <td className="py-3 pr-4">
                                            <span className={statusBadgeClass(application.status)}>{application.status_label}</span>
                                        </td>
                                        <td className="py-3 pr-4">{analysisBadge(application.analysis)}</td>
                                        <td className="py-3 pr-4 text-foreground-muted">
                                            {new Date(application.created_at).toLocaleDateString('id-ID', {
                                                day: 'numeric', month: 'short', year: 'numeric',
                                            })}
                                        </td>
                                        <td className="py-3">
                                            <div className="flex justify-end gap-2">
                                                <Link href={route('applications.show', application.id)} className="btn-secondary">
                                                    Lihat
                                                </Link>
                                                <button
                                                    type="button"
                                                    className="btn-secondary"
                                                    onClick={() => duplicate(application.id)}
                                                    title="Duplikasi untuk lowongan lain"
                                                >
                                                    Duplikasi
                                                </button>
                                                <button
                                                    type="button"
                                                    className="btn-secondary text-danger"
                                                    onClick={() => destroy(application.id)}
                                                    disabled={deleting === application.id}
                                                >
                                                    Hapus
                                                </button>
                                            </div>
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