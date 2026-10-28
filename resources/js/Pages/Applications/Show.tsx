import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

interface Requirement {
    text: string;
    category: string;
    priority: string;
    match_status: string;
    evidence_refs: string[];
    recommendation: string | null;
}

interface Keyword {
    keyword: string;
    supported_by_profile: boolean;
    evidence_refs: string[];
}

interface AnalysisResult {
    requirements: Requirement[];
    keyword_suggestions: Keyword[];
    seniority: string | null;
    summary: string;
    demoted?: number;
}

interface Analysis {
    id: number;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    error_message: string | null;
    model_name: string | null;
    created_at: string;
    result: AnalysisResult | null;
    matched: number;
    partial: number;
    not_evidenced: number;
    total: number;
}

interface ShowProps {
    application: {
        id: number;
        company_name: string;
        job_title: string;
        job_location: string | null;
        job_description: string;
        job_qualifications: string | null;
        language: string;
        notes: string | null;
        status: string;
        status_label: string;
        created_at: string;
        updated_at: string;
        documents?: {
            id: number;
            type: string;
            type_label: string;
            version: number;
            generation_status: 'pending' | 'processing' | 'completed' | 'failed';
        }[];
    };
    analysis: Analysis | null;
    statuses: Record<string, string>;
    hasProfile: boolean;
    flash?: { success?: string; error?: string };
}

const categoryLabels: Record<string, string> = {
    responsibility: 'Tanggung jawab',
    required_qualification: 'Kualifikasi wajib',
    preferred_qualification: 'Kualifikasi diutamakan',
    skill: 'Skill',
    tool: 'Tools',
    seniority: 'Senioritas',
};

const matchMeta: Record<string, { label: string; badge: string; ring: string }> = {
    matched: { label: 'Cocok — ada bukti', badge: 'badge-success', ring: 'border-success' },
    partial: { label: 'Sebagian — perlu konfirmasi', badge: 'badge-warning', ring: 'border-warning' },
    not_evidenced: { label: 'Belum ada bukti', badge: 'badge-danger', ring: 'border-danger' },
};

const evidenceLabel = (ref: string): string => {
    const [type, id] = ref.split(':');
    const labels: Record<string, string> = {
        experience: 'Pengalaman',
        education: 'Pendidikan',
        skill: 'Skill',
        project: 'Proyek',
        certification: 'Sertifikasi',
    };

    return `${labels[type] ?? type} #${id}`;
};

export default function ApplicationShow({ application, analysis, statuses, hasProfile, flash }: ShowProps) {
    const [tab, setTab] = useState<'fit' | 'documents' | 'details'>('fit');
    const [statusValue, setStatusValue] = useState(application.status);
    const running = analysis?.status === 'pending' || analysis?.status === 'processing';
    const [polling, setPolling] = useState(running);
    const pollRef = useRef(false);

    // Poll while the analysis is queued/running.
    useEffect(() => {
        if (!polling || pollRef.current) return;
        pollRef.current = true;

        const timer = setInterval(async () => {
            try {
                const res = await fetch(route('applications.analysis.status', application.id), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const payload = await res.json();

                if (payload.status === 'completed' || payload.status === 'failed' || payload.status === null) {
                    setPolling(false);
                    router.reload();
                }
            } catch {
                // keep polling
            }
        }, 3000);

        return () => {
            clearInterval(timer);
            pollRef.current = false;
        };
    }, [polling, application.id]);

    const rerun = () => {
        router.post(route('applications.analyze', application.id), {}, {
            onSuccess: () => setPolling(true),
        });
    };

    const changeStatus = (value: string) => {
        setStatusValue(value);
        router.patch(route('applications.status', application.id), { status: value }, { preserveScroll: true });
    };

    const result = analysis?.result ?? null;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-h1">{application.company_name}</h1>
                            <span className="badge-neutral">{application.job_title}</span>
                        </div>
                        <p className="mt-1 text-small text-foreground-muted">
                            {application.job_location ?? 'Lokasi tidak disebut'} · dibuat{' '}
                            {new Date(application.created_at).toLocaleDateString('id-ID')}
                        </p>
                    </div>
                    <div className="flex shrink-0 flex-wrap items-center gap-2">
                        <Link href={route('applications.edit', application.id)} className="btn-secondary">Edit</Link>
                        <Link href={route('applications.index')} className="btn-secondary">Daftar lamaran</Link>
                    </div>
                </div>
            }
        >
            <Head title={`${application.company_name} — Lamaran`} />

            {flash?.success && <div className="mb-6 text-small font-medium text-success">{flash.success}</div>}
            {flash?.error && <div className="mb-6 text-small font-medium text-danger">{flash.error}</div>}

            <div className="mb-6 flex flex-wrap items-center gap-3">
                <label className="flex items-center gap-2 text-small">
                    <span className="text-foreground-muted">Status lamaran</span>
                    <select className="field-input w-auto" value={statusValue} onChange={(e) => changeStatus(e.target.value)}>
                        {Object.entries(statuses).map(([value, label]) => (
                            <option key={value} value={value}>{label}</option>
                        ))}
                    </select>
                </label>

                <button type="button" className="btn-secondary" onClick={rerun} disabled={running}>
                    {running ? 'Menganalisis…' : 'Analisis ulang'}
                </button>
            </div>

            {/* Tabs */}
            <div className="mb-5 flex gap-2 border-b border-border" role="tablist" aria-label="Bagian lamaran">
                {([
                    ['fit', 'Kecocokan'],
                    ['documents', 'Dokumen'],
                    ['details', 'Detail lowongan'],
                ] as const).map(([key, label]) => (
                    <button
                        key={key}
                        type="button"
                        role="tab"
                        aria-selected={tab === key}
                        onClick={() => setTab(key)}
                        className={`-mb-px border-b-2 px-3 py-2 text-small font-medium transition-colors ${
                            tab === key
                                ? 'border-primary text-primary'
                                : 'border-transparent text-foreground-muted hover:text-foreground'
                        }`}
                    >
                        {label}
                    </button>
                ))}
            </div>

            {tab === 'fit' && (
                <div className="space-y-6">
                    {!hasProfile && (
                        <section className="card p-5 sm:p-6">
                            <p className="text-h3">Profil karier kosong</p>
                            <p className="mt-1 text-small text-foreground-muted">
                                Analisis kecocokan membutuhkan data profil. Lengkapi profil karier atau impor CV dulu.
                            </p>
                            <div className="mt-3 flex flex-wrap gap-2">
                                <Link href={route('career-profile.show')} className="btn-primary">Isi profil karier</Link>
                                <Link href={route('career-profile.import.index')} className="btn-secondary">Impor CV</Link>
                            </div>
                        </section>
                    )}

                    {analysis === null && hasProfile && (
                        <section className="card p-5 sm:p-6">
                            <p className="text-small text-foreground-muted">Belum ada analisis. Jalankan analisis untuk melihat kecocokan.</p>
                            <button type="button" className="btn-primary mt-3" onClick={rerun}>Analisis sekarang</button>
                        </section>
                    )}

                    {running && (
                        <section className="card p-5 sm:p-6">
                            <div className="flex items-center gap-3">
                                <svg className="h-5 w-5 animate-spin text-primary" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                                </svg>
                                <div>
                                    <p className="text-h3">Menganalisis kecocokan…</p>
                                    <p className="mt-0.5 text-small text-foreground-muted">
                                        AI sedang memetakan persyaratan lowongan ke bukti di profil Anda. Halaman memperbarui sendiri.
                                    </p>
                                </div>
                            </div>
                        </section>
                    )}

                    {analysis?.status === 'failed' && (
                        <section className="card p-5 sm:p-6">
                            <p className="text-h3 text-danger">Analisis gagal</p>
                            <p className="mt-1 text-small text-foreground-muted">
                                {analysis.error_message ?? 'Terjadi kesalahan saat menganalisis.'}
                            </p>
                            <button type="button" className="btn-primary mt-3" onClick={rerun}>Coba lagi</button>
                        </section>
                    )}

                    {analysis?.status === 'completed' && result && (
                        <>
                            <section className="card p-5 sm:p-6">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <h2 className="text-h3">Ringkasan cakupan</h2>
                                        <p className="mt-1 text-small text-foreground-muted">{result.summary}</p>
                                    </div>
                                    <span className="badge-neutral shrink-0">
                                        {new Date(analysis.created_at).toLocaleString('id-ID')}
                                    </span>
                                </div>

                                <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    <div className="rounded-xl border border-success bg-success-soft p-4">
                                        <p className="text-h2 text-success">{analysis.matched}</p>
                                        <p className="text-small text-foreground-muted">Cocok (ada bukti)</p>
                                    </div>
                                    <div className="rounded-xl border border-warning bg-warning-soft p-4">
                                        <p className="text-h2 text-warning">{analysis.partial}</p>
                                        <p className="text-small text-foreground-muted">Sebagian</p>
                                    </div>
                                    <div className="rounded-xl border border-danger bg-danger-soft p-4">
                                        <p className="text-h2 text-danger">{analysis.not_evidenced}</p>
                                        <p className="text-small text-foreground-muted">Belum ada bukti</p>
                                    </div>
                                </div>

                                <p className="mt-4 text-caption text-foreground-subtle">
                                    Ini heuristik internal berbasis bukti profil — bukan prediksi lolos screening ATS. Periksa tiap
                                    persyaratan di bawah dan lengkapi bukti yang memang Anda miliki.
                                </p>
                            </section>

                            {/* Requirements by status */}
                            {(['matched', 'partial', 'not_evidenced'] as const).map((status) => {
                                const items = result.requirements.filter((requirement) => requirement.match_status === status);
                                if (items.length === 0) return null;
                                const meta = matchMeta[status];

                                return (
                                    <section key={status} className="card p-5 sm:p-6">
                                        <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                                            <h2 className="text-h3">{meta.label}</h2>
                                            <span className={meta.badge}>{items.length} persyaratan</span>
                                        </div>

                                        <ul className="space-y-3">
                                            {items.map((requirement, index) => (
                                                <li key={`${status}-${index}`} className={`rounded-xl border ${meta.ring} bg-surface-muted p-4`}>
                                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                                        <p className="min-w-0 flex-1 text-body text-foreground">{requirement.text}</p>
                                                        <span className="badge-neutral shrink-0">
                                                            {categoryLabels[requirement.category] ?? requirement.category}
                                                            {requirement.priority === 'must_have' ? ' · wajib' : ' · diutamakan'}
                                                        </span>
                                                    </div>

                                                    {requirement.evidence_refs.length > 0 && (
                                                        <div className="mt-2 flex flex-wrap items-center gap-1.5">
                                                            <span className="text-caption text-foreground-subtle">Bukti:</span>
                                                            {requirement.evidence_refs.map((ref) => (
                                                                <span key={ref} className="badge-neutral">{evidenceLabel(ref)}</span>
                                                            ))}
                                                            <Link
                                                                href={route('career-profile.show')}
                                                                className="text-caption text-primary hover:underline"
                                                            >
                                                                lihat di profil
                                                            </Link>
                                                        </div>
                                                    )}

                                                    {requirement.recommendation && (
                                                        <p className="mt-2 text-small text-foreground-muted">
                                                            <span className="font-medium">Saran:</span> {requirement.recommendation}
                                                        </p>
                                                    )}
                                                </li>
                                            ))}
                                        </ul>
                                    </section>
                                );
                            })}

                            {/* Keywords */}
                            {result.keyword_suggestions.length > 0 && (
                                <section className="card p-5 sm:p-6">
                                    <h2 className="text-h3">Kata kunci lowongan</h2>
                                    <p className="mt-0.5 text-small text-foreground-muted">
                                        Kata kunci penting dari JD dan apakah profil Anda sudah mendukungnya.
                                    </p>

                                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <p className="text-caption uppercase tracking-wide text-foreground-subtle">Didukung profil</p>
                                            <ul className="mt-2 space-y-1.5">
                                                {result.keyword_suggestions.filter((keyword) => keyword.supported_by_profile).map((keyword) => (
                                                    <li key={keyword.keyword} className="flex items-center gap-2 text-small">
                                                        <span className="text-success" aria-hidden="true">●</span>
                                                        <span>{keyword.keyword}</span>
                                                    </li>
                                                ))}
                                                {result.keyword_suggestions.filter((keyword) => keyword.supported_by_profile).length === 0 && (
                                                    <li className="text-small text-foreground-muted">Belum ada.</li>
                                                )}
                                            </ul>
                                        </div>
                                        <div>
                                            <p className="text-caption uppercase tracking-wide text-foreground-subtle">Belum ada bukti</p>
                                            <ul className="mt-2 space-y-1.5">
                                                {result.keyword_suggestions.filter((keyword) => !keyword.supported_by_profile).map((keyword) => (
                                                    <li key={keyword.keyword} className="flex items-center gap-2 text-small">
                                                        <span className="text-danger" aria-hidden="true">○</span>
                                                        <span>{keyword.keyword}</span>
                                                    </li>
                                                ))}
                                                {result.keyword_suggestions.filter((keyword) => !keyword.supported_by_profile).length === 0 && (
                                                    <li className="text-small text-foreground-muted">Semua kata kunci utama sudah didukung.</li>
                                                )}
                                            </ul>
                                        </div>
                                    </div>

                                    {result.seniority && (
                                        <p className="mt-4 text-small text-foreground-muted">
                                            <span className="font-medium">Senioritas yang diminta:</span> {result.seniority}
                                        </p>
                                    )}
                                </section>
                            )}
                        </>
                    )}
                </div>
            )}

            {tab === 'documents' && (
                <div className="space-y-6">
                    <section className="card p-5 sm:p-6">
                        <h2 className="text-h3">Buat dokumen</h2>
                        <p className="mt-0.5 text-small text-foreground-muted">
                            CV dan cover letter dibuat dari profil Anda + analisis lowongan ini. Setiap generate membuat versi baru.
                        </p>

                        {analysis?.status !== 'completed' && (
                            <p className="mt-3 text-small text-warning">
                                Analisis kecocokan belum selesai. Jalankan analisis dulu agar dokumen selaras dengan lowongan.
                            </p>
                        )}

                        <div className="mt-4 flex flex-wrap gap-2">
                            <button
                                type="button"
                                className="btn-primary"
                                disabled={analysis?.status !== 'completed'}
                                onClick={() => router.post(route('documents.store', application.id), { type: 'resume' })}
                            >
                                Buat CV
                            </button>
                            <button
                                type="button"
                                className="btn-secondary"
                                disabled={analysis?.status !== 'completed'}
                                onClick={() => router.post(route('documents.store', application.id), { type: 'cover_letter' })}
                            >
                                Buat Cover Letter
                            </button>
                        </div>
                    </section>

                    <section className="card p-5 sm:p-6">
                        <h2 className="text-h3">Dokumen lamaran ini</h2>

                        {(application.documents ?? []).length === 0 ? (
                            <p className="mt-3 text-small text-foreground-muted">Belum ada dokumen untuk lamaran ini.</p>
                        ) : (
                            <div className="mt-4 overflow-x-auto">
                                <table className="w-full min-w-[560px] text-left text-small">
                                    <thead>
                                        <tr className="border-b border-border text-caption uppercase tracking-wide text-foreground-subtle">
                                            <th className="py-2 pr-4 font-medium">Dokumen</th>
                                            <th className="py-2 pr-4 font-medium">Versi</th>
                                            <th className="py-2 pr-4 font-medium">Status</th>
                                            <th className="py-2 text-right font-medium">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {(application.documents ?? []).map((document) => (
                                            <tr key={document.id} className="border-b border-border last:border-0">
                                                <td className="py-3 pr-4 font-medium">{document.type_label}</td>
                                                <td className="py-3 pr-4 text-foreground-muted">v{document.version}</td>
                                                <td className="py-3 pr-4">
                                                    {document.generation_status === 'completed' && <span className="badge-success">Siap</span>}
                                                    {(document.generation_status === 'pending' || document.generation_status === 'processing') && <span className="badge-warning">Diproses…</span>}
                                                    {document.generation_status === 'failed' && <span className="badge-danger">Gagal</span>}
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
                </div>
            )}

            {tab === 'details' && (
                <div className="space-y-6">
                    <section className="card p-5 sm:p-6">
                        <h2 className="text-h3">Deskripsi lowongan</h2>
                        <pre className="mt-3 whitespace-pre-wrap break-words font-sans text-body text-foreground">
                            {application.job_description}
                        </pre>
                    </section>

                    {application.job_qualifications && (
                        <section className="card p-5 sm:p-6">
                            <h2 className="text-h3">Kualifikasi</h2>
                            <pre className="mt-3 whitespace-pre-wrap break-words font-sans text-body text-foreground">
                                {application.job_qualifications}
                            </pre>
                        </section>
                    )}

                    {application.notes && (
                        <section className="card p-5 sm:p-6">
                            <h2 className="text-h3">Catatan</h2>
                            <pre className="mt-3 whitespace-pre-wrap break-words font-sans text-body text-foreground">
                                {application.notes}
                            </pre>
                        </section>
                    )}
                </div>
            )}
        </AuthenticatedLayout>
    );
}