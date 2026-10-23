import { InputHTMLAttributes } from 'react';

export default function Checkbox({
    className = '',
    ...props
}: InputHTMLAttributes<HTMLInputElement>) {
    return (
        <input
            {...props}
            type="checkbox"
            className={
                'rounded border-border-strong text-primary shadow-sm focus:ring-primary/40 focus:ring-offset-0 ' +
                className
            }
        />
    );
}
