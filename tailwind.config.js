import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/View/**/*.php',
    ],

    theme: {
        extend: {
            // Sistemul vizual din ADR 007: espresso (fundaluri închise, text), auriu (accent), crem (suprafețe calde).
            colors: {
                espresso: {
                    50: '#F6F2EF',
                    100: '#E9E1DB',
                    200: '#D3C4B9',
                    300: '#B29D8E',
                    400: '#8C7464',
                    500: '#6B5547',
                    600: '#523F34',
                    700: '#3D312C',
                    800: '#2F2622',
                    900: '#231B18',
                    950: '#161110',
                },
                gold: {
                    50: '#FBF6EA',
                    100: '#F5E8C8',
                    200: '#EBD193',
                    300: '#E0B95F',
                    400: '#D6A743',
                    500: '#CC9933',
                    600: '#AE7E25',
                    700: '#8A6220',
                    800: '#6B4B1D',
                    900: '#4F3818',
                },
                cream: {
                    DEFAULT: '#F7F1E8',
                    50: '#FDFBF7',
                    100: '#F7F1E8',
                    200: '#EFE5D5',
                },
            },
            fontFamily: {
                sans: ['Onest', ...defaultTheme.fontFamily.sans],
                // Kaushan Script are ș/ț cu virgulă dedesubt (Yellowtail nu le are).
                script: ['"Kaushan Script"', 'cursive'],
            },
            borderRadius: {
                '4xl': '2rem',
            },
            keyframes: {
                marquee: {
                    from: { transform: 'translateX(0)' },
                    to: { transform: 'translateX(-50%)' },
                },
                'stamp-in': {
                    '0%': { transform: 'scale(0.4) rotate(-25deg)', opacity: '0' },
                    '60%': { transform: 'scale(1.15) rotate(6deg)', opacity: '1' },
                    '100%': { transform: 'scale(1) rotate(0)', opacity: '1' },
                },
                float: {
                    '0%, 100%': { transform: 'translateY(0) rotate(-4deg)' },
                    '50%': { transform: 'translateY(-12px) rotate(-2deg)' },
                },
            },
            animation: {
                marquee: 'marquee 40s linear infinite',
                'stamp-in': 'stamp-in 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) both',
                float: 'float 7s ease-in-out infinite',
            },
        },
    },

    plugins: [forms],
};
