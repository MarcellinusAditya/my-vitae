import { ReactNode } from 'react';

interface SectionCardProps {
    title: string;
    description?: string;
    addLabel?: string;
    onAdd?: () => void;
    items?: any[];
    renderItem?: (item: any) => ReactNode;
    empty?: string;
    children?: ReactNode;
}

export default function SectionCard({
    title, description, addLabel, onAdd, items = [], renderItem, empty = '', children,
}: SectionCardProps) {
    const hasItems = items.length > 0;
    const hasChildren = children !== undefined && children !== null;

    return (
        <section className="card p-5 sm:p-6">
            <div className="flex flex-wrap items-start justify-between gap-3 mb-4">
                <div>
                    <h2 className="text-h3">{title}</h2>
                    {description && <p className="mt-0.5 text-small text-foreground-muted">{description}</p>}
                </div>
                {addLabel && onAdd && (
                    <button type="button" onClick={onAdd} className="btn-primary shrink-0">
                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                        {addLabel}
                    </button>
                )}
            </div>

            {hasItems ? (
                <div className="space-y-3">
                    {items.map((item) => renderItem!(item))}
                </div>
            ) : (
                <div className="text-center py-8 text-foreground-muted">
                    <svg className="mx-auto h-10 w-10 text-foreground-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    <p className="mt-3 text-small">{empty}</p>
                    {addLabel && onAdd && (
                        <button type="button" onClick={onAdd} className="mt-3 btn-primary">
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" /></svg>
                            {addLabel}
                        </button>
                    )}
                </div>
            )}

            {hasChildren && children}
        </section>
    );
}