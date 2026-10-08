import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import daisyui from 'daisyui';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['DM Sans', 'Plus Jakarta Sans', ...defaultTheme.fontFamily.sans],
                mono: ['JetBrains Mono', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                slate: {
                    950: '#0c1222',
                    900: '#111a2e',
                    800: '#1a2540',
                    700: '#253352',
                    600: '#3d4f6f',
                    500: '#64748b',
                    400: '#94a3b8',
                    300: '#cbd5e1',
                    200: '#e2e8f0',
                    100: '#f1f5f9',
                    50: '#f8fafc',
                },
                sage: {
                    DEFAULT: '#3a9d6e',
                    dim: '#2d7d57',
                    bright: '#4ade80',
                    wash: 'rgba(58,157,110,0.08)',
                    wash2: 'rgba(58,157,110,0.15)',
                },
                coral: {
                    DEFAULT: '#ef6b4a',
                    wash: 'rgba(239,107,74,0.1)',
                },
                amber: {
                    DEFAULT: '#e5a520',
                    wash: 'rgba(229,165,32,0.1)',
                },
                sky: {
                    DEFAULT: '#3b82f6',
                    wash: 'rgba(59,130,246,0.08)',
                },
            },
        },
    },

    plugins: [forms, daisyui],

    daisyui: {
        themes: [
            {
                light: {
                    "primary": "#3a9d6e",          // Sage (PS2 UAD)
                    "primary-focus": "#2d7d57",
                    "primary-content": "#ffffff",
                    "secondary": "#3b82f6",        // Sky
                    "accent": "#e5a520",           // Amber
                    "neutral": "#111a2e",          // Slate 900
                    "neutral-content": "#ffffff",
                    "base-100": "#ffffff",         // Surface card
                    "base-200": "#f5f7fa",         // Page background clean
                    "base-300": "#e2e8f0",         // Slate 200 border
                    "base-content": "#111a2e",     // Text primary
                    "info": "#3b82f6",
                    "success": "#3a9d6e",
                    "warning": "#e5a520",
                    "error": "#ef6b4a",
                },
            },
        ],
        darkTheme: "light", // Paksa light theme default agar konsisten dan clean
        base: true,
        styled: true,
        utils: true,
    },
};
