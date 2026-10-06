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
                sans: ['Plus Jakarta Sans', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms, daisyui],

    daisyui: {
        themes: [
            {
                emerald: {
                    ...require("daisyui/src/theming/themes")["emerald"],
                    primary: "#10b981",
                    "primary-focus": "#059669",
                    "primary-content": "#ffffff",
                    secondary: "#0284c7",
                    accent: "#f59e0b",
                    neutral: "#1e293b",
                    "base-100": "#ffffff",
                    "base-200": "#f8fafc",
                    "base-300": "#f1f5f9",
                },
            },
            "dark",
        ],
        darkTheme: "dark",
        base: true,
        styled: true,
        utils: true,
    },
};
