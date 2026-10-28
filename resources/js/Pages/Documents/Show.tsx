import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

interface BulletRow {
    organization?: string;
    role?: string;
    start_date?: string | null;
    end_date?: string | null;
    is_current?: boolean;
    bullets?: string[];
    name?: string;
    institution?: string;
    degree?: string | null;
    field_of_study?: string | null;
    issuer?: string | null;
    issue_date?: string | null;
    technologies?: string[];
    category?: string | null;
    evidence_refs?: string[];
}

interface Content {
    summary?: string;
    skills?: BulletRow[];
    experiences?: BulletRow[];
    projects?: BulletRow[];
    education?: BulletRow[];
    certifications?: BulletRow[];
    languages?: string[];
    recipient_line?: string;
    opening?: string;
    body_paragraphs?: { text: string; evidence_refs?: string[] }[];
    closing?: string;
    signature?: string;
    unverified_claims?: string[];
    prompt_version?: string;
    dropped?: number;
}

interface DocumentPayload {
    id: number;
    type: 'resume' | 'cover_letter';
    type_label: string;
    title: string;
    version: number;
    language: string;
    tone: string | null;
    generation_status: 'pending' | 'processing' | 'completed' | 'failed';
    error_message: string | null;
    model_name: string | null;
    created_at: string;
    content: Content | null;
    unverified_claims: string[];
    application_id: number;
}

interface ShowProps {
    document: DocumentPayload;
    application: { id: number; company_name: string; job_title: string; language: string };
    sections: Record<string, string>;
    tones: Record<string, string>;
    types: Record<string, string>;
    flash?: { success?: string };
}

const evidenceLabel = (ref: string): string => {
    const [type, id] = ref.split(':');
    const labels: Record<string, string> = {
        experience: 'Pengalaman', education: 'Pendidikan', skill: 'Skill',
        project: 'Proyek', certification: 'Sertifikasi',
    };
    return `${labels[type] ?? type} #${id}`;
};

export default function DocumentShow({ document, application, sections, flash }: ShowProps) {
    const [polling, setPolling] = useState(
        document.generation_status === 'pending' || document.generation_status === 'processing',
    );
    const pollingRef = useRef(false);

    useEffect(() => {
        if (!polling || pollingRef.current) return;
        pollingRef.current = true;

        const timer = setInterval(async () => {
            try {
                const res = await fetch(route('documents.status', document.id), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const payload = await res.json();
                if (payload.status === 'completed' || payload.status === 'failed') {
                    setPolling(false);
                    router.reload();
                }
            } catch {
                // keep polling
            }
        }, 3000);

        return () => {
            clearInterval(timer);
            pollingRef.current = false;
        };
    }, [polling, document.id]);

    const running = document.generation_status === 'pending' || document.generation_status === 'processing';
    const content = document.content;

    const { data, setData, patch, processing } = useForm<{ content: Content }>({
        content: content ?? {},
    });

    const regenerate = (section: string | null) => {
        router.post(route('documents.regenerate', document.id), section ? { section } : {}, {
            onSuccess: () => setPolling(true),
        });
    };

    const save = () => {
        patch(route('documents.update', document.id), { preserveScroll: true });
    };

    const updateExperienceBullet = (index: number, bulletIndex: number, value: string) => {
        const list = [...(data.content.experiences ?? [])];
        const bullets = [...(list[index]?.bullets ?? [])];
        bullets[bulletIndex] = value;
        list[index] = { ...list[index], bullets };
        setData('content', { ...data.content, experiences: list });
    };

    const isResume = document.type === 'resume';

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                        <h1 className="text-h1">{document.type_label}</h1>
                        <p className="mt-1 text-small text-foreground-muted">
                            {application.company_name} · {application.job_title} · v{document.version}
                            {document.tone && ` · ${document.tone}`}
                        </p>
                    </div>
                    <div className="flex shrink-0 flex-wrap gap-2">
                        <Link href={route('applications.show', application.id)} className="btn-secondary">Lamaran</Link>
                        <Link href={route('documents.index')} className="btn-secondary">Semua dokumen</Link>
                    </div>
                </div>
            }
        >
            <Head title={document.title} />

            {flash?.success && <div className="mb-6 text-small font-medium text-success">{flash.success}</div>}

            {running && (
                <section className="card mb-6 p-5 sm:p-6">
                    <div className="flex items-center gap-3">
                        <svg className="h-5 w-5 animate-spin text-primary" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                        </svg>
                        <div>
                            <p className="text-h3">Menyusun dokumen…</p>
                            <p className="mt-0.5 text-small text-foreground-muted">
                                AI menulis berdasarkan profil dan analisis lowongan. Halaman memperbarui sendiri.
                            </p>
                        </div>
                    </div>
                </section>
            )}

            {document.generation_status === 'failed' && (
                <section className="card mb-6 p-5 sm:p-6">
                    <p className="text-h3 text-danger">Gagal membuat dokumen</p>
                    <p className="mt-1 text-small text-foreground-muted">{document.error_message ?? 'Terjadi kesalahan.'}</p>
                    <div className="mt-3 flex flex-wrap gap-2">
                        <button type="button" className="btn-primary" onClick={() => regenerate(null)}>Coba lagi</button>
                        <Link href={route('applications.show', application.id)} className="btn-secondary">
                            Kembali ke lamaran
                        </Link>
                    </div>
                </section>
            )}

            {document.generation_status === 'completed' && content && (
                <>
                    {document.unverified_claims.length > 0 && (
                        <section className="card mb-6 border-warning bg-warning-soft p-4 sm:p-5">
                            <p className="text-h3 text-warning">Perlu Anda periksa</p>
                            <ul className="mt-2 list-inside list-disc space-y-1 text-small text-foreground-muted">
                                {document.unverified_claims.map((claim, index) => (
                                    <li key={`${claim}-${index}`}>{claim}</li>
                                ))}
                            </ul>
                            <p className="mt-2 text-caption text-foreground-subtle">
                                AI tidak bisa membuktikan klaim di atas dari profil Anda. Hapus atau perbaiki sebelum dipakai.
                            </p>
                        </section>
                    )}

                    <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
                        {/* Document body */}
                        <div className="space-y-6">
                            {isResume ? (
                                <>
                                    <section className="card p-5 sm:p-6">
                                        <div className="flex items-center justify-between gap-3">
                                            <h2 className="text-h3">Ringkasan</h2>
                                            <button type="button" className="btn-ghost text-caption" onClick={() => regenerate('summary')}>
                                                Regenerate bagian
                                            </button>
                                        </div>
                                        <textarea
                                            rows={4}
                                            className="field-input mt-3"
                                            value={data.content.summary ?? ''}
                                            onChange={(e) => setData('content', { ...data.content, summary: e.target.value })}
                                        />
                                    </section>

                                    {(data.content.experiences ?? []).length > 0 && (
                                        <section className="card p-5 sm:p-6">
                                            <div className="flex items-center justify-between gap-3">
                                                <h2 className="text-h3">Pengalaman</h2>
                                                <button type="button" className="btn-ghost text-caption" onClick={() => regenerate('experiences')}>
                                                    Regenerate bagian
                                                </button>
                                            </div>
                                            <div className="mt-4 space-y-4">
                                                {(data.content.experiences ?? []).map((experience, index) => (
                                                    <div key={`${experience.organization}-${index}`} className="rounded-xl border border-border bg-surface-muted p-4">
                                                        <p className="text-h3">{experience.role}</p>
                                                        <p className="text-small text-foreground-muted">
                                                            {experience.organization}
                                                            {' · '}
                                                            {experience.start_date ?? '?'} – {experience.is_current ? 'sekarang' : (experience.end_date ?? '?')}
                                                        </p>
                                                        <div className="mt-3 space-y-2">
                                                            {(experience.bullets ?? []).map((bullet, bulletIndex) => (
                                                                <textarea
                                                                    key={bulletIndex}
                                                                    rows={2}
                                                                    className="field-input"
                                                                    value={bullet}
                                                                    onChange={(e) => updateExperienceBullet(index, bulletIndex, e.target.value)}
                                                                />
                                                            ))}
                                                        </div>
                                                        {(experience.evidence_refs ?? []).length > 0 && (
                                                            <div className="mt-2 flex flex-wrap gap-1.5">
                                                                {(experience.evidence_refs ?? []).map((ref) => (
                                                                    <span key={ref} className="badge-neutral">{evidenceLabel(ref)}</span>
                                                                ))}
                                                            </div>
                                                        )}
                                                    </div>
                                                ))}
                                            </div>
                                        </section>
                                    )}

                                    {(data.content.skills ?? []).length > 0 && (
                                        <section className="card p-5 sm:p-6">
                                            <h2 className="text-h3">Skill</h2>
                                            <div className="mt-3 flex flex-wrap gap-2">
                                                {(data.content.skills ?? []).map((skill, index) => (
                                                    <span key={`${skill.name}-${index}`} className="badge-neutral">{skill.name}</span>
                                                ))}
                                            </div>
                                        </section>
                                    )}

                                    {(data.content.projects ?? []).length > 0 && (
                                        <section className="card p-5 sm:p-6">
                                            <h2 className="text-h3">Proyek</h2>
                                            <div className="mt-3 space-y-3">
                                                {(data.content.projects ?? []).map((project, index) => (
                                                    <div key={`${project.name}-${index}`}>
                                                        <p className="text-h3">{project.name}</p>
                                                        {(project.bullets ?? []).map((bullet, bulletIndex) => (
                                                            <p key={bulletIndex} className="mt-1 text-small text-foreground-muted">• {bullet}</p>
                                                        ))}
                                                    </div>
                                                ))}
                                            </div>
                                        </section>
                                    )}

                                    {(data.content.education ?? []).length > 0 && (
                                        <section className="card p-5 sm:p-6">
                                            <h2 className="text-h3">Pendidikan</h2>
                                            <ul className="mt-3 space-y-2 text-small">
                                                {(data.content.education ?? []).map((education, index) => (
                                                    <li key={`${education.institution}-${index}`}>
                                                        <span className="font-medium">{education.institution}</span>
                                                        {education.degree ? ` — ${education.degree}` : ''}
                                                        {education.field_of_study ? ` (${education.field_of_study})` : ''}
                                                    </li>
                                                ))}
                                            </ul>
                                        </section>
                                    )}

                                    {(data.content.certifications ?? []).length > 0 && (
                                        <section className="card p-5 sm:p-6">
                                            <h2 className="text-h3">Sertifikasi</h2>
                                            <ul className="mt-3 space-y-2 text-small">
                                                {(data.content.certifications ?? []).map((certification, index) => (
                                                    <li key={`${certification.name}-${index}`}>
                                                        <span className="font-medium">{certification.name}</span>
                                                        {certification.issuer ? ` — ${certification.issuer}` : ''}
                                                    </li>
                                                ))}
                                            </ul>
                                        </section>
                                    )}

                                    {(data.content.languages ?? []).length > 0 && (
                                        <section className="card p-5 sm:p-6">
                                            <h2 className="text-h3">Bahasa</h2>
                                            <p className="mt-2 text-small text-foreground-muted">{(data.content.languages ?? []).join(', ')}</p>
                                        </section>
                                    )}
                                </>
                            ) : (
                                <>
                                    <section className="card p-5 sm:p-6">
                                        <div className="flex items-center justify-between gap-3">
                                            <h2 className="text-h3">Surat</h2>
                                            <button type="button" className="btn-ghost text-caption" onClick={() => regenerate(null)}>
                                                Regenerate surat
                                            </button>
                                        </div>

                                        <div className="mt-4 space-y-4">
                                            <label className="block">
                                                <span className="field-label">Sapaan</span>
                                                <input
                                                    className="field-input"
                                                    value={data.content.recipient_line ?? ''}
                                                    onChange={(e) => setData('content', { ...data.content, recipient_line: e.target.value })}
                                                />
                                            </label>

                                            <label className="block">
                                                <span className="field-label">Pembuka</span>
                                                <textarea
                                                    rows={3}
                                                    className="field-input"
                                                    value={data.content.opening ?? ''}
                                                    onChange={(e) => setData('content', { ...data.content, opening: e.target.value })}
                                                />
                                            </label>

                                            <div className="space-y-3">
                                                <span className="field-label">Isi</span>
                                                {(data.content.body_paragraphs ?? []).map((paragraph, index) => (
                                                    <textarea
                                                        key={index}
                                                        rows={4}
                                                        className="field-input"
                                                        value={paragraph.text}
                                                        onChange={(e) => {
                                                            const list = [...(data.content.body_paragraphs ?? [])];
                                                            list[index] = { ...list[index], text: e.target.value };
                                                            setData('content', { ...data.content, body_paragraphs: list });
                                                        }}
                                                    />
                                                ))}
                                            </div>

                                            <label className="block">
                                                <span className="field-label">Penutup</span>
                                                <textarea
                                                    rows={3}
                                                    className="field-input"
                                                    value={data.content.closing ?? ''}
                                                    onChange={(e) => setData('content', { ...data.content, closing: e.target.value })}
                                                />
                                            </label>

                                            <label className="block">
                                                <span className="field-label">Tanda tangan</span>
                                                <input
                                                    className="field-input"
                                                    value={data.content.signature ?? ''}
                                                    onChange={(e) => setData('content', { ...data.content, signature: e.target.value })}
                                                />
                                            </label>
                                        </div>
                                    </section>
                                </>
                            )}
                        </div>

                        {/* Sidebar */}
                        <aside className="space-y-4">
                            <section className="card p-4 sm:p-5">
                                <h2 className="text-h3">Info</h2>
                                <dl className="mt-3 space-y-2 text-small">
                                    <div className="flex justify-between gap-3">
                                        <dt className="text-foreground-muted">Versi</dt>
                                        <dd>v{document.version}</dd>
                                    </div>
                                    <div className="flex justify-between gap-3">
                                        <dt className="text-foreground-muted">Bahasa</dt>
                                        <dd>{document.language === 'en' ? 'English' : 'Indonesia'}</dd>
                                    </div>
                                    <div className="flex justify-between gap-3">
                                        <dt className="text-foreground-muted">Model</dt>
                                        <dd className="max-w-[60%] truncate text-right">{document.model_name ?? '—'}</dd>
                                    </div>
                                    {content.prompt_version && (
                                        <div className="flex justify-between gap-3">
                                            <dt className="text-foreground-muted">Prompt</dt>
                                            <dd>{content.prompt_version}</dd>
                                        </div>
                                    )}
                                    {typeof content.dropped === 'number' && content.dropped > 0 && (
                                        <div className="flex justify-between gap-3">
                                            <dt className="text-foreground-muted">Klaim dibuang</dt>
                                            <dd>{content.dropped}</dd>
                                        </div>
                                    )}
                                </dl>
                            </section>

                            <section className="card p-4 sm:p-5">
                                <h2 className="text-h3">Aksi</h2>
                                <div className="mt-3 space-y-2">
                                    <button type="button" className="btn-primary w-full" onClick={save} disabled={processing}>
                                        {processing ? 'Menyimpan…' : 'Simpan perubahan'}
                                    </button>
                                    <button type="button" className="btn-secondary w-full" onClick={() => regenerate(null)} disabled={running}>
                                        Versi baru (regenerate)
                                    </button>
                                </div>
                                <p className="mt-3 text-caption text-foreground-subtle">
                                    Regenerate membuat versi baru; versi lama tetap tersimpan.
                                </p>
                            </section>
                        </aside>
                    </div>
                </>
            )}
        </AuthenticatedLayout>
    );
}