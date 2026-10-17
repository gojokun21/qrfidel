import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/*
 * Sistemul vizual (ADR 007, ADR 015). Documentație: docs/ui.md.
 *
 * 1. Paleta brandului LoyUp (ADR 017), luată din logo: jade (verdele cardului din logo, `jade-500` = #01A760),
 *    navy (bleumarinul textului din logo, `navy-900` = #012156), steel (neutre reci, fundaluri și borduri).
 *    Paginile aplicației NU o folosesc direct; o folosesc doar tokenurile de mai jos
 *    și landing-ul (direcție artistică proprie).
 */
const jade = {
    50: '#EAF8F1',
    100: '#CDF0DE',
    200: '#9BE0BD',
    300: '#5ECD98',
    400: '#2DBB7B',
    500: '#01A760',
    600: '#037A47',
    700: '#036A3E',
    800: '#05532F',
    900: '#074027',
};

const navy = {
    50: '#EEF2F9',
    100: '#D8E0EE',
    200: '#B0C0DC',
    300: '#8099C4',
    400: '#5373A8',
    500: '#33548E',
    600: '#1E4078',
    700: '#113266',
    800: '#08285D',
    900: '#012156',
    950: '#01153A',
};

const steel = {
    DEFAULT: '#E9EDF3',
    50: '#F6F8FB',
    100: '#E9EDF3',
    200: '#D3DAE4',
    300: '#AEB8C7',
    400: '#8794A8',
    500: '#58657C',
    600: '#47546A',
    700: '#343F52',
};

/*
 * 2. Tokenuri semantice: aici se schimbă culorile întregii aplicații.
 *    Numele spun rolul, nu nuanța: `bg-surface`, `text-ink-muted`, `border-line`, `bg-danger`.
 */
const semantic = {
    // Fundalul paginii.
    canvas: steel[50],
    // Carduri, panouri, câmpuri; `muted` = zone de fundal secundare (pastile, rânduri evidențiate).
    surface: {
        DEFAULT: '#FFFFFF',
        muted: steel[100],
        strong: steel[200],
    },
    // Text: `ink` titluri și text principal (bleumarinul din logo), `secondary` paragrafe, `muted` meta/indicii (min. 4.5:1 pe alb).
    ink: {
        DEFAULT: navy[900],
        secondary: steel[600],
        muted: steel[500],
    },
    // Borduri: `line` separatoare și carduri, `strong` câmpuri de formular.
    line: {
        DEFAULT: steel[100],
        strong: steel[200],
    },
    // Acțiunea principală (verde). `fg` = textul de pe ea, `ink` = text/linkuri în culoarea brandului.
    // Verdele din logo (`jade-500`) are doar 3.1:1 cu alb, așa că butoanele folosesc `jade-600` (5.4:1).
    primary: {
        DEFAULT: jade[600],
        hover: jade[700],
        active: jade[800],
        fg: '#FFFFFF',
        soft: jade[50],
        line: jade[200],
        ink: jade[600],
    },
    // Accentul: verdele exact din logo. Recompense, ștampile și evidențierile de pe fundalurile închise
    // (pe bleumarin are 5:1); textul de pe el e bleumarin.
    accent: {
        DEFAULT: jade[500],
        hover: jade[400],
        fg: navy[900],
        soft: jade[50],
        line: jade[200],
        ink: jade[700],
    },
    // Acțiuni neutre închise și zonele închise (sidebar, header client, cardul de fidelitate): bleumarinul din logo.
    inverse: {
        DEFAULT: navy[900],
        deep: navy[950],
        raised: navy[800],
        line: navy[700],
        fg: steel[50],
        muted: navy[200],
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
    // Inelul de focus: contrast ≥ 3:1 și pe alb/canvas (3.3:1), și pe bleumarin (4.4:1). Verdele exact din logo
    // are doar 2.95:1 pe canvas, de aceea o nuanță puțin mai închisă.
    focus: '#019C59',
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
            colors: { jade, navy, steel, ...semantic },
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
                // Lumina verde-albastră de pe fundalurile închise (auth, landing, cardul de fidelitate).
                glow: 'radial-gradient(ellipse at 70% 10%, rgb(1 167 96 / 0.30), transparent 55%), radial-gradient(ellipse at 0% 100%, rgb(83 115 168 / 0.25), transparent 50%)',
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
