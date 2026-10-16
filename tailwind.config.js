import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/*
 * Sistemul vizual (ADR 007, ADR 015). Documentație: docs/ui.md.
 *
 * 1. Paleta brandului: espresso (închis), gold (accent), cream (suprafețe calde).
 *    Paginile aplicației NU o folosesc direct; o folosesc doar tokenurile de mai jos
 *    și landing-ul (direcție artistică proprie).
 */
const espresso = {
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
};

const gold = {
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
};

const cream = {
    DEFAULT: '#F7F1E8',
    50: '#FDFBF7',
    100: '#F7F1E8',
    200: '#EFE5D5',
};

/*
 * 2. Tokenuri semantice: aici se schimbă culorile întregii aplicații.
 *    Numele spun rolul, nu nuanța: `bg-surface`, `text-ink-muted`, `border-line`, `bg-danger`.
 */
const semantic = {
    // Fundalul paginii.
    canvas: cream[50],
    // Carduri, panouri, câmpuri; `muted` = zone de fundal secundare (pastile, rânduri evidențiate).
    surface: {
        DEFAULT: '#FFFFFF',
        muted: cream[100],
        strong: cream[200],
    },
    // Text: `ink` titluri și text principal, `secondary` paragrafe, `muted` meta/indicii (min. 4.5:1 pe alb).
    ink: {
        DEFAULT: espresso[900],
        secondary: espresso[600],
        muted: espresso[500],
    },
    // Borduri: `line` separatoare și carduri, `strong` câmpuri de formular.
    line: {
        DEFAULT: espresso[100],
        strong: espresso[200],
    },
    // Acțiunea principală (auriu). `fg` = textul de pe ea, `ink` = text/linkuri în culoarea brandului.
    primary: {
        DEFAULT: gold[500],
        hover: gold[400],
        active: gold[600],
        fg: espresso[950],
        soft: gold[50],
        line: gold[200],
        ink: gold[700],
    },
    // Acțiuni neutre închise și zonele închise (sidebar, header client, cardul de fidelitate).
    inverse: {
        DEFAULT: espresso[900],
        deep: espresso[950],
        raised: espresso[800],
        line: espresso[700],
        fg: cream[50],
        muted: espresso[300],
    },
    success: {
        DEFAULT: '#047857',
        soft: '#ECFDF5',
        line: '#A7F3D0',
        ink: '#064E3B',
    },
    warning: {
        DEFAULT: '#B45309',
        soft: '#FFFBEB',
        line: '#FDE68A',
        ink: '#78350F',
    },
    danger: {
        DEFAULT: '#DC2626',
        hover: '#B91C1C',
        fg: '#FFFFFF',
        soft: '#FEF2F2',
        line: '#FECACA',
        ink: '#7F1D1D',
    },
    // Inelul de focus: contrast ≥ 3:1 și pe alb, și pe fundalurile închise.
    focus: gold[600],
};

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/View/**/*.php',
    ],

    theme: {
        extend: {
            colors: { espresso, gold, cream, ...semantic },
            fontFamily: {
                sans: ['Onest', ...defaultTheme.fontFamily.sans],
                // Kaushan Script are ș/ț cu virgulă dedesubt (Yellowtail nu le are). Doar pe landing.
                script: ['"Kaushan Script"', 'cursive'],
            },
            // Razele: controale (butoane, câmpuri), carduri, panouri mari (cardul de fidelitate, hero).
            borderRadius: {
                control: '0.75rem',
                card: '1rem',
                panel: '1.5rem',
                '4xl': '2rem',
            },
            boxShadow: {
                card: '0 1px 2px 0 rgb(35 27 24 / 0.05)',
                overlay: '0 24px 48px -12px rgb(22 17 16 / 0.35)',
            },
            // Lățimile conținutului: pagini, formulare, ecranele de lucru ale angajatului / clientului.
            maxWidth: {
                content: '72rem',
                form: '42rem',
                narrow: '32rem',
            },
            zIndex: {
                // Linkul „Sari la conținut”: deasupra sidebar-ului și a dialogurilor (z-50).
                skip: '60',
            },
            letterSpacing: {
                eyebrow: '0.18em',
            },
            backgroundImage: {
                // Lumina aurie de pe fundalurile închise (auth, landing, cardul de fidelitate).
                glow: 'radial-gradient(ellipse at 70% 10%, rgb(204 153 51 / 0.3), transparent 55%), radial-gradient(ellipse at 0% 100%, rgb(204 153 51 / 0.15), transparent 50%)',
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
                // Linia de sub titlu (`pathLength="1"`, `stroke-dasharray: 1`).
                draw: {
                    from: { strokeDashoffset: '1' },
                    to: { strokeDashoffset: '0' },
                },
                twinkle: {
                    '0%, 100%': { transform: 'scale(1) rotate(0)', opacity: '1' },
                    '50%': { transform: 'scale(0.6) rotate(45deg)', opacity: '0.5' },
                },
            },
            animation: {
                marquee: 'marquee 40s linear infinite',
                'stamp-in': 'stamp-in 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) both',
                float: 'float 7s ease-in-out infinite',
                // Toate trei se folosesc doar cu `motion-safe:`.
                'spin-slow': 'spin 24s linear infinite',
                draw: 'draw 1.2s cubic-bezier(0.65, 0, 0.35, 1) 0.4s both',
                twinkle: 'twinkle 3.2s ease-in-out infinite',
            },
        },
    },

    plugins: [forms],
};
