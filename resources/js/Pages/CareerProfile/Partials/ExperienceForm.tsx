import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { FormEventHandler, useState } from 'react';
import { useForm } from '@inertiajs/react';

interface ExperienceFormProps {
    data: any;
    setData: (key: string, value: any) => void;
    errors: any;
    processing: boolean;
    editing: number | null;
    onSubmit: FormEventHandler<HTMLFormElement>;
    onClose: () => void;
}

export default function ExperienceForm({
    data, setData, errors, processing, editing, onSubmit, onClose,
}: ExperienceFormProps) {
    const [achievements, setAchievements] = useState<string[]>(data.achievements?.length ? data.achievements : ['']);

    const addAchievement = () => setAchievements([...achievements, '']);
    const removeAchievement = (idx: number) => setAchievements(achievements.filter((_, i) => i !== idx));
    const updateAchievement = (idx: number, value: string) => setAchievements(achievements.map((a, i) => i === idx ? value : a));

    return (
        <Modal show onClose={onClose} maxWidth="2xl">
            <form onSubmit={onSubmit} className="p-6 space-y-6">
                <h2 className="text-h2">{editing ? 'Edit Pengalaman' : 'Tambah Pengalaman'}</h2>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="organization" value="Organisasi / Perusahaan *" />
                        <TextInput id="organization" name="organization" value={data.organization} onChange={e => setData('organization', e.target.value)} className="mt-1 block w-full" required />
                        <InputError message={errors.organization} className="mt-2" />
                    </div>
                    <div>
                        <InputLabel htmlFor="job_title" value="Jabatan *" />
                        <TextInput id="job_title" name="job_title" value={data.job_title} onChange={e => setData('job_title', e.target.value)} className="mt-1 block w-full" required />
                        <InputError message={errors.job_title} className="mt-2" />
                    </div>
                    <div>
                        <InputLabel htmlFor="employment_type" value="Jenis pekerjaan" />
                        <TextInput id="employment_type" name="employment_type" value={data.employment_type} onChange={e => setData('employment_type', e.target.value)} className="mt-1 block w-full" placeholder="Purna waktu, Kontrak, Magang, Freelance" />
                    </div>
                    <div>
                        <InputLabel htmlFor="location" value="Lokasi" />
                        <TextInput id="location" name="location" value={data.location} onChange={e => setData('location', e.target.value)} className="mt-1 block w-full" placeholder="Kota, Negara" />
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <div>
                        <InputLabel htmlFor="start_date" value="Tanggal mulai" />
                        <TextInput id="start_date" type="date" name="start_date" value={data.start_date} onChange={e => setData('start_date', e.target.value)} className="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel htmlFor="end_date" value="Tanggal selesai" />
                        <TextInput id="end_date" type="date" name="end_date" value={data.end_date} onChange={e => setData('end_date', e.target.value)} className="mt-1 block w-full" />
                    </div>
                    <div className="flex items-end">
                        <label className="flex items-center gap-2 cursor-pointer w-full">
                            <input type="checkbox" checked={data.is_current} onChange={e => setData('is_current', e.target.checked)} className="rounded border-border-strong text-primary focus:ring-primary/40" />
                            <span className="text-small text-foreground-muted">Masih bekerja di sini</span>
                        </label>
                    </div>
                </div>

                <div>
                    <InputLabel htmlFor="description" value="Deskripsi peran" />
                    <textarea id="description" name="description" value={data.description} onChange={e => setData('description', e.target.value)} rows={4} className="field-input mt-1 block w-full" maxLength={4000} />
                    <InputError message={errors.description} className="mt-2" />
                </div>

                <div>
                    <div className="flex items-center justify-between">
                        <InputLabel value="Pencapaian (bullet)" />
                        <button type="button" onClick={addAchievement} className="btn-ghost text-caption">+ Tambah</button>
                    </div>
                    <div className="mt-2 space-y-2">
                        {achievements.map((ach, idx) => (
                            <div key={idx} className="flex gap-2">
                                <TextInput value={ach} onChange={e => updateAchievement(idx, e.target.value)} className="flex-1" placeholder={`Pencapaian ${idx + 1}`} maxLength={500} />
                                {achievements.length > 1 && (
                                    <button type="button" onClick={() => removeAchievement(idx)} className="btn-ghost text-caption text-danger shrink-0" title="Hapus"><svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></button>
                                )}
                            </div>
                        ))}
                    </div>
                    <InputError message={errors.achievements} className="mt-2" />
                </div>

                <div className="flex justify-end gap-3 border-t border-border pt-4">
                    <SecondaryButton type="button" onClick={onClose}>Batal</SecondaryButton>
                    <PrimaryButton disabled={processing}>{processing ? 'Menyimpan...' : (editing ? 'Perbarui' : 'Tambah')}</PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
