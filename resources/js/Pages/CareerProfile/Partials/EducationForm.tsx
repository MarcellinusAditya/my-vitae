import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { FormEventHandler } from 'react';

interface EducationFormProps {
    data: any;
    setData: (key: string, value: any) => void;
    errors: any;
    processing: boolean;
    editing: number | null;
    onSubmit: FormEventHandler<HTMLFormElement>;
    onClose: () => void;
}

export default function EducationForm({
    data, setData, errors, processing, editing, onSubmit, onClose,
}: EducationFormProps) {
    return (
        <Modal show onClose={onClose}>
            <form onSubmit={onSubmit} className="p-6 space-y-6">
                <h2 className="text-h2">{editing ? 'Edit Pendidikan' : 'Tambah Pendidikan'}</h2>

                <div>
                    <InputLabel htmlFor="institution" value="Institusi *" />
                    <TextInput id="institution" name="institution" value={data.institution} onChange={e => setData('institution', e.target.value)} className="mt-1 block w-full" required />
                    <InputError message={errors.institution} className="mt-2" />
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="degree" value="Gelar" />
                        <TextInput id="degree" name="degree" value={data.degree} onChange={e => setData('degree', e.target.value)} className="mt-1 block w-full" placeholder="S1, S2, D3, SMA, dll" />
                    </div>
                    <div>
                        <InputLabel htmlFor="field_of_study" value="Jurusan / Bidang studi" />
                        <TextInput id="field_of_study" name="field_of_study" value={data.field_of_study} onChange={e => setData('field_of_study', e.target.value)} className="mt-1 block w-full" placeholder="Teknik Informatika, Manajemen, dll" />
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
                    <InputLabel htmlFor="description" value="Deskripsi (opsional)" />
                    <textarea id="description" name="description" value={data.description} onChange={e => setData('description', e.target.value)} rows={3} className="field-input mt-1 block w-full" maxLength={2000} />
                    <InputError message={errors.description} className="mt-2" />
                </div>

                <div className="flex justify-end gap-3 border-t border-border pt-4">
                    <SecondaryButton type="button" onClick={onClose}>Batal</SecondaryButton>
                    <PrimaryButton disabled={processing}>{processing ? 'Menyimpan...' : (editing ? 'Perbarui' : 'Tambah')}</PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
