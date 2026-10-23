import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-screen flex-col bg-background">
            <header className="px-4 py-5 sm:px-6">
                <Link href="/" className="inline-flex items-center gap-2.5 rounded-lg">
                    <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-on-primary">
                        <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24" aria-hidden="true">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h10M4 18h7" />
                        </svg>
                    </span>
                    <span className="text-h3 font-semibold tracking-tight text-foreground">MyVitae</span>
                </Link>
            </header>

            <main className="flex flex-1 items-center justify-center px-4 pb-16 pt-4 sm:px-6">
                <div className="w-full max-w-md">
                    <div className="card p-6 sm:p-8">{children}</div>
                </div>
            </main>
        </div>
    );
}
