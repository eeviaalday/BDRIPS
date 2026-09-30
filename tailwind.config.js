import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/View/Components/**/*.php',
        // Class names are also held in config/bdrpis.php and echoed into markup
        // through {{ $item['tone'] }} / {{ $item['badge'] }}. Without this glob
        // those utilities are never generated, so a colour used only in config
        // silently renders with no styling at all.
        './config/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },

            /*
             * BDRPIS brand palette.
             * Navy  = primary (headers, footers, primary buttons)
             * Sky   = Tailwind default, used for links/accents
             * Gold  = secondary accent (CTAs, active states, highlights)
             * Keep names stable: existing views use navy, navy-light, gold, gold-light.
             */
            colors: {
                navy: {
                    DEFAULT: '#0D233A',
                    50: '#F2F6FA',
                    100: '#E1EAF3',
                    200: '#C2D4E6',
                    300: '#93B2D2',
                    400: '#5C87B5',
                    500: '#3A6595',
                    600: '#2A4D77',
                    700: '#1F3B5C',
                    800: '#152C45',
                    900: '#0D233A',
                    light: '#1E3A8A',
                },
                gold: {
                    DEFAULT: '#FBBF24',
                    50: '#FFFBEB',
                    100: '#FEF3C7',
                    200: '#FDE68A',
                    light: '#FDE68A',
                    300: '#FCD34D',
                    400: '#FBBF24',
                    500: '#F59E0B',
                    600: '#D97706',
                    700: '#B45309',
                    800: '#92400E',
                    900: '#78350F',
                },
                surface: '#F6F9FC',
            },

            maxWidth: {
                shell: '80rem',
            },

            boxShadow: {
                card: '0 1px 2px 0 rgb(13 35 58 / 0.04), 0 1px 3px 0 rgb(13 35 58 / 0.06)',
                'card-hover': '0 10px 25px -5px rgb(13 35 58 / 0.10), 0 8px 10px -6px rgb(13 35 58 / 0.06)',
                header: '0 4px 20px -4px rgb(13 35 58 / 0.25)',
            },

            fontSize: {
                '2xs': ['0.6875rem', { lineHeight: '1rem' }],
            },

            keyframes: {
                'fade-up': {
                    '0%': { opacity: '0', transform: 'translateY(12px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
            },

            animation: {
                'fade-up': 'fade-up 0.5s ease-out both',
            },

            transitionTimingFunction: {
                smooth: 'cubic-bezier(0.4, 0, 0.2, 1)',
            },
        },
    },

    plugins: [forms],
};
