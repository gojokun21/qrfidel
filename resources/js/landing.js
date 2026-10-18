/*
 * Animațiile landing-ului (Figma „Landing v3” → „Interacțiuni (GSAP)”). Se încarcă doar pe paginile de marketing.
 * Totul e progresiv: fără JavaScript sau cu prefers-reduced-motion, pagina rămâne exact designul static.
 *
 * data-anim="line"     titlul hero intră rând cu rând (masca e părintele cu overflow-hidden)
 * data-anim="fade"     elementele hero apar după titlu
 * data-anim="parallax" fotografia se mișcă ușor la scroll (imaginea are scale-110, deci nu se văd margini)
 * data-anim="count"    cifra numără de la 0 (data-count, data-prefix, data-suffix)
 * data-anim="steps"    secțiunea se fixează pe desktop, iar pașii [data-step] devin activi pe rând
 * data-anim="stagger"  copiii listei intră pe rând
 */
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function hero() {
    const lines = document.querySelectorAll('[data-anim="line"]');
    const fades = document.querySelectorAll('[data-anim="fade"]');
    if (!lines.length) return;

    gsap.timeline({ defaults: { ease: 'power3.out' } })
        .from(lines, { yPercent: 105, duration: 0.9, stagger: 0.12 })
        .from(fades, { y: 16, opacity: 0, duration: 0.6, stagger: 0.08 }, '-=0.5');
}

function parallax() {
    document.querySelectorAll('[data-anim="parallax"]').forEach((img) => {
        gsap.fromTo(img, { yPercent: -4 }, {
            yPercent: 4,
            ease: 'none',
            scrollTrigger: { trigger: img.parentElement, start: 'top bottom', end: 'bottom top', scrub: true },
        });
    });
}

function counters() {
    document.querySelectorAll('[data-anim="count"]').forEach((el) => {
        const target = Number(el.dataset.count);
        const { prefix = '', suffix = '' } = el.dataset;
        const state = { value: 0 };

        gsap.to(state, {
            value: target,
            duration: 1.4,
            ease: 'power2.out',
            scrollTrigger: { trigger: el, start: 'top 90%', once: true },
            onUpdate: () => { el.textContent = `${prefix}${Math.round(state.value)}${suffix}`; },
        });
    });
}

function steps() {
    const section = document.querySelector('[data-anim="steps"]');
    if (!section) return;

    const items = [...section.querySelectorAll('[data-step]')];
    const bars = [...section.querySelectorAll('[data-step-bar]')];
    const activate = (index) => {
        [items, bars].forEach((group) => group.forEach((el, i) => { el.dataset.active = String(i === index); }));
    };

    // Doar pe desktop: secțiunea se fixează cât derulezi prin pași. Pe telefon, pașii rămân așa cum sunt.
    ScrollTrigger.matchMedia({
        '(min-width: 1024px)': () => {
            activate(0);
            const trigger = ScrollTrigger.create({
                trigger: section,
                start: 'top top',
                end: `+=${items.length * 45}%`,
                pin: true,
                onUpdate: (self) => activate(Math.min(items.length - 1, Math.floor(self.progress * items.length))),
            });

            return () => { trigger.kill(); activate(items.length - 1); };
        },
    });
}

function stagger() {
    document.querySelectorAll('[data-anim="stagger"]').forEach((list) => {
        gsap.from(list.children, {
            y: 24,
            opacity: 0,
            duration: 0.7,
            ease: 'power3.out',
            stagger: 0.1,
            scrollTrigger: { trigger: list, start: 'top 85%', once: true },
        });
    });
}

if (!reduceMotion) {
    hero();
    parallax();
    counters();
    steps();
    stagger();
}
