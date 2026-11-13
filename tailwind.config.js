import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */

// Brand red ramp (primary #c01917). Used to repaint the two color scales
// ('cyan' and 'indigo') that this app already uses as its accent/brand
// colors throughout the UI (sidebar highlights, buttons, focus rings,
// links, badges, pagination), without touching any blade markup.
const brandRed = {
    50: '#fdf2f2',
    100: '#fce4e3',
    200: '#f8caca',
    300: '#f0a3a2',
    400: '#e4726f',
    500: '#d1443f',
    600: '#c01917',
    700: '#a61413',
    800: '#8f1111',
    900: '#6b0d0d',
    950: '#3d0606',
};

export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                cyan: brandRed,
                indigo: brandRed,
            },
            // Premium-card system: these two radii are the ones used almost
            // everywhere in this app for cards/panels (2xl) and
            // buttons/inputs/pills (xl), so bumping them here instantly
            // modernizes the whole app without touching blade markup.
            borderRadius: {
                xl: '10px',
                '2xl': '14px',
            },
            boxShadow: {
                sm: '0 10px 25px rgba(0, 0, 0, 0.06)',
                card: '0 10px 25px rgba(0, 0, 0, 0.06)',
            },
            transitionDuration: {
                DEFAULT: '250ms',
                250: '250ms',
            },
        },
    },

    plugins: [forms],
};
