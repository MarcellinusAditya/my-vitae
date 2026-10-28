import Dropdown from '@/Components/Dropdown';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useEffect, useState } from 'react';

interface NavItem {
    label: string;
    href?: string;
    routeName?: string;
    icon: ReactNode;
    disabled?: boolean;
    soon?: boolean;
}

const icon = (path: string) => (
    <svg
        className="h-5 w-5 shrink-0"
        fill="none"
        stroke="currentColor"
        strokeWidth={1.75}
        viewBox="0 0 24 24"
        aria-hidden="true"
    >
        <path strokeLinecap="round" strokeLinejoin="round" d={path} />
    </svg>
);

const navigation: NavItem[] = [
    {
        label: 'Dashboard',
        routeName: 'dashboard',
        icon: icon('M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10'),
    },
    {
        label: 'Career Profile',
        routeName: 'career-profile.show',
        icon: icon('M16 7a4 4 0 11-8 0 4 4 0 018 0zM4 21v-1a6 6 0 016-6h4a6 6 0 016 6v1'),
    },
    {
        label: 'Import CV',
        routeName: 'career-profile.import.index',
        icon: icon('M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v12m0-12L7 9m5-5l5 5'),
    },
    {
        label: 'Applications',
        routeName: 'applications.index',
        icon: icon('M9 12h6m-6 4h6M7 3h10a2 2 0 012 2v14a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z'),
    },
    {
        label: 'Documents',
        routeName: 'documents.index',
        icon: icon('M7 21h10a2 2 0 002-2V7l-4-4H7a2 2 0 00-2 2v14a2 2 0 002 2z'),
    },
];

function Brand({ compact = false }: { compact?: boolean }) {
    return (
        <Link
            href={route('dashboard')}
            className="flex items-center gap-2.5 rounded-lg px-1 py-1"
        >
            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-on-primary">
                <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24" aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h10M4 18h7" />
                </svg>
            </span>
            {!compact && (
                <span className="text-h3 font-semibold tracking-tight">MyVitae</span>
            )}
        </Link>
    );
}

function SidebarContent({ onNavigate }: { onNavigate?: () => void }) {
    const { url } = usePage();

    const isActive = (item: NavItem) =>
        item.routeName ? url === route(item.routeName) || url.startsWith(route(item.routeName) + '/') : false;

    return (
        <div className="flex h-full w-sidebar-width flex-col border-r border-border bg-surface">
            <div className="flex h-header-height items-center px-4">
                <Brand />
            </div>

            <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Main">
                {navigation.map((item) => {
                    const active = isActive(item);

                    if (item.disabled) {
                        return (
                            <span
                                key={item.label}
                                className="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2 text-small text-foreground-subtle"
                                aria-disabled="true"
                            >
                                {item.icon}
                                <span className="flex-1 truncate">{item.label}</span>
                                {item.soon && (
                                    <span className="rounded bg-surface-sunken px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-foreground-subtle">
                                        Soon
                                    </span>
                                )}
                            </span>
                        );
                    }

                    return (
                        <Link
                            key={item.label}
                            href={route(item.routeName!)}
                            onClick={onNavigate}
                            aria-current={active ? 'page' : undefined}
                            className={`flex items-center gap-3 rounded-lg px-3 py-2 text-small transition-colors ${
                                active
                                    ? 'bg-primary-soft font-medium text-primary'
                                    : 'text-foreground-muted hover:bg-surface-muted hover:text-foreground'
                            }`}
                        >
                            {item.icon}
                            <span className="truncate">{item.label}</span>
                        </Link>
                    );
                })}
            </nav>

            <div className="border-t border-border p-3">
                <Link
                    href={route('profile.edit')}
                    onClick={onNavigate}
                    className="flex items-center gap-3 rounded-lg px-3 py-2 text-small text-foreground-muted transition-colors hover:bg-surface-muted hover:text-foreground"
                >
                    {icon('M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z')}
                    <span>Settings</span>
                </Link>
            </div>
        </div>
    );
}

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const user = usePage().props.auth.user;
    const [mobileOpen, setMobileOpen] = useState(false);

    useEffect(() => {
        if (!mobileOpen) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setMobileOpen(false);
            }
        };

        document.addEventListener('keydown', onKeyDown);
        document.body.style.overflow = 'hidden';

        return () => {
            document.removeEventListener('keydown', onKeyDown);
            document.body.style.overflow = '';
        };
    }, [mobileOpen]);

    return (
        <div className="min-h-screen bg-background">
            {/* Desktop sidebar */}
            <aside className="fixed inset-y-0 left-0 z-30 hidden md:block">
                <SidebarContent />
            </aside>

            {/* Mobile drawer */}
            {mobileOpen && (
                <div className="fixed inset-0 z-50 md:hidden">
                    <div
                        className="absolute inset-0 bg-foreground/30"
                        onClick={() => setMobileOpen(false)}
                        aria-hidden="true"
                    />
                    <div className="relative h-full w-sidebar-width max-w-[85vw] shadow-lift">
                        <SidebarContent onNavigate={() => setMobileOpen(false)} />
                    </div>
                </div>
            )}

            <div className="md:pl-sidebar-width">
                <header className="sticky top-0 z-20 flex h-header-height items-center gap-3 border-b border-border bg-background/90 px-4 backdrop-blur sm:px-6">
                    <button
                        type="button"
                        onClick={() => setMobileOpen(true)}
                        className="-ml-2 rounded-lg p-2 text-foreground-muted hover:bg-surface-muted hover:text-foreground md:hidden"
                        aria-label="Open navigation"
                        aria-expanded={mobileOpen}
                    >
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth={1.75} viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <div className="md:hidden">
                        <Brand />
                    </div>

                    <div className="ml-auto flex items-center gap-2">
                        <Dropdown>
                            <Dropdown.Trigger>
                                <button
                                    type="button"
                                    className="flex items-center gap-2 rounded-lg px-2 py-1.5 text-small text-foreground-muted transition-colors hover:bg-surface-muted hover:text-foreground"
                                >
                                    <span className="flex h-7 w-7 items-center justify-center rounded-full bg-primary-soft text-caption font-semibold text-primary">
                                        {user.name.charAt(0).toUpperCase()}
                                    </span>
                                    <span className="hidden max-w-[10rem] truncate sm:inline">{user.name}</span>
                                    <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth={1.75} viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 9l6 6 6-6" />
                                    </svg>
                                </button>
                            </Dropdown.Trigger>

                            <Dropdown.Content align="right">
                                <div className="border-b border-border px-4 py-2.5">
                                    <p className="truncate text-small font-medium text-foreground">{user.name}</p>
                                    <p className="truncate text-caption text-foreground-muted">{user.email}</p>
                                </div>
                                <Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link>
                                <Dropdown.Link href={route('logout')} method="post" as="button">
                                    Log out
                                </Dropdown.Link>
                            </Dropdown.Content>
                        </Dropdown>
                    </div>
                </header>

                <main className="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    {header && <div className="mb-6">{header}</div>}
                    {children}
                </main>
            </div>
        </div>
    );
}
