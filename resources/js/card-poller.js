/**
 * Cardul clientului se actualizează singur (SDD §5.3, ADR 012): întreabă serverul de starea cardului
 * (des cât o cerere așteaptă, rar altfel) și reîncarcă pagina când s-a schimbat ceva – o ștampilă de la
 * scanare sau de la o cerere aprobată, o cerere respinsă ori expirată. Cât tab-ul e ascuns nu întreabă.
 */
export default function cardPoller(url, fingerprint, pollSeconds = 5, pending = false) {
    return {
        timer: null,
        busy: false,

        init() {
            this.schedule();

            this.onVisibility = () => {
                if (document.hidden) {
                    clearTimeout(this.timer);
                } else {
                    this.check();
                }
            };
            document.addEventListener('visibilitychange', this.onVisibility);
        },

        destroy() {
            clearTimeout(this.timer);
            document.removeEventListener('visibilitychange', this.onVisibility);
        },

        schedule() {
            clearTimeout(this.timer);
            if (!document.hidden) {
                this.timer = setTimeout(() => this.check(), (pending ? pollSeconds : pollSeconds * 3) * 1000);
            }
        },

        async check() {
            if (this.busy) {
                return;
            }
            this.busy = true;

            // window.axios trimite X-Requested-With: Laravel nu salvează endpointul JSON ca „URL anterior” al sesiunii.
            try {
                const { data } = await window.axios.get(url, { headers: { Accept: 'application/json' } });
                const current = [data.stamps, data.rewards, data.request?.id ?? '', data.request?.status ?? ''].join('|');

                if (current !== fingerprint) {
                    window.location.reload();
                    return;
                }
            } catch (error) {
                // Sesiune expirată sau card inaccesibil: pagina duce la login. Altă eroare (429, offline): reîncercăm mai târziu.
                if ([401, 404].includes(error.response?.status)) {
                    window.location.reload();
                    return;
                }
            } finally {
                this.busy = false;
            }

            this.schedule();
        },
    };
}
