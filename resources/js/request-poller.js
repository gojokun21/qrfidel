/**
 * UUID v4. `crypto.randomUUID` există doar în context securizat (HTTPS / localhost); pe HTTP în rețeaua locală
 * îl construim din `getRandomValues`, disponibil peste tot.
 */
function uuid() {
    if (typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

/**
 * Panoul cu cererile de la tejghea (SDD §5.3, §8, ADR 012): lista se reîmprospătează la `pollSeconds`,
 * „Aprobă” / „Respinge” merg prin fetch JSON. Fiecare cerere are cheia ei de idempotency, păstrată între
 * reîmprospătări: un dublu-click trimite aceeași cheie (replay), nu a doua ștampilă.
 */
export default function requestPoller(feedUrl, initial = [], pollSeconds = 5) {
    return {
        requests: initial,
        keys: {},
        acting: null,
        message: '',
        error: '',
        now: Date.now(),
        timer: null,
        ticker: null,
        loading: false,
        refreshAgain: false,
        version: 0,

        init() {
            this.schedule();
            this.ticker = setInterval(() => (this.now = Date.now()), 15000);
            this.updateTitle();

            this.onVisibility = () => {
                if (document.hidden) {
                    clearTimeout(this.timer);
                } else {
                    this.refresh();
                }
            };
            document.addEventListener('visibilitychange', this.onVisibility);
        },

        destroy() {
            clearTimeout(this.timer);
            clearInterval(this.ticker);
            document.removeEventListener('visibilitychange', this.onVisibility);
        },

        schedule() {
            clearTimeout(this.timer);
            if (!document.hidden) {
                this.timer = setTimeout(() => this.refresh(), pollSeconds * 1000);
            }
        },

        async refresh() {
            if (this.loading) {
                this.refreshAgain = true;
                return;
            }
            this.loading = true;
            // O acțiune terminată în timpul poll-ului face lista primită veche (ar readuce cererea tratată).
            const version = this.version;

            try {
                const { data } = await this.send('get', feedUrl);
                if (version === this.version) {
                    this.requests = data.requests;
                    this.error = '';
                    this.updateTitle();
                } else {
                    this.refreshAgain = true;
                }
            } catch (error) {
                if (error.handled) {
                    return;
                }
                this.error = error.response?.data?.message ?? 'Fără conexiune – reîncercăm automat.';
            } finally {
                this.loading = false;
            }

            if (this.refreshAgain) {
                this.refreshAgain = false;
                this.refresh();
            } else {
                this.schedule();
            }
        },

        keyFor(request) {
            this.keys[request.id] ??= uuid();

            return this.keys[request.id];
        },

        async approve(request, quantity) {
            await this.act(request, request.approveUrl, { quantity, idempotency_key: this.keyFor(request) });
        },

        async reject(request) {
            await this.act(request, request.rejectUrl, {});
        },

        async act(request, url, body) {
            if (this.acting !== null) {
                return;
            }
            this.acting = request.id;
            this.message = '';
            this.error = '';

            try {
                const { data } = await this.send('post', url, body);
                this.version++;
                this.message = data.message;
                this.requests = this.requests.filter((item) => item.id !== request.id);
                delete this.keys[request.id];
                this.updateTitle();
            } catch (error) {
                if (error.handled) {
                    return;
                }
                if (!error.response) {
                    // Fără răspuns: cheia rămâne, o nouă apăsare e un replay sigur.
                    this.error = 'Fără conexiune – încearcă din nou.';
                } else if (error.response.status === 429) {
                    this.error = 'Prea multe încercări. Așteaptă puțin.';
                } else {
                    // Refuzul (cooldown, cerere expirată) lasă cererea în listă: angajatul o poate respinge.
                    // Refuzul e definitiv pentru cheia trimisă: o nouă încercare primește cheie nouă.
                    this.error = error.response.data?.message ?? 'Acțiunea nu a reușit.';
                    delete this.keys[request.id];
                }
            } finally {
                this.acting = null;
            }

            this.refresh();
        },

        /**
         * Prin window.axios (X-Requested-With, token XSRF), ca restul componentelor. Sesiune expirată sau token
         * CSRF vechi: reîncărcăm pagina (ajunge la login), fără buclă de cereri.
         */
        async send(method, url, body = undefined) {
            try {
                return await window.axios.request({ method, url, data: body, headers: { Accept: 'application/json' } });
            } catch (error) {
                if ([401, 419].includes(error.response?.status)) {
                    error.handled = true;
                    window.location.reload();
                }
                throw error;
            }
        },

        age(request) {
            const minutes = Math.max(0, Math.floor((this.now - Date.parse(request.createdAt)) / 60000));

            return minutes === 0 ? 'acum' : `acum ${minutes} min`;
        },

        quantities(request) {
            return Array.from({ length: request.maxStamps }, (_, index) => index + 1);
        },

        updateTitle() {
            this.baseTitle ??= document.title;
            document.title = this.requests.length > 0 ? `(${this.requests.length}) ${this.baseTitle}` : this.baseTitle;
        },
    };
}
