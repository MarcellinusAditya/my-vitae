import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { FormEventHandler } from 'react';

interface CertificationFormProps {
    data: any;
    setData: (key: string, value: any) => void;
    errors: any;
    processing: boolean;
    editing: number | null;
    onSubmit: FormEventHandler<HTMLFormElement>;
    onClose: () => void;
}

export default function CertificationForm({
    data, setData, errors, processing, editing, onSubmit, onClose,
}: CertificationFormProps) {
    return (
        <Modal show onClose={onClose}>
            <form onSubmit={onSubmit} className="p-6 space-y-6">
                <h2 className="text-h2">{editing ? 'Edit Sertifikasi' : 'Tambah Sertifikasi'}</h2>

                <div>
                    <InputLabel htmlFor="name" value="Nama sertifikasi *" />
                    <TextInput id="name" name="name" value={data.name} onChange={e => setData('name', e.target.value)} className="mt-1 block w-full" required />
                    <InputError message={errors.name} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="issuer" value="Penerbit" />
                    <TextInput id="issuer" name="issuer" value={data.issuer} onChange={e => setData('issuer', e.target.value)} className="mt-1 block w-full" placeholder="AWS, Google, Microsoft, dll" />
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <div>
                        <InputLabel htmlFor="issue_date" value="Tanggal terbit" />
                        <TextInput id="issue_date" type="date" name="issue_date" value={data.issue_date} onChange={e => setData('issue_date', e.target.value)} className="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel htmlFor="expiry_date" value="Tanggal kedaluwarsa" />
                        <TextInput id="expiry_date" type="date" name="expiry_date" value={data.expiry_date} onChange={e => setData('expiry_date', e.target.value)} className="mt-1 block w-full" />
                    </div>
                    <div className="hidden sm:block" />
                </div>

                <div>
                    <InputLabel htmlFor="credential_url" value="URL kredensial" />
                    <TextInput id="credential_url" type="url" name="credential_url" value={data.credential_url} onChange={e => setData('credential_url', e.target.value)} className="mt-1 block w-full" placeholder="https://..." />
                    <InputError message={errors.credential_url} className="mt-2" />
                </div>

                <div className="flex justify-end gap-3 border-t border-border pt-4">
                    <SecondaryButton type="button" onClick={onClose}>Batal</SecondaryButton>
                    <PrimaryButton disabled={processing}>{processing ? 'Menyimpan...' : (editing ? 'Perbarui' : 'Tambah')}</PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
