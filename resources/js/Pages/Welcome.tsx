import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';

const values = [
    {
        title: 'Grounded in your profile',
        description:
            'Every line traces back to experience you entered. Nothing invented, nothing exaggerated — gaps are flagged, not filled.',
        icon: 'M9 12l2 2 4-4m5.6 2A9.6 9.6 0 0112 21.6 9.6 9.6 0 012.4 12 9.6 9.6 0 0112 2.4 9.6 9.6 0 0121.6 12z',
    },
    {
        title: 'Tailored per application',
        description:
            'Paste a job description and get a CV and cover letter written for that role, built from the same verified career profile.',
        icon: 'M9 12h6m-6 4h6M7 3h10a2 2 0 012 2v14a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z',
    },
    {
        title: 'Editable, exportable',
        description:
            'Review and adjust every section, then export to PDF or DOCX. It reads like you because it is you.',
        icon: 'M12 10v6m0 0l-3-3m3 3l3-3M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2',
    },
];

function Brand() {
    return (
        <Link href="/" className="inline-flex items-center gap-2.5 rounded-lg">
            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-on-primary">
                <svg
                    className="h-4 w-4"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={2}
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M4 6h16M4 12h10M4 18h7"
                    />
                </svg>
            </span>
            <span className="text-h3 font-semibold tracking-tight text-foreground">
                MyVitae
            </span>
        </Link>
    );
}

export default function Welcome({
    auth,
    canLogin,
    canRegister,
}: PageProps<{ canLogin: boolean; canRegister: boolean }>) {
    return (
        <>
            <Head title="A CV that tells the truth" />

            <div className="min-h-screen bg-background">
                <header className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-5 sm:px-6 lg:px-8">
                    <Brand />

                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <Link
                                href={route('dashboard')}
                                className="btn-primary"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <>
                                {canLogin && (
                                    <Link
                                        href={route('login')}
                                        className="btn-ghost"
                                    >
                                        Log in
                                    </Link>
                                )}
                                {canRegister && (
                                    <Link
                                        href={route('register')}
                                        className="btn-primary"
                                    >
                                        Get started
                                    </Link>
                                )}
                            </>
                        )}
                    </nav>
                </header>

                <main className="mx-auto max-w-6xl px-4 pb-16 sm:px-6 lg:px-8">
                    <section className="grid gap-10 py-10 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)] lg:items-center lg:py-20">
                        <div>
                            <span className="badge-neutral">Early access</span>

                            <h1 className="mt-4 text-display-sm text-foreground sm:text-display">
                                A CV tailored to every job, without inventing a
                                single fact.
                            </h1>

                            <p className="mt-5 max-w-xl text-body text-foreground-muted">
                                MyVitae turns your real experience into a
                                role-specific CV and cover letter. You review
                                every claim before it ships — no filler, no
                                fabricated achievements.
                            </p>

                            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                                {auth.user ? (
                                    <Link
                                        href={route('dashboard')}
                                        className="btn-primary"
                                    >
                                        Go to dashboard
                                    </Link>
                                ) : (
                                    canRegister && (
                                        <Link
                                            href={route('register')}
                                            className="btn-primary"
                                        >
                                            Create free account
                                        </Link>
                                    )
                                )}
                                {!auth.user && canLogin && (
                                    <Link
                                        href={route('login')}
                                        className="btn-secondary"
                                    >
                                        I already have an account
                                    </Link>
                                )}
                            </div>
                        </div>

                        <div className="card p-5 sm:p-6">
                            <div className="flex items-center justify-between gap-3">
                                <p className="text-h3 text-foreground">
                                    Cover letter
                                </p>
                                <span className="badge-success">Grounded</span>
                            </div>

                            <div className="mt-4 space-y-3" aria-hidden="true">
                                <div className="h-3 w-3/4 rounded bg-surface-sunken" />
                                <div className="h-3 w-full rounded bg-surface-sunken" />
                                <div className="h-3 w-5/6 rounded bg-surface-sunken" />
                                <div className="h-3 w-full rounded bg-surface-sunken" />
                                <div className="h-3 w-2/3 rounded bg-surface-sunken" />
                            </div>

                            <ul className="mt-6 space-y-2.5 border-t border-border pt-4">
                                {[
                                    'Experience verified against your profile',
                                    'No invented skills or metrics',
                                    'Editable before export',
                                ].map((item) => (
                                    <li
                                        key={item}
                                        className="flex items-start gap-2 text-small text-foreground-muted"
                                    >
                                        <svg
                                            className="mt-0.5 h-4 w-4 shrink-0 text-success"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth={2}
                                            viewBox="0 0 24 24"
                                            aria-hidden="true"
                                        >
                                            <path
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                                d="M5 13l4 4L19 7"
                                            />
                                        </svg>
                                        {item}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </section>

                    <section
                        className="grid gap-4 border-t border-border pt-10 sm:grid-cols-2 lg:grid-cols-3"
                        aria-label="Why MyVitae"
                    >
                        {values.map((value) => (
                            <div key={value.title} className="card p-5">
                                <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary-soft text-primary">
                                    <svg
                                        className="h-5 w-5"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth={1.75}
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            d={value.icon}
                                        />
                                    </svg>
                                </span>

                                <h2 className="mt-4 text-h3 text-foreground">
                                    {value.title}
                                </h2>
                                <p className="mt-1.5 text-small text-foreground-muted">
                                    {value.description}
                                </p>
                            </div>
                        ))}
                    </section>
                </main>

                <footer className="border-t border-border">
                    <div className="mx-auto flex max-w-6xl flex-col gap-2 px-4 py-6 text-caption text-foreground-subtle sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                        <p>MyVitae. Your experience, told accurately.</p>
                        {canLogin && !auth.user && (
                            <Link
                                href={route('login')}
                                className="hover:text-foreground"
                            >
                                Log in
                            </Link>
                        )}
                    </div>
                </footer>
            </div>
        </>
    );
}
