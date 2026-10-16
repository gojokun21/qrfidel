/**
 * PWA-ul clientului (ADR 010): service worker-ul se înregistrează doar pe paginile cu `data-pwa`
 * (layout-ul `customer`), iar `installPrompt` afișează „Adaugă pe ecranul principal”.
 */
const DISMISS_KEY = 'pwa-install-dismissed';

let deferredPrompt = null;

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredPrompt = event;
    window.dispatchEvent(new CustomEvent('pwa-installable'));
});

export function registerServiceWorker() {
    if ('serviceWorker' in navigator && document.body.hasAttribute('data-pwa')) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
    }
}

function readDismissed() {
    try {
        return localStorage.getItem(DISMISS_KEY) === '1';
    } catch {
        return false;
    }
}

export function installPrompt() {
    return {
        canPrompt: deferredPrompt !== null,
        // iPadOS 13+ se prezintă ca Mac; îl recunoaștem după ecranul tactil.
        ios: /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1),
        installed: window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true,
        dismissed: readDismissed(),

        init() {
            window.addEventListener('pwa-installable', () => (this.canPrompt = true));
            window.addEventListener('appinstalled', () => (this.installed = true));
        },

        get visible() {
            return !this.installed && !this.dismissed && (this.canPrompt || this.ios);
        },

        async install() {
            if (!deferredPrompt) {
                return;
            }

            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            deferredPrompt = null;
            this.canPrompt = false;
            this.installed = outcome === 'accepted';
        },

        dismiss() {
            this.dismissed = true;
            try {
                localStorage.setItem(DISMISS_KEY, '1');
            } catch {
                // Fără storage (navigare privată): butonul revine la următoarea vizită.
            }
        },
    };
}
