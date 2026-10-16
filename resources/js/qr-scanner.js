// Încărcat doar pe pagina de scanare (chunk separat): camera și decodorul nu ajung în bundle-ul comun.
const loadScanner = () => import('qr-scanner').then((module) => module.default);

/** Cât timp ignorăm un cod deja refuzat care stă în fața camerei (altfel l-am retrimite la 2–3 s, până la limita de 30/min). */
const REJECTED_CODE_PAUSE_MS = 15000;

/**
 * Scanerul angajatului (SDD §5.2, ADR 011): camera din spate citește QR-ul clientului, trimitem conținutul
 * la `resolve` și deschidem ecranul cardului primit. Fără cameră (permisiune refuzată, lipsă HTTPS), rămâne
 * codul scurt tastat, care merge prin formularul obișnuit.
 */
export default function qrScanner(url) {
    return {
        scanner: null,
        state: 'idle', // idle | starting | scanning | busy | unavailable
        error: '',
        rejected: { code: '', until: 0 },

        async init() {
            this.onVisibility = () => (document.hidden ? this.stop() : this.start());
            document.addEventListener('visibilitychange', this.onVisibility);

            await this.start();
        },

        destroy() {
            document.removeEventListener('visibilitychange', this.onVisibility);
            this.scanner?.destroy();
            this.scanner = null;
        },

        async start() {
            if (document.hidden || ['scanning', 'starting', 'busy', 'unavailable'].includes(this.state)) {
                return;
            }

            if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
                this.unavailable('Camera merge doar pe HTTPS. Tastează codul clientului mai jos.');
                return;
            }

            this.state = 'starting';

            try {
                const QrScanner = await loadScanner();
                this.scanner ??= new QrScanner(this.$refs.video, (result) => this.found(result.data), {
                    preferredCamera: 'environment',
                    highlightScanRegion: true,
                    maxScansPerSecond: 5,
                    returnDetailedScanResult: true,
                });
                await this.scanner.start();
            } catch {
                this.unavailable('Nu am acces la cameră. Permite accesul din browser sau tastează codul clientului.');
                return;
            }

            // Tab-ul a fost ascuns cât am așteptat permisiunea sau încărcarea: camera nu rămâne pornită în fundal.
            if (document.hidden || this.state !== 'starting') {
                this.scanner.stop();
                this.state = 'idle';
                return;
            }

            this.state = 'scanning';
        },

        stop() {
            if (this.state === 'busy' || this.state === 'unavailable') {
                return;
            }

            this.scanner?.stop();
            this.state = 'idle';
        },

        unavailable(message) {
            this.state = 'unavailable';
            this.error = message;
        },

        async found(code) {
            // O singură cerere pe rând: decodorul raportează același cod de mai multe ori pe secundă.
            if (this.state !== 'scanning') {
                return;
            }

            if (code === this.rejected.code && Date.now() < this.rejected.until) {
                return;
            }

            this.state = 'busy';
            this.scanner.stop();
            this.error = '';

            try {
                const { data } = await window.axios.post(url, { code }, { headers: { Accept: 'application/json' } });
                window.location.assign(data.redirect);
            } catch (e) {
                const status = e.response?.status;

                // Sesiune sau token CSRF expirat: reîncărcarea duce la login (sau dă un token nou).
                if (status === 401 || status === 419) {
                    window.location.reload();
                    return;
                }

                this.error = status === 429
                    ? 'Prea multe scanări într-un minut. Așteaptă puțin.'
                    : (e.response?.data?.message ?? 'Nu am putut verifica codul. Încearcă din nou.');
                this.rejected = { code, until: Date.now() + REJECTED_CODE_PAUSE_MS };
                this.state = 'idle';
                // O pauză scurtă, ca angajatul să citească mesajul; același cod e ignorat o vreme.
                setTimeout(() => this.start(), 2000);
            }
        },
    };
}
