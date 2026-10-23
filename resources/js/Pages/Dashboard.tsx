import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';

interface DashboardProps {
    counts: {
        applications: number;
        documents: number;
    };
}

const steps = [
    {
        title: 'Build your career profile',
        description: 'Add your experience, education, and skills once. Every document reuses it.',
        action: 'Start profile',
    },
    {
        title: 'Add a job you are applying to',
        description: 'Paste the job description and qualifications. We map them against your profile.',
        action: 'Add application',
    },
    {
        title: 'Generate and export',
        description: 'Tailor a CV and cover letter, edit them, then export to PDF or DOCX.',
        action: 'Generate document',
    },
];

export default function Dashboard({ counts }: DashboardProps) {
    const user = usePage().props.auth.user;
    const firstName = user.name.split(' ')[0];

    const stats = [
        { label: 'Career profile', value: 'Not created', hint: 'Your source of truth for every document' },
        { label: 'Applications', value: String(counts.applications), hint: 'Jobs you are tailoring documents for' },
        { label: 'Documents', value: String(counts.documents), hint: 'Generated CVs and cover letters' },
    ];

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-h1">Welcome, {firstName}</h1>
                    <p className="mt-1 text-body text-foreground-muted">
                        Set up your career profile once, then tailor a CV and cover letter for each job.
                    </p>
                </div>
            }
        >
            <Head title="Dashboard" />

            <div className="space-y-6">
                <section className="grid grid-cols-1 gap-4 sm:grid-cols-3" aria-label="Overview">
                    {stats.map((stat) => (
                        <div key={stat.label} className="card p-4 sm:p-5">
                            <p className="text-caption uppercase tracking-wide text-foreground-subtle">{stat.label}</p>
                            <p className="mt-2 text-h2 text-foreground">{stat.value}</p>
                            <p className="mt-1 text-small text-foreground-muted">{stat.hint}</p>
                        </div>
                    ))}
                </section>

                <section className="card p-5 sm:p-6">
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 className="text-h2">Get started</h2>
                        <span className="badge-warning">First release in progress</span>
                    </div>

                    <ol className="mt-5 space-y-4">
                        {steps.map((step, index) => (
                            <li key={step.title} className="flex gap-4">
                                <span className="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-surface-sunken text-caption font-semibold text-foreground-muted">
                                    {index + 1}
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="text-h3">{step.title}</p>
                                    <p className="mt-0.5 text-small text-foreground-muted">{step.description}</p>
                                </div>
                                <button
                                    type="button"
                                    disabled
                                    className="btn-secondary hidden shrink-0 self-start sm:inline-flex"
                                    title="Coming in a later phase"
                                >
                                    {step.action}
                                </button>
                            </li>
                        ))}
                    </ol>
                </section>

                <section className="card p-5 sm:p-6">
                    <h2 className="text-h2">Account</h2>
                    <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="min-w-0">
                            <p className="truncate text-body text-foreground">{user.name}</p>
                            <p className="truncate text-small text-foreground-muted">{user.email}</p>
                        </div>
                        <Link href={route('profile.edit')} className="btn-secondary shrink-0">
                            Manage account
                        </Link>
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
