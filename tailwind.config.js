import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/*
 * Sistemul vizual (ADR 007, ADR 015). Documentație: docs/ui.md.
 *
 * 1. Paleta brandului „Verde cafea” (ADR 016): leaf (verdele frunzei de cafea, acțiunea principală),
 *    honey (ambra recompenselor și a ștampilelor), graphite (neutre gri-verzui), paper (fundaluri calde).
 *    Paginile aplicației NU o folosesc direct; o folosesc doar tokenurile de mai jos
 *    și landing-ul (direcție artistică proprie).
 */
const leaf = {
    50: '#EEF6F2',
    100: '#D5EADF',
    200: '#ABD4C0',
    300: '#7AB89C',
    400: '#4A9878',
    500: '#2E7A5E',
    600: '#1F5B45',
    700: '#194A39',
    800: '#143A2D',
    900: '#0F2C22',
};

const honey = {
    50: '#FDF5EC',
    100: '#FAE6CF',
    200: '#F4CB9C',
    300: '#EDAE69',
    400: '#E39645',
    500: '#D9822B',
    600: '#B96A1E',
    700: '#93531A',
    800: '#6F3F17',
    900: '#4F2E13',
};

const graphite = {
    50: '#F4F5F4',
    100: '#E4E7E5',
    200: '#CBD0CD',
    300: '#A9B0AC',
    400: '#848C88',
    500: '#646C68',
    600: '#4B524F',
    700: '#363C39',
    800: '#262B29',
    900: '#1A1D1B',
    950: '#111312',
};

const paper = {
    DEFAULT: '#EFEDE6',
    50: '#F7F6F2',
    100: '#EFEDE6',
    200: '#E4E1D8',
};

/*
 * 2. Tokenuri semantice: aici se schimbă culorile întregii aplicații.
 *    Numele spun rolul, nu nuanța: `bg-surface`, `text-ink-muted`, `border-line`, `bg-danger`.
 */
const semantic = {
    // Fundalul paginii.
    canvas: paper[50],
    // Carduri, panouri, câmpuri; `muted` = zone de fundal secundare (pastile, rânduri evidențiate).
    surface: {
        DEFAULT: '#FFFFFF',
        muted: paper[100],
        strong: paper[200],
    },
    // Text: `ink` titluri și text principal, `secondary` paragrafe, `muted` meta/indicii (min. 4.5:1 pe alb).
    ink: {
        DEFAULT: graphite[900],
        secondary: graphite[600],
        muted: graphite[500],
    },
    // Borduri: `line` separatoare și carduri, `strong` câmpuri de formular.
    line: {
        DEFAULT: graphite[100],
        strong: graphite[200],
    },
    // Acțiunea principală (verde). `fg` = textul de pe ea, `ink` = text/linkuri în culoarea brandului.
    primary: {
        DEFAULT: leaf[600],
        hover: leaf[500],
        active: leaf[700],
        fg: '#FFFFFF',
        soft: leaf[50],
        line: leaf[200],
        ink: leaf[600],
    },
    // Accentul cald: recompense, ștampile și evidențierile de pe fundalurile închise (unde verdele nu are contrast).
    accent: {
        DEFAULT: honey[500],
        hover: honey[400],
        fg: graphite[950],
        soft: honey[50],
        line: honey[200],
        ink: honey[700],
    },
    // Acțiuni neutre închise și zonele închise (sidebar, header client, cardul de fidelitate).
    inverse: {
        DEFAULT: graphite[900],
        deep: graphite[950],
        raised: graphite[800],
        line: graphite[700],
        fg: paper[50],
        muted: graphite[300],
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
    focus: leaf[500],
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
            colors: { leaf, honey, graphite, paper, ...semantic },
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
                card: '0 1px 2px 0 rgb(26 29 27 / 0.05)',
                overlay: '0 24px 48px -12px rgb(17 19 18 / 0.35)',
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
                // Lumina verde-ambră de pe fundalurile închise (auth, landing, cardul de fidelitate).
                glow: 'radial-gradient(ellipse at 70% 10%, rgb(46 122 94 / 0.35), transparent 55%), radial-gradient(ellipse at 0% 100%, rgb(217 130 43 / 0.15), transparent 50%)',
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
