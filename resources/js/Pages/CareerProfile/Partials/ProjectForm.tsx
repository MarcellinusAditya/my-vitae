import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { FormEventHandler, useState } from 'react';
import { useForm } from '@inertiajs/react';

interface ProjectFormProps {
    data: any;
    setData: (key: string, value: any) => void;
    errors: any;
    processing: boolean;
    editing: number | null;
    onSubmit: FormEventHandler<HTMLFormElement>;
    onClose: () => void;
}

export default function ProjectForm({
    data, setData, errors, processing, editing, onSubmit, onClose,
}: ProjectFormProps) {
    const [technologies, setTechnologies] = useState<string[]>(data.technologies?.length ? data.technologies : ['']);

    const addTech = () => setTechnologies([...technologies, '']);
    const removeTech = (idx: number) => setTechnologies(technologies.filter((_, i) => i !== idx));
    const updateTech = (idx: number, value: string) => setTechnologies(technologies.map((t, i) => i === idx ? value : t));

    return (
        <Modal show onClose={onClose} maxWidth="lg">
            <form onSubmit={onSubmit} className="p-6 space-y-6">
                <h2 className="text-h2">{editing ? 'Edit Proyek' : 'Tambah Proyek'}</h2>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="name" value="Nama proyek *" />
                        <TextInput id="name" name="name" value={data.name} onChange={e => setData('name', e.target.value)} className="mt-1 block w-full" required />
                        <InputError message={errors.name} className="mt-2" />
                    </div>
                    <div>
                        <InputLabel htmlFor="role" value="Peran Anda" />
                        <TextInput id="role" name="role" value={data.role} onChange={e => setData('role', e.target.value)} className="mt-1 block w-full" placeholder="Full Stack Developer, Tech Lead, dll" />
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
                    <div className="hidden sm:block" />
                </div>

                <div>
                    <InputLabel htmlFor="url" value="URL proyek (GitHub, demo, dll)" />
                    <TextInput id="url" type="url" name="url" value={data.url} onChange={e => setData('url', e.target.value)} className="mt-1 block w-full" placeholder="https://..." />
                    <InputError message={errors.url} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="description" value="Deskripsi" />
                    <textarea id="description" name="description" value={data.description} onChange={e => setData('description', e.target.value)} rows={3} className="field-input mt-1 block w-full" maxLength={4000} />
                    <InputError message={errors.description} className="mt-2" />
                </div>

                <div>
                    <div className="flex items-center justify-between">
                        <InputLabel value="Teknologi" />
                        <button type="button" onClick={addTech} className="btn-ghost text-caption">+ Tambah</button>
                    </div>
                    <div className="mt-2 flex flex-wrap gap-2">
                        {technologies.map((tech, idx) => (
                            <div key={idx} className="flex gap-2">
                                <TextInput value={tech} onChange={e => updateTech(idx, e.target.value)} className="w-48" placeholder={`Teknologi ${idx + 1}`} maxLength={80} />
                                {technologies.length > 1 && (
                                    <button type="button" onClick={() => removeTech(idx)} className="btn-ghost text-caption text-danger shrink-0" title="Hapus"><svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></button>
                                )}
                            </div>
                        ))}
                    </div>
                    <InputError message={errors.technologies} className="mt-2" />
                </div>

                <div className="flex justify-end gap-3 border-t border-border pt-4">
                    <SecondaryButton type="button" onClick={onClose}>Batal</SecondaryButton>
                    <PrimaryButton disabled={processing}>{processing ? 'Menyimpan...' : (editing ? 'Perbarui' : 'Tambah')}</PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
