import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import SectionCard from './Partials/SectionCard';
import ExperienceForm from './Partials/ExperienceForm';
import EducationForm from './Partials/EducationForm';
import SkillForm from './Partials/SkillForm';
import ProjectForm from './Partials/ProjectForm';
import CertificationForm from './Partials/CertificationForm';

interface FlashProps {
    success?: string;
}

interface CareerProfilePageProps {
    profile: any;
    contact: any;
    experiences: any[];
    educations: any[];
    skills: any[];
    projects: any[];
    certifications: any[];
    options: any;
    completion: any;
    flash: FlashProps;
}

export default function CareerProfileIndex(props: CareerProfilePageProps) {
    const { profile, contact, experiences, educations, skills, projects, certifications, options, completion, flash } = props;

    // Contact form
    const { data: cData, setData: cSetData, patch: cPatch, processing: cProc, errors: cErrors, reset: cReset } = useForm({
        full_name: contact?.full_name ?? '',
        email: contact?.email ?? '',
        phone: contact?.phone ?? '',
        location: contact?.location ?? '',
        linkedin_url: contact?.linkedin_url ?? '',
        portfolio_url: contact?.portfolio_url ?? '',
        github_url: contact?.github_url ?? '',
    });

    // Profile update form
    const { data: pData, setData: pSetData, patch: pPatch, processing: pProc, errors: pErrors } = useForm({
        title: profile?.title ?? '',
        summary: profile?.summary ?? '',
        default_language: profile?.default_language ?? options.languages[0],
        writing_style: profile?.writing_style ?? options.writingStyles[0],
        resume_length: profile?.resume_length ?? options.resumeLengths[0],
        date_format: profile?.date_format ?? options.dateFormats[0],
    });

    const { data: expData, setData: expSetData, post: expPost, patch: expPatch, processing: expProc, errors: expErrors, reset: expReset } = useForm({
        organization: '', job_title: '', employment_type: '', location: '',
        start_date: '', end_date: '', is_current: false, description: '', achievements: [''],
    });

    const { data: eduData, setData: eduSetData, post: eduPost, patch: eduPatch, processing: eduProc, errors: eduErrors, reset: eduReset } = useForm({
        institution: '', degree: '', field_of_study: '', start_date: '', end_date: '', description: '',
    });

    const { data: skiData, setData: skiSetData, post: skiPost, patch: skiPatch, processing: skiProc, errors: skiErrors, reset: skiReset } = useForm({
        name: '', category: '', proficiency: '',
    });

    const { data: proData, setData: proSetData, post: proPost, patch: proPatch, processing: proProc, errors: proErrors, reset: proReset } = useForm({
        name: '', role: '', description: '', technologies: [''], url: '', start_date: '', end_date: '',
    });

    const { data: certData, setData: certSetData, post: certPost, patch: certPatch, processing: certProc, errors: certErrors, reset: certReset } = useForm({
        name: '', issuer: '', issue_date: '', expiry_date: '', credential_url: '',
    });

    const [openModal, setOpenModal] = useState<'experience' | 'education' | 'skill' | 'project' | 'certification' | null>(null);
    const [editingId, setEditingId] = useState<number | null>(null);

    const closeModal = () => {
        setOpenModal(null);
        setEditingId(null);
        expReset(); eduReset(); skiReset(); proReset(); certReset();
    };

    const editExp = (e: any) => { expSetData(e); setEditingId(e.id); setOpenModal('experience'); };
    const editEdu = (e: any) => { eduSetData(e); setEditingId(e.id); setOpenModal('education'); };
    const editSki = (e: any) => { skiSetData(e); setEditingId(e.id); setOpenModal('skill'); };
    const editPro = (e: any) => { proSetData(e); setEditingId(e.id); setOpenModal('project'); };
    const editCert = (e: any) => { certSetData(e); setEditingId(e.id); setOpenModal('certification'); };

    const addExp = () => { expReset(); setEditingId(null); setOpenModal('experience'); };
    const addEdu = () => { eduReset(); setEditingId(null); setOpenModal('education'); };
    const addSki = () => { skiReset(); setEditingId(null); setOpenModal('skill'); };
    const addPro = () => { proReset(); setEditingId(null); setOpenModal('project'); };
    const addCert = () => { certReset(); setEditingId(null); setOpenModal('certification'); };

    const handleExpSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingId) {
            expPatch(route('career-profile.experiences.update', editingId), {
                onSuccess: closeModal,
            });
        } else {
            expPost(route('career-profile.experiences.store'), {
                onSuccess: closeModal,
            });
        }
    };

    const handleEduSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingId) {
            eduPatch(route('career-profile.educations.update', editingId), {
                onSuccess: closeModal,
            });
        } else {
            eduPost(route('career-profile.educations.store'), {
                onSuccess: closeModal,
            });
        }
    };

    const handleSkiSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingId) {
            skiPatch(route('career-profile.skills.update', editingId), {
                onSuccess: closeModal,
            });
        } else {
            skiPost(route('career-profile.skills.store'), {
                onSuccess: closeModal,
            });
        }
    };

    const handleProSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingId) {
            proPatch(route('career-profile.projects.update', editingId), {
                onSuccess: closeModal,
            });
        } else {
            proPost(route('career-profile.projects.store'), {
                onSuccess: closeModal,
            });
        }
    };

    const handleCertSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingId) {
            certPatch(route('career-profile.certifications.update', editingId), {
                onSuccess: closeModal,
            });
        } else {
            certPost(route('career-profile.certifications.store'), {
                onSuccess: closeModal,
            });
        }
    };

    const deleteItem = (type: string, id: number) => {
        if (!window.confirm('Hapus item ini?')) return;
        const routes: Record<string, string> = {
            experience: 'career-profile.experiences.destroy',
            education: 'career-profile.educations.destroy',
            skill: 'career-profile.skills.destroy',
            project: 'career-profile.projects.destroy',
            certification: 'career-profile.certifications.destroy',
        };
        useForm({}).delete(route(routes[type], id));
    };

    const reorder = (type: string, ids: number[]) => {
        const routes: Record<string, string> = {
            experience: 'career-profile.experiences.reorder',
            education: 'career-profile.educations.reorder',
            skill: 'career-profile.skills.reorder',
            project: 'career-profile.projects.reorder',
            certification: 'career-profile.certifications.reorder',
        };
        useForm({ ids }).post(route(routes[type]));
    };

    // Move up/down helpers
    const move = (list: any[], idx: number, dir: number) => {
        const newIdx = idx + dir;
        if (newIdx < 0 || newIdx >= list.length) return;
        const copy = [...list];
        [copy[idx], copy[newIdx]] = [copy[newIdx], copy[idx]];
        reorder(typeMap(list[0]), copy.map(i => i.id));
    };

    const typeMap = (item: any) => {
        if (item.organization) return 'experience';
        if (item.institution) return 'education';
        if (item.name && item.issuer !== undefined) return 'certification';
        if (item.name && item.role !== undefined) return 'project';
        return 'skill';
    };

    // Completion summary
    const progress = completion?.percentage ?? 0;
    const nextLabel = completion?.next_section
        ? (({
            contact: 'Kontak',
            summary: 'Ringkasan',
            experiences: 'Pengalaman',
            educations: 'Pendidikan',
            skills: 'Skill',
            projects: 'Proyek',
            certifications: 'Sertifikasi',
          } as Record<string, string>)[completion.next_section])
        : null;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-h1">Career Profile</h1>
                        <p className="mt-1 text-small text-foreground-muted">
                            Kelola master profile yang dipakai di semua lamaran.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="card p-3 sm:p-4 w-64">
                            <div className="flex items-center justify-between">
                                <span className="text-caption text-foreground-muted">Kelengkapan</span>
                                <span className="text-h3 font-semibold text-primary">{progress}%</span>
                            </div>
                            <div className="mt-2 h-1.5 w-full rounded-full bg-surface-sunken overflow-hidden">
                                <div className="h-full bg-primary transition-all duration-300" style={{ width: `${progress}%` }} />
                            </div>
                            {nextLabel && (
                                <p className="mt-2 text-caption text-foreground-muted">
                                    Selanjutnya: <span className="font-medium">{nextLabel}</span>
                                </p>
                            )}
                        </div>
                    </div>
                </div>
            }
        >
            <Head title="Career Profile" />

            {(flash?.success) && (
                <div className="mb-6 text-small font-medium text-success">
                    {flash.success}
                </div>
            )}

            {/* Profile & Contact */}
            <SectionCard title="Kontak & Ringkasan" description="Informasi kontak dan ringkasan profesional">
                <form onSubmit={(e) => { e.preventDefault(); cPatch(route('career-profile.contact.update'), { onSuccess: () => cReset() }); }} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="sm:col-span-2">
                            <label className="field-label" htmlFor="full_name">Nama lengkap *</label>
                            <input id="full_name" value={cData.full_name} onChange={e => cSetData('full_name', e.target.value)} className="field-input" required />
                            {cErrors.full_name && <p className="field-error">{cErrors.full_name}</p>}
                        </div>
                        <div>
                            <label className="field-label" htmlFor="email">Email</label>
                            <input id="email" type="email" value={cData.email} onChange={e => cSetData('email', e.target.value)} className="field-input" />
                            {cErrors.email && <p className="field-error">{cErrors.email}</p>}
                        </div>
                        <div>
                            <label className="field-label" htmlFor="phone">Telepon</label>
                            <input id="phone" value={cData.phone} onChange={e => cSetData('phone', e.target.value)} className="field-input" />
                        </div>
                        <div className="sm:col-span-2">
                            <label className="field-label" htmlFor="location">Lokasi (kota/negara)</label>
                            <input id="location" value={cData.location} onChange={e => cSetData('location', e.target.value)} className="field-input" />
                        </div>
                    </div>

                    <div className="border-t border-border pt-4">
                        <h3 className="text-h3 mb-3">Link Profil</h3>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label className="field-label" htmlFor="linkedin_url">LinkedIn</label>
                                <input id="linkedin_url" value={cData.linkedin_url} onChange={e => cSetData('linkedin_url', e.target.value)} className="field-input" placeholder="https://linkedin.com/in/..." />
                            </div>
                            <div>
                                <label className="field-label" htmlFor="portfolio_url">Portofolio</label>
                                <input id="portfolio_url" value={cData.portfolio_url} onChange={e => cSetData('portfolio_url', e.target.value)} className="field-input" placeholder="https://..." />
                            </div>
                            <div>
                                <label className="field-label" htmlFor="github_url">GitHub</label>
                                <input id="github_url" value={cData.github_url} onChange={e => cSetData('github_url', e.target.value)} className="field-input" placeholder="https://github.com/..." />
                            </div>
                        </div>
                    </div>

                    <div className="border-t border-border pt-4">
                        <h3 className="text-h3 mb-3">Ringkasan & Preferensi</h3>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <label className="field-label" htmlFor="summary">Ringkasan profesional</label>
                                <textarea id="summary" rows={4} value={pData.summary} onChange={e => pSetData('summary', e.target.value)} className="field-input" maxLength={4000} />
                            </div>
                            <div>
                                <label className="field-label" htmlFor="title">Judul Profil</label>
                                <input id="title" value={pData.title} onChange={e => pSetData('title', e.target.value)} className="field-input" placeholder="Contoh: Senior Full Stack Engineer" />
                            </div>
                            <div>
                                <label className="field-label" htmlFor="default_language">Bahasa dokumen</label>
                                <select id="default_language" value={pData.default_language} onChange={e => pSetData('default_language', e.target.value)} className="field-input">
                                    {options.languages.map((l: string) => <option key={l} value={l}>{l === 'id' ? 'Indonesia' : 'English'}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="field-label" htmlFor="writing_style">Gaya penulisan</label>
                                <select id="writing_style" value={pData.writing_style} onChange={e => pSetData('writing_style', e.target.value)} className="field-input">
                                    {options.writingStyles.map((s: string) => <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="field-label" htmlFor="resume_length">Panjang CV</label>
                                <select id="resume_length" value={pData.resume_length} onChange={e => pSetData('resume_length', e.target.value)} className="field-input">
                                    {options.resumeLengths.map((l: string) => <option key={l} value={l}>{l.charAt(0).toUpperCase() + l.slice(1)}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="field-label" htmlFor="date_format">Format tanggal</label>
                                <select id="date_format" value={pData.date_format} onChange={e => pSetData('date_format', e.target.value)} className="field-input">
                                    {options.dateFormats.map((f: string) => <option key={f} value={f}>{f.replace('_', ' ')}</option>)}
                                </select>
                            </div>
                        </div>
                    </div>

                    <div className="flex justify-end pt-2">
                        <button type="submit" disabled={cProc || pProc} className="btn-primary">
                            {cProc || pProc ? 'Menyimpan...' : 'Simpan perubahan'}
                        </button>
                    </div>
                </form>
            </SectionCard>

            {/* Experiences */}
            <SectionCard
                title="Pengalaman Kerja"
                description="Pengalaman, magang, proyek freelance, dll."
                addLabel="Tambah pengalaman"
                onAdd={addExp}
                items={experiences}
                renderItem={(exp) => (
                    <div className="flex items-start justify-between gap-3 p-3 rounded-lg bg-surface-muted">
                        <div className="min-w-0 flex-1">
                            <p className="font-medium text-foreground truncate">{exp.organization}</p>
                            <p className="text-small text-foreground-muted">{exp.job_title} · {formatDate(exp.start_date)} – {exp.is_current ? 'Sekarang' : formatDate(exp.end_date)}</p>
                            {exp.description && <p className="mt-1 text-small text-foreground-muted line-clamp-2">{exp.description}</p>}
                            {exp.achievements?.length && (
                                <ul className="mt-1 ml-4 list-disc text-small text-foreground-muted max-h-24 overflow-auto">
                                    {exp.achievements.map((a: string, i: number) => <li key={i}>{a}</li>)}
                                </ul>
                            )}
                        </div>
                        <div className="flex items-center gap-1 shrink-0">
                            <button onClick={() => editExp(exp)} className="btn-ghost text-caption" title="Edit"><svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg></button>
                            <button onClick={() => move(experiences, experiences.findIndex((i) => i.id === exp.id), -1)} disabled={experiences[0].id === exp.id} className="btn-ghost text-caption" title="Naik"><svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg></button>
                            <button onClick={() => move(experiences, experiences.findIndex((i) => i.id === exp.id), 1)} disabled={experiences[experiences.length-1].id === exp.id} className="btn-ghost text-caption" title="Turun"><svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 14l-7 7m0 0l-7-7m7 7V5" /></svg></button>
                            <button onClick={() => deleteItem('experience', exp.id)} className="btn-ghost text-caption text-danger" title="Hapus"><svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></button>
                        </div>
                    </div>
                )}
                empty="Belum ada pengalaman. Tambahkan pengalaman kerja pertama Anda."
            >
                {experiences.map((exp) => (
                    <div key={exp.id} className="flex items-start justify-between gap-3 p-3 rounded-lg bg-surface-muted">
                        <div className="min-w-0 flex-1">
                            <p className="font-medium text-foreground truncate">{exp.organization}</p>
                            <p className="text-small text-foreground-muted">{exp.job_title} · {formatDate(exp.start_date)} – {exp.is_current ? 'Sekarang' : formatDate(exp.end_date)}</p>
                            {exp.description && <p className="mt-1 text-small text-foreground-muted line-clamp-2">{exp.description}</p>}
                            {exp.achievements?.length && (
                                <ul className="mt-1 ml-4 list-disc text-small text-foreground-muted max-h-24 overflow-auto">
                                    {exp.achievements.map((a: string, i: number) => <li key={i}>{a}</li>)}
                                </ul>
                            )}
                        </div>
                        <div className="flex items-center gap-1 shrink-0">
                            <button onClick={() => editExp(exp)} className="btn-ghost text-caption" title="Edit"><svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg></button>
                            <button onClick={() => move(experiences, experiences.findIndex((i) => i.id === exp.id), -1)} disabled={experiences[0].id === exp.id} className="btn-ghost text-caption" title="Naik"><svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg></button>
                            <button onClick={() => move(experiences, experiences.findIndex((i) => i.id === exp.id), 1)} disabled={experiences[experiences.length-1].id === exp.id} className="btn-ghost text-caption" title="Turun"><svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 14l-7 7m0 0l-7-7m7 7V5" /></svg></button>
                            <button onClick={() => deleteItem('experience', exp.id)} className="btn-ghost text-caption text-danger" title="Hapus"><svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></button>
                        </div>
                    </div>
                ))}
            </SectionCard>

            {/* Education */}
            <SectionCard
                title="Pendidikan"
                addLabel="Tambah pendidikan"
                onAdd={addEdu}
                items={educations}
                renderItem={(edu) => (
                    <div className="flex items-center justify-between gap-3 p-3 rounded-lg bg-surface-muted">
                        <div>
                            <p className="font-medium text-foreground truncate">{edu.institution}</p>
                            <p className="text-small text-foreground-muted">
                                {edu.degree || ''} {edu.field_of_study ? '· ' + edu.field_of_study : ''}
                                {' · ' + formatDate(edu.start_date) + ' – ' + formatDate(edu.end_date)}
                            </p>
                        </div>
                        <div className="flex items-center gap-1 shrink-0">
                            <button onClick={() => editEdu(edu)} className="btn-ghost text-caption" title="Edit"><svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg></button>
                            <button onClick={() => move(educations, educations.findIndex((i) => i.id === edu.id), -1)} disabled={educations[0]?.id === edu.id} className="btn-ghost text-caption" title="Naik"><svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg></button>
                            <button onClick={() => move(educations, educations.findIndex((i) => i.id === edu.id), 1)} disabled={educations[educations.length-1]?.id === edu.id} className="btn-ghost text-caption" title="Turun"><svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 14l-7 7m0 0l-7-7m7 7V5" /></svg></button>
                            <button onClick={() => deleteItem('education', edu.id)} className="btn-ghost text-caption text-danger" title="Hapus"><svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></button>
                        </div>
                    </div>
                )}
                empty="Belum ada riwayat pendidikan."
            />

            {/* Skills */}
            <SectionCard
                title="Skill"
                addLabel="Tambah skill"
                onAdd={addSki}
                items={skills}
                renderItem={(ski) => (
                    <div className="flex items-center justify-between gap-3 p-3 rounded-lg bg-surface-muted">
                        <div>
                            <p className="font-medium text-foreground truncate">{ski.name}</p>
                            <p className="text-small text-foreground-muted">
                                {ski.category && ski.category + ' · '}{ski.proficiency || ''}
                            </p>
                        </div>
                        <div className="flex items-center gap-1 shrink-0">
                            <button onClick={() => editSki(ski)} className="btn-ghost text-caption" title="Edit"><svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg></button>
                            <button onClick={() => move(skills, skills.findIndex((i) => i.id === ski.id), -1)} disabled={skills[0]?.id === ski.id} className="btn-ghost text-caption" title="Naik"><svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg></button>
                            <button onClick={() => move(skills, skills.findIndex((i) => i.id === ski.id), 1)} disabled={skills[skills.length-1]?.id === ski.id} className="btn-ghost text-caption" title="Turun"><svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 14l-7 7m0 0l-7-7m7 7V5" /></svg></button>
                            <button onClick={() => deleteItem('skill', ski.id)} className="btn-ghost text-caption text-danger" title="Hapus"><svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></button>
                        </div>
                    </div>
                )}
                empty="Belum ada skill."
            />

            {/* Projects */}
            <SectionCard
                title="Proyek"
                addLabel="Tambah proyek"
                onAdd={addPro}
                items={projects}
                renderItem={(pro) => (
                    <div className="flex items-start justify-between gap-3 p-3 rounded-lg bg-surface-muted">
                        <div className="min-w-0 flex-1">
                            <p className="font-medium text-foreground truncate">{pro.name}</p>
                            {pro.role && <p className="text-small text-foreground-muted">{pro.role}</p>}
                            <p className="text-small text-foreground-muted">{formatDate(pro.start_date)} – {formatDate(pro.end_date)}</p>
                            {pro.technologies?.length && (
                                <div className="mt-1 flex flex-wrap gap-1">
                                    {pro.technologies.map((t: string, i: number) => <span key={i} className="badge-neutral text-[10px]">{t}</span>)}
                                </div>
                            )}
                        </div>
                        <div className="flex items-center gap-1 shrink-0">
                            <button onClick={() => editPro(pro)} className="btn-ghost text-caption" title="Edit"><svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg></button>
                            <button onClick={() => move(projects, projects.findIndex((i) => i.id === pro.id), -1)} disabled={projects[0]?.id === pro.id} className="btn-ghost text-caption" title="Naik"><svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg></button>
                            <button onClick={() => move(projects, projects.findIndex((i) => i.id === pro.id), 1)} disabled={projects[projects.length-1]?.id === pro.id} className="btn-ghost text-caption" title="Turun"><svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 14l-7 7m0 0l-7-7m7 7V5" /></svg></button>
                            <button onClick={() => deleteItem('project', pro.id)} className="btn-ghost text-caption text-danger" title="Hapus"><svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></button>
                        </div>
                    </div>
                )}
                empty="Belum ada proyek."
            />

            {/* Certifications */}
            <SectionCard
                title="Sertifikasi"
                addLabel="Tambah sertifikasi"
                onAdd={addCert}
                items={certifications}
                renderItem={(cert) => (
                    <div className="flex items-center justify-between gap-3 p-3 rounded-lg bg-surface-muted">
                        <div>
                            <p className="font-medium text-foreground truncate">{cert.name}</p>
                            <p className="text-small text-foreground-muted">
                                {cert.issuer && cert.issuer + ' · '}{formatDate(cert.issue_date)} – {formatDate(cert.expiry_date) || 'Tidak kedaluwarsa'}
                            </p>
                        </div>
                        <div className="flex items-center gap-1 shrink-0">
                            <button onClick={() => editCert(cert)} className="btn-ghost text-caption" title="Edit"><svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg></button>
                            <button onClick={() => move(certifications, certifications.findIndex((i) => i.id === cert.id), -1)} disabled={certifications[0]?.id === cert.id} className="btn-ghost text-caption" title="Naik"><svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg></button>
                            <button onClick={() => move(certifications, certifications.findIndex((i) => i.id === cert.id), 1)} disabled={certifications[certifications.length-1]?.id === cert.id} className="btn-ghost text-caption" title="Turun"><svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 14l-7 7m0 0l-7-7m7 7V5" /></svg></button>
                            <button onClick={() => deleteItem('certification', cert.id)} className="btn-ghost text-caption text-danger" title="Hapus"><svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></button>
                        </div>
                    </div>
                )}
                empty="Belum ada sertifikasi."
            />

            {/* Experience Form Modal */}
            {openModal === 'experience' && (
                <ExperienceForm
                    data={expData}
                    setData={expSetData}
                    errors={expErrors}
                    processing={expProc}
                    editing={editingId}
                    onSubmit={handleExpSubmit}
                    onClose={closeModal}
                />
            )}
            {/* Education Form Modal */}
            {openModal === 'education' && (
                <EducationForm
                    data={eduData}
                    setData={eduSetData}
                    errors={eduErrors}
                    processing={eduProc}
                    editing={editingId}
                    onSubmit={handleEduSubmit}
                    onClose={closeModal}
                />
            )}
            {/* Skill Form Modal */}
            {openModal === 'skill' && (
                <SkillForm
                    data={skiData}
                    setData={skiSetData}
                    errors={skiErrors}
                    processing={skiProc}
                    editing={editingId}
                    onSubmit={handleSkiSubmit}
                    onClose={closeModal}
                />
            )}
            {/* Project Form Modal */}
            {openModal === 'project' && (
                <ProjectForm
                    data={proData}
                    setData={proSetData}
                    errors={proErrors}
                    processing={proProc}
                    editing={editingId}
                    onSubmit={handleProSubmit}
                    onClose={closeModal}
                />
            )}
            {/* Certification Form Modal */}
            {openModal === 'certification' && (
                <CertificationForm
                    data={certData}
                    setData={certSetData}
                    errors={certErrors}
                    processing={certProc}
                    editing={editingId}
                    onSubmit={handleCertSubmit}
                    onClose={closeModal}
                />
            )}
        </AuthenticatedLayout>
    );
}

function formatDate(d: string | null | undefined): string {
    if (!d) return '-';
    const dt = new Date(d);
    return dt.toLocaleDateString('id-ID', { year: 'numeric', month: 'short' });
}