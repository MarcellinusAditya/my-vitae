import { ButtonHTMLAttributes } from 'react';

export default function DangerButton({
    className = '',
    disabled,
    children,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            {...props}
            className={'btn bg-danger text-white hover:opacity-90 ' + className}
            disabled={disabled}
        >
            {children}
        </button>
    );
}
