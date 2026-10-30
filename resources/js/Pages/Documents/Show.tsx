import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

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

interface ExportRow {
    id: number;
    format: string;
    format_label: string;
    filename: string;
    file_size: number;
    status: string;
    created_at: string;
}

interface Revision {
    id: number;
    source: string;
    source_label: string;
    word_count: number;
    created_at: string;
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
    section_order: string[];
    word_count: number;
    last_saved_at: string | null;
    template_key: string;
}

interface ShowProps {
    document: DocumentPayload;
    application: { id: number; company_name: string; job_title: string; language: string };
    sections: Record<string, string>;
    tones: Record<string, string>;
    types: Record<string, string>;
    templates: Record<string, string>;
    revisions: Revision[];
    word_limit: number;
    overflow_warning: string | null;
    exports: ExportRow[];
    export_formats: Record<string, string>;
    flash?: { success?: string; error?: string };
}

type SaveState = 'idle' | 'dirty' | 'saving' | 'saved' | 'error';

const evidenceLabel = (ref: string): string => {
    const [type, id] = ref.split(':');
    const labels: Record<string, string> = {
        experience: 'Pengalaman', education: 'Pendidikan', skill: 'Skill',
        project: 'Proyek', certification: 'Sertifikasi',
    };
    return `${labels[type] ?? type} #${id}`;
};

const AUTOSAVE_DELAY_MS = 1500;

function formatSize(bytes: number): string {
    if (!bytes) return '—';
    const mb = bytes / (1024 * 1024);
    if (mb >= 1) return `${mb.toFixed(1)} MB`;
    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

function xsrfToken(): string {
    const match = document.cookie.split('; ').find((row) => row.startsWith('XSRF-TOKEN='));

    return match ? decodeURIComponent(match.split('=')[1]) : '';
}

export default function DocumentShow(props: ShowProps) {
    const { document, application, sections, templates, revisions, word_limit, overflow_warning, exports, export_formats, flash } = props;

    const [polling, setPolling] = useState(
        document.generation_status === 'pending' || document.generation_status === 'processing',
    );
    const pollingRef = useRef(false);

    const [content, setContent] = useState<Content>(document.content ?? {});
    const [sectionOrder, setSectionOrder] = useState<string[]>(document.section_order ?? []);
    const [templateKey, setTemplateKey] = useState<string>(document.template_key ?? 'ats_plain');
    const [saveState, setSaveState] = useState<SaveState>('idle');
    const [savedAtLabel, setSavedAtLabel] = useState<string | null>(null);
    const [wordCount, setWordCount] = useState<number>(document.word_count ?? 0);
    const [overflow, setOverflow] = useState<string | null>(overflow_warning);
    const [previewNonce, setPreviewNonce] = useState(0);
    const [showHistory, setShowHistory] = useState(false);

    const dirtyRef = useRef(false);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const running = document.generation_status === 'pending' || document.generation_status === 'processing';
    const isResume = document.type === 'resume';

    // Poll while generation is running.
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

    const persist = useCallback(async () => {
        setSaveState('saving');

        try {
            const res = await fetch(route('documents.autosave', document.id), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({
                    content,
                    section_order: isResume ? sectionOrder : null,
                    template_key: templateKey,
                }),
            });

            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const payload = await res.json();
            setSaveState('saved');
            setSavedAtLabel(payload.saved_at_label ?? null);
            setWordCount(payload.word_count ?? 0);
            setOverflow(payload.overflow_warning ?? null);
            dirtyRef.current = false;
            setPreviewNonce((n) => n + 1);
        } catch {
            // Content stays in the editor; the user can retry explicitly.
            setSaveState('error');
        }
    }, [content, document.id, isResume, sectionOrder, templateKey]);

    const scheduleSave = useCallback(() => {
        dirtyRef.current = true;
        setSaveState('dirty');

        if (timerRef.current) clearTimeout(timerRef.current);
        timerRef.current = setTimeout(() => void persist(), AUTOSAVE_DELAY_MS);
    }, [persist]);

    // Warn before leaving with unsaved edits.
    useEffect(() => {
        const handler = (event: BeforeUnloadEvent) => {
            if (dirtyRef.current) {
                event.preventDefault();
                event.returnValue = '';
            }
        };

        window.addEventListener('beforeunload', handler);

        return () => {
            window.removeEventListener('beforeunload', handler);
            if (timerRef.current) clearTimeout(timerRef.current);
        };
    }, []);

    const updateContent = (patch: Partial<Content>) => {
        setContent((prev) => ({ ...prev, ...patch }));
        scheduleSave();
    };

    const updateExperienceBullet = (index: number, bulletIndex: number, value: string) => {
        const list = [...(content.experiences ?? [])];
        const bullets = [...(list[index]?.bullets ?? [])];
        bullets[bulletIndex] = value;
        list[index] = { ...list[index], bullets };
        updateContent({ experiences: list });
    };

    const moveSection = (index: number, direction: -1 | 1) => {
        const next = [...sectionOrder];
        const target = index + direction;

        if (target < 0 || target >= next.length) return;

        [next[index], next[target]] = [next[target], next[index]];
        setSectionOrder(next);
        scheduleSave();
    };

    const regenerate = (section: string | null) => {
        router.post(route('documents.regenerate', document.id), section ? { section } : {}, {
            onSuccess: () => setPolling(true),
        });
    };

    const createExport = (format: string) => {
        router.post(route('documents.exports.store', document.id), { format }, { preserveScroll: true });
    };

    const deleteExport = (exportId: number) => {
        if (!window.confirm('Hapus file ekspor ini?')) return;
        router.delete(route('exports.destroy', exportId), { preserveScroll: true });
    };

    const restore = (revisionId: number) => {
        if (!window.confirm('Pulihkan versi ini? Isi saat ini akan disimpan sebagai revisi baru.')) return;
        router.post(route('documents.revisions.restore', [document.id, revisionId]), {}, { preserveScroll: true });
    };

    const saveLabel: Record<SaveState, string> = {
        idle: 'Tersimpan',
        dirty: 'Belum disimpan…',
        saving: 'Menyimpan…',
        saved: savedAtLabel ? `Tersimpan ${savedAtLabel}` : 'Tersimpan',
        error: 'Gagal menyimpan — coba lagi',
    };

    const saveTone: Record<SaveState, string> = {
        idle: 'text-foreground-subtle',
        dirty: 'text-warning',
        saving: 'text-foreground-muted',
        saved: 'text-success',
        error: 'text-danger',
    };

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
                    <div className="flex shrink-0 flex-wrap items-center gap-2">
                        <span className={`text-caption ${saveTone[saveState]}`} role="status" aria-live="polite">
                            {saveLabel[saveState]}
                        </span>
                        {saveState === 'error' && (
                            <button type="button" className="btn-secondary" onClick={() => void persist()}>
                                Coba simpan lagi
                            </button>
                        )}
                        <Link href={route('applications.show', application.id)} className="btn-secondary">Lamaran</Link>
                        <Link href={route('documents.index')} className="btn-secondary">Semua dokumen</Link>
                    </div>
                </div>
            }
        >
            <Head title={document.title} />

            {flash?.success && <div className="mb-6 text-small font-medium text-success">{flash.success}</div>}
            {flash?.error && <div className="mb-6 text-small font-medium text-danger">{flash.error}</div>}

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

            {document.generation_status === 'completed' && (
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

                    {overflow && (
                        <section className="card mb-6 border-warning bg-warning-soft p-4 sm:p-5">
                            <p className="text-small text-warning">{overflow}</p>
                        </section>
                    )}

                    <div className="grid gap-6 lg:grid-cols-[1fr_400px]">
                        {/* Editor */}
                        <div className="space-y-6">
                            {isResume && (
                                <section className="card p-5 sm:p-6">
                                    <h2 className="text-h3">Urutan bagian</h2>
                                    <p className="mt-0.5 text-small text-foreground-muted">
                                        Urutan ini dipakai di preview dan saat ekspor nanti.
                                    </p>
                                    <ul className="mt-3 space-y-2">
                                        {sectionOrder.map((section, index) => (
                                            <li
                                                key={section}
                                                className="flex items-center justify-between gap-2 rounded-lg border border-border bg-surface-muted px-3 py-2"
                                            >
                                                <span className="text-small">{sections[section] ?? section}</span>
                                                <span className="flex gap-1">
                                                    <button
                                                        type="button"
                                                        className="btn-secondary px-2 py-1"
                                                        onClick={() => moveSection(index, -1)}
                                                        disabled={index === 0}
                                                        aria-label={`Naikkan ${sections[section] ?? section}`}
                                                    >
                                                        ↑
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="btn-secondary px-2 py-1"
                                                        onClick={() => moveSection(index, 1)}
                                                        disabled={index === sectionOrder.length - 1}
                                                        aria-label={`Turunkan ${sections[section] ?? section}`}
                                                    >
                                                        ↓
                                                    </button>
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            )}

                            {isResume ? (
                                <>
                                    <section className="card p-5 sm:p-6">
                                        <div className="flex items-center justify-between gap-3">
                                            <h2 className="text-h3">{sections.summary}</h2>
                                            <button type="button" className="btn-ghost text-caption" onClick={() => regenerate('summary')}>
                                                Regenerate
                                            </button>
                                        </div>
                                        <textarea
                                            rows={4}
                                            className="field-input mt-3"
                                            value={content.summary ?? ''}
                                            onChange={(e) => updateContent({ summary: e.target.value })}
                                        />
                                    </section>

                                    <section className="card p-5 sm:p-6">
                                        <div className="flex items-center justify-between gap-3">
                                            <h2 className="text-h3">{sections.experiences}</h2>
                                            <button type="button" className="btn-ghost text-caption" onClick={() => regenerate('experiences')}>
                                                Regenerate
                                            </button>
                                        </div>
                                        <div className="mt-4 space-y-4">
                                            {(content.experiences ?? []).map((experience, index) => (
                                                <div key={`${experience.organization}-${index}`} className="rounded-xl border border-border bg-surface-muted p-4">
                                                    <p className="text-h3">{experience.role}</p>
                                                    <p className="text-small text-foreground-muted">
                                                        {experience.organization} · {experience.start_date ?? '?'} –{' '}
                                                        {experience.is_current ? 'sekarang' : (experience.end_date ?? '?')}
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

                                    {(content.skills ?? []).length > 0 && (
                                        <section className="card p-5 sm:p-6">
                                            <h2 className="text-h3">{sections.skills}</h2>
                                            <div className="mt-3 flex flex-wrap gap-2">
                                                {(content.skills ?? []).map((skill, index) => (
                                                    <span key={`${skill.name}-${index}`} className="badge-neutral">{skill.name}</span>
                                                ))}
                                            </div>
                                        </section>
                                    )}
                                </>
                            ) : (
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
                                                value={content.recipient_line ?? ''}
                                                onChange={(e) => updateContent({ recipient_line: e.target.value })}
                                            />
                                        </label>

                                        <label className="block">
                                            <span className="field-label">Pembuka</span>
                                            <textarea
                                                rows={3}
                                                className="field-input"
                                                value={content.opening ?? ''}
                                                onChange={(e) => updateContent({ opening: e.target.value })}
                                            />
                                        </label>

                                        <div className="space-y-3">
                                            <span className="field-label">Isi</span>
                                            {(content.body_paragraphs ?? []).map((paragraph, index) => (
                                                <textarea
                                                    key={index}
                                                    rows={4}
                                                    className="field-input"
                                                    value={paragraph.text}
                                                    onChange={(e) => {
                                                        const list = [...(content.body_paragraphs ?? [])];
                                                        list[index] = { ...list[index], text: e.target.value };
                                                        updateContent({ body_paragraphs: list });
                                                    }}
                                                />
                                            ))}
                                        </div>

                                        <label className="block">
                                            <span className="field-label">Penutup</span>
                                            <textarea
                                                rows={3}
                                                className="field-input"
                                                value={content.closing ?? ''}
                                                onChange={(e) => updateContent({ closing: e.target.value })}
                                            />
                                        </label>

                                        <label className="block">
                                            <span className="field-label">Tanda tangan</span>
                                            <input
                                                className="field-input"
                                                value={content.signature ?? ''}
                                                onChange={(e) => updateContent({ signature: e.target.value })}
                                            />
                                        </label>
                                    </div>
                                </section>
                            )}
                        </div>

                        {/* Preview + history */}
                        <aside className="space-y-4">
                            <section className="card p-4 sm:p-5">
                                <div className="flex items-center justify-between gap-2">
                                    <h2 className="text-h3">Preview</h2>
                                    <select
                                        className="field-input w-auto py-1 text-caption"
                                        value={templateKey}
                                        onChange={(e) => {
                                            setTemplateKey(e.target.value);
                                            scheduleSave();
                                        }}
                                        aria-label="Template"
                                    >
                                        {Object.entries(templates).map(([value, label]) => (
                                            <option key={value} value={value}>{label}</option>
                                        ))}
                                    </select>
                                </div>

                                <p className="mt-1 text-caption text-foreground-subtle">
                                    {wordCount} kata · batas nyaman {word_limit}
                                </p>

                                <iframe
                                    key={previewNonce}
                                    title="Preview dokumen"
                                    src={`${route('documents.preview', document.id)}?v=${previewNonce}`}
                                    className="mt-3 h-[520px] w-full rounded-lg border border-border bg-white"
                                />
                            </section>

                            <section className="card p-4 sm:p-5">
                                <h2 className="text-h3">Unduh</h2>
                                <p className="mt-0.5 text-caption text-foreground-subtle">
                                    File dibuat dari konten yang sedang Anda lihat.
                                </p>

                                <div className="mt-3 flex flex-wrap gap-2">
                                    {Object.entries(export_formats).map(([format, label]) => (
                                        <button
                                            key={format}
                                            type="button"
                                            className="btn-primary"
                                            onClick={() => createExport(format)}
                                        >
                                            {label}
                                        </button>
                                    ))}
                                </div>

                                {overflow && (
                                    <p className="mt-2 text-caption text-warning">
                                        Dokumen melewati batas nyaman — hasil ekspor bisa lebih dari satu halaman.
                                    </p>
                                )}

                                {exports.length === 0 ? (
                                    <p className="mt-3 text-small text-foreground-muted">Belum ada file ekspor.</p>
                                ) : (
                                    <ul className="mt-3 space-y-2">
                                        {exports.map((row) => (
                                            <li key={row.id} className="rounded-lg border border-border px-3 py-2">
                                                <div className="flex items-center justify-between gap-2">
                                                    <span className="text-small font-medium">{row.format_label}</span>
                                                    <span className="text-caption text-foreground-subtle">{formatSize(row.file_size)}</span>
                                                </div>
                                                <p className="mt-0.5 truncate text-caption text-foreground-muted">{row.filename}</p>
                                                <div className="mt-1 flex items-center justify-between gap-2">
                                                    <span className="text-caption text-foreground-subtle">
                                                        {new Date(row.created_at).toLocaleString('id-ID')}
                                                    </span>
                                                    <span className="flex gap-1">
                                                        <a
                                                            href={route('exports.download', row.id)}
                                                            className="btn-secondary px-2 py-1 text-caption"
                                                        >
                                                            Unduh
                                                        </a>
                                                        <button
                                                            type="button"
                                                            className="btn-secondary px-2 py-1 text-caption text-danger"
                                                            onClick={() => deleteExport(row.id)}
                                                        >
                                                            Hapus
                                                        </button>
                                                    </span>
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </section>

                            <section className="card p-4 sm:p-5">
                                <div className="flex items-center justify-between gap-2">
                                    <h2 className="text-h3">Riwayat versi</h2>
                                    <button type="button" className="btn-ghost text-caption" onClick={() => setShowHistory((v) => !v)}>
                                        {showHistory ? 'Sembunyikan' : 'Lihat'}
                                    </button>
                                </div>

                                {showHistory && (
                                    <ul className="mt-3 max-h-72 space-y-2 overflow-y-auto">
                                        {revisions.length === 0 && (
                                            <li className="text-small text-foreground-muted">Belum ada revisi tersimpan.</li>
                                        )}
                                        {revisions.map((revision) => (
                                            <li key={revision.id} className="rounded-lg border border-border px-3 py-2">
                                                <div className="flex items-center justify-between gap-2">
                                                    <span className="text-caption text-foreground-muted">
                                                        {new Date(revision.created_at).toLocaleString('id-ID')}
                                                    </span>
                                                    <span className="badge-neutral">{revision.source_label}</span>
                                                </div>
                                                <div className="mt-1 flex items-center justify-between gap-2">
                                                    <span className="text-caption text-foreground-subtle">{revision.word_count} kata</span>
                                                    <button
                                                        type="button"
                                                        className="btn-secondary px-2 py-1 text-caption"
                                                        onClick={() => restore(revision.id)}
                                                    >
                                                        Pulihkan
                                                    </button>
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                )}

                                <button
                                    type="button"
                                    className="btn-secondary mt-3 w-full"
                                    onClick={() => regenerate(null)}
                                    disabled={running}
                                >
                                    Versi baru (generate ulang penuh)
                                </button>
                            </section>
                        </aside>
                    </div>
                </>
            )}
        </AuthenticatedLayout>
    );
}