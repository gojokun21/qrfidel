// Încărcat doar pe pagina cardului (chunk separat), nu în bundle-ul comun cu dashboard-ul.
const loadQrCode = () => import('qrcode').then((module) => module.default);

/**
 * QR-ul rotativ al cardului (SDD §5.2, ADR 005, ADR 010). Serverul emite tokenul; aici doar îl cerem
 * la fiecare `refreshSeconds` și îl desenăm. Fără conexiune, ultimul cod rămâne pe ecran cât e valabil.
 */
export default function qrDisplay(url) {
    return {
        svg: '',
        shortCode: '',
        expiresAt: 0,
        nextAt: 0,
        now: Date.now(),
        error: '',
        offline: false,
        stopped: false,
        loading: false,
        timer: null,
        ticker: null,

        init() {
            this.refresh();
            this.ticker = setInterval(() => this.tick(), 1000);

            // Telefonul în buzunar nu consumă rotații. La revenire codul se schimbă doar dacă îi venise rândul
            // (altfel clientul ar pierde codul pe care tocmai îl dictează, iar limita de 10/min s-ar consuma).
            this.onVisibility = () => {
                if (document.hidden) {
                    this.pause();
                } else if (Date.now() >= this.nextAt || this.expired) {
                    this.refresh();
                } else {
                    this.schedule((this.nextAt - Date.now()) / 1000);
                }
            };
            document.addEventListener('visibilitychange', this.onVisibility);
            window.addEventListener('online', this.onVisibility);
        },

        destroy() {
            this.pause();
            clearInterval(this.ticker);
            document.removeEventListener('visibilitychange', this.onVisibility);
            window.removeEventListener('online', this.onVisibility);
        },

        get expired() {
            return this.svg !== '' && this.now >= this.expiresAt;
        },

        get secondsLeft() {
            return Math.max(0, Math.ceil((this.nextAt - this.now) / 1000));
        },

        get formattedCode() {
            return this.shortCode.replace(/^(.{3})(.{3})$/, '$1 $2');
        },

        tick() {
            this.now = Date.now();
        },

        pause() {
            clearTimeout(this.timer);
            this.timer = null;
        },

        schedule(seconds) {
            this.pause();
            this.nextAt = Date.now() + seconds * 1000;
            this.timer = setTimeout(() => this.refresh(), seconds * 1000);
        },

        async refresh() {
            // Fără cereri suprapuse: răspunsurile ar putea veni invers și pagina ar arăta un cod deja înlocuit.
            if (this.stopped || this.loading || document.hidden) {
                return;
            }

            this.loading = true;
            this.pause();

            try {
                const { data } = await window.axios.post(url, null, { headers: { Accept: 'application/json' } });

                const QRCode = await loadQrCode();
                this.svg = await QRCode.toString(data.token, { type: 'svg', errorCorrectionLevel: 'M', margin: 1 });
                this.shortCode = data.shortCode;
                // Ceasul telefonului poate fi decalat: valabilitatea se măsoară de la primire, nu de la issuedAt.
                this.expiresAt = Date.now() + (Date.parse(data.expiresAt) - Date.parse(data.issuedAt));
                this.error = '';
                this.offline = false;
                this.tick();
                this.schedule(data.refreshSeconds);
            } catch (e) {
                const status = e.response?.status;

                if (status === 409) {
                    // Program oprit sau afacere suspendată: nu mai are rost să cerem coduri.
                    this.stopped = true;
                    this.svg = '';
                    this.error = e.response.data.message;
                    this.pause();
                } else if (status === 401 || status === 419) {
                    window.location.reload();
                } else {
                    this.offline = !e.response;
                    this.schedule(status === 429 ? 30 : 5);
                }
            } finally {
                this.loading = false;
            }
        },
    };
}
