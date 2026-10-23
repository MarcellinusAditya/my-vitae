import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { FormEventHandler } from 'react';

interface SkillFormProps {
    data: any;
    setData: (key: string, value: any) => void;
    errors: any;
    processing: boolean;
    editing: number | null;
    onSubmit: FormEventHandler<HTMLFormElement>;
    onClose: () => void;
}

export default function SkillForm({
    data, setData, errors, processing, editing, onSubmit, onClose,
}: SkillFormProps) {
    return (
        <Modal show onClose={onClose}>
            <form onSubmit={onSubmit} className="p-6 space-y-6">
                <h2 className="text-h2">{editing ? 'Edit Skill' : 'Tambah Skill'}</h2>

                <div className="grid gap-4 sm:grid-cols-3">
                    <div>
                        <InputLabel htmlFor="name" value="Nama skill *" />
                        <TextInput id="name" name="name" value={data.name} onChange={e => setData('name', e.target.value)} className="mt-1 block w-full" required placeholder="React, Laravel, Project Management" />
                        <InputError message={errors.name} className="mt-2" />
                    </div>
                    <div>
                        <InputLabel htmlFor="category" value="Kategori" />
                        <TextInput id="category" name="category" value={data.category} onChange={e => setData('category', e.target.value)} className="mt-1 block w-full" placeholder="Frontend, Backend, DevOps, Soft Skill" />
                    </div>
                    <div>
                        <InputLabel htmlFor="proficiency" value="Tingkat kemahiran" />
                        <TextInput id="proficiency" name="proficiency" value={data.proficiency} onChange={e => setData('proficiency', e.target.value)} className="mt-1 block w-full" placeholder="Pemula, Menengah, Mahir, Ahli" />
                    </div>
                </div>

                <div className="flex justify-end gap-3 border-t border-border pt-4">
                    <SecondaryButton type="button" onClick={onClose}>Batal</SecondaryButton>
                    <PrimaryButton disabled={processing}>{processing ? 'Menyimpan...' : (editing ? 'Perbarui' : 'Tambah')}</PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
