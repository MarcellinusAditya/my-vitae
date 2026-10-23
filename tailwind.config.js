import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                background: '#FBFBF9',
                surface: '#FFFFFF',
                'surface-muted': '#F4F4F1',
                'surface-sunken': '#EFEFEA',
                border: '#E5E5DF',
                'border-strong': '#D3D3CB',
                foreground: '#1A1A17',
                'foreground-muted': '#6B6B63',
                'foreground-subtle': '#96968C',
                primary: '#1F6F5C',
                'primary-hover': '#175A4A',
                'primary-soft': '#E7F2EE',
                'on-primary': '#FFFFFF',
                success: '#2F7D57',
                'success-soft': '#E6F2EB',
                warning: '#A66A17',
                'warning-soft': '#FBF0DD',
                danger: '#B42318',
                'danger-soft': '#FCEDEB',
            },
            fontSize: {
                display: ['3rem', { lineHeight: '1.05', letterSpacing: '-0.025em', fontWeight: '600' }],
                'display-sm': ['2.25rem', { lineHeight: '1.1', letterSpacing: '-0.02em', fontWeight: '600' }],
                h1: ['1.75rem', { lineHeight: '1.2', letterSpacing: '-0.015em', fontWeight: '600' }],
                h2: ['1.25rem', { lineHeight: '1.3', letterSpacing: '-0.01em', fontWeight: '600' }],
                h3: ['1rem', { lineHeight: '1.4', fontWeight: '600' }],
                body: ['0.9375rem', { lineHeight: '1.6' }],
                small: ['0.8125rem', { lineHeight: '1.5' }],
                caption: ['0.75rem', { lineHeight: '1.4', letterSpacing: '0.01em' }],
            },
            borderRadius: {
                DEFAULT: '0.375rem',
                lg: '0.5rem',
                xl: '0.75rem',
                '2xl': '1rem',
            },
            boxShadow: {
                card: '0 1px 2px rgba(26, 26, 23, 0.04)',
                lift: '0 4px 16px rgba(26, 26, 23, 0.06)',
            },
            spacing: {
                'sidebar-width': '15rem',
                'header-height': '3.5rem',
            },
        },
    },

    plugins: [forms],
};
