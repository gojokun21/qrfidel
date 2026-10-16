/**
 * Wizard-ul de programe (SDD §7): pași, template-uri și previzualizarea live a cardului.
 * Toate câmpurile stau într-un singur <form>; serverul validează oricum tot (ProgramRequest).
 */

const HEX = /^#[0-9a-fA-F]{6}$/;

// Luminanța lui #1c1410, textul închis folosit pe card.
const DARK_LUMINANCE = 0.0078;

// Aceeași regulă ca App\Support\Color::readableOn().
export function readableOn(hex) {
    if (!HEX.test(hex)) return '#1c1410';

    const channel = (part) => {
        const c = parseInt(part, 16) / 255;
        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    };
    const l = 0.2126 * channel(hex.slice(1, 3)) + 0.7152 * channel(hex.slice(3, 5)) + 0.0722 * channel(hex.slice(5, 7));

    return (l + 0.05) / (DARK_LUMINANCE + 0.05) >= 1.05 / (l + 0.05) ? '#1c1410' : '#ffffff';
}

// Pe ce pas stă fiecare câmp, ca la o eroare de validare wizard-ul să se deschidă unde trebuie.
const FIELD_STEPS = {
    template: 1,
    name: 1,
    stamps_required: 2,
    max_stamps_per_visit: 2,
    cooldown_minutes: 2,
    daily_limit: 2,
    stamps_expire_days: 2,
    reward_expire_days: 2,
    rollover_extra: 2,
    loyalty: 2,
    reward_title: 3,
    reward_description: 3,
    color: 3,
    stamp_icon: 3,
    terms: 3,
};

export default (config) => {
    // Markup-ul iconurilor, citit o singură dată per icon (previzualizarea îl cere la fiecare ștampilă).
    // Stă în afara stării Alpine, ca citirea din `preview` să nu declanșeze re-randări.
    const icons = {};

    return {
        steps: config.steps,
        step: config.steps[0],
        templates: config.templates,
        selected: config.selected,
        fallbackColor: config.fallbackColor,
        form: config.form,
        submitting: false,

        init() {
            const failed = config.errors.map((field) => FIELD_STEPS[field]).filter((step) => this.steps.includes(step));
            if (failed.length) this.step = Math.min(...failed);
        },

        get stepIndex() {
            return this.steps.indexOf(this.step);
        },

        get isLast() {
            return this.stepIndex === this.steps.length - 1;
        },

        get preview() {
            const required = Math.min(50, Math.max(1, parseInt(this.form.stamps_required, 10) || 1));
            const color = HEX.test(this.form.color) ? this.form.color : this.fallbackColor;

            return {
                name: this.form.name,
                reward: this.form.reward_title,
                required,
                filled: Math.max(0, Math.min(3, required - 1)),
                color,
                ink: readableOn(color),
                iconSvg: this.iconSvg(this.form.stamp_icon),
            };
        },

        iconSvg(name) {
            icons[name] ??= this.$refs[`icon-${name}`]?.innerHTML;
            return icons[name] ?? '';
        },

        applyTemplate(key) {
            const template = this.templates.find((t) => t.key === key);
            if (!template) return;

            this.selected = key;
            // Culoarea rămâne cea aleasă de owner; restul vine din template și se poate edita.
            Object.entries(template.attributes).forEach(([field, value]) => {
                this.form[field] = value ?? '';
            });
        },

        go(step) {
            // Înainte se poate merge doar cu pasul curent valid; înapoi oricând.
            if (this.steps.indexOf(step) > this.stepIndex && !this.validate(this.step)) return;
            this.step = step;
            this.$nextTick(() => this.$refs.wizard?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
        },

        next() {
            if (!this.isLast) this.go(this.steps[this.stepIndex + 1]);
        },

        back() {
            if (this.stepIndex > 0) this.go(this.steps[this.stepIndex - 1]);
        },

        validate(step) {
            const panel = this.$refs[`step-${step}`];
            if (!panel) return true;

            const invalid = [...panel.querySelectorAll('input, select, textarea')].find((field) => !field.checkValidity());
            if (!invalid) return true;

            this.step = step;
            this.$nextTick(() => invalid.reportValidity());
            return false;
        },

        // Formularul are `novalidate` (câmpurile din pașii ascunși nu pot primi focus), deci verificăm noi pe pași.
        submit(event) {
            if (!this.steps.every((step) => this.validate(step))) {
                event.preventDefault();
                return;
            }
            // După ce browserul a citit butonul apăsat (`intent`): un dublu-click nu mai trimite a doua oară.
            setTimeout(() => {
                this.submitting = true;
            });
        },

        // Enter într-un câmp duce la pasul următor; se salvează doar cu butoanele de pe ultimul pas.
        enter(event) {
            if (event.target.tagName === 'TEXTAREA' || event.target.tagName === 'BUTTON') return;

            event.preventDefault();
            this.next();
        },
    };
};
