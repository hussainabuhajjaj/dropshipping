import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',

        // Filament vendor views & components (required when using a custom panel theme)
        './vendor/filament/**/*.blade.php',
        './vendor/filament/**/*.js',
        './vendor/filament/**/*.ts',
        './vendor/filament/**/*.vue',
        './vendor/filament/**/*.php',

        // Panel theme sources
        './resources/css/filament/**/*.css',
    ],
    input: [
    // ...
    'resources/css/filament/admin/theme.css',
],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Manrope', ...defaultTheme.fontFamily.sans],
                heading: ['Syne', 'sans-serif'],
            },
            colors: {
                brand: {
                    primary: '#0F172A',
                    accent: '#EF4444',
                    'accent-hover': '#DC2626',
                    muted: '#64748B',
                    light: '#F8FAFC',
                    border: '#E2E8F0',
                    surface: '#FFFFFF',
                },
            },
        },
    },

    plugins: [forms],
};
