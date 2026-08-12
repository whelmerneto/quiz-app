import Alpine from 'alpinejs';

/**
 * The round.
 *
 * What this component is allowed to know is the whole point of the page. It
 * holds the position awaiting an answer, and — for the length of one reveal —
 * the truth of the position it has just answered, which the server hands back
 * in `correct_label`. It never holds, requests or derives the label of an
 * image the player has not answered, and it keeps no tally: a running score on
 * this page would be an aggregate over labels, and the round's guarantee is
 * that this page knows nothing label-derived.
 *
 * The urls and the display dictionary come from data attributes on the root
 * element, so the markup stays identical for every position.
 */
Alpine.data('quizRound', ({ total, current }) => ({
    total,

    /** Position awaiting an answer. 0 once every position is spent. */
    current,

    /** True while a request is in flight or a reveal is on screen. */
    busy: false,

    /** Drives the lens flex on the hero card. */
    flexing: false,

    /** { correct: boolean, truth: string } for the position just answered. */
    verdict: null,

    /** { text: string, href: string|null } shown when a request is refused. */
    notice: null,

    /** The control focus was on when the answer was sent, to give it back. */
    returnFocusTo: null,

    answerUrl: '',
    resultUrl: '',
    landingUrl: '',
    token: '',
    labels: {},
    values: [],
    calm: false,

    init() {
        const root = this.$root.dataset;

        this.answerUrl = root.answerUrl;
        this.resultUrl = root.resultUrl;
        this.landingUrl = root.landingUrl;
        this.labels = JSON.parse(root.labels);
        this.values = Object.keys(this.labels);
        this.token = document.querySelector('meta[name="csrf-token"]').content;

        // Reduced motion removes movement, not time: the reveal still has to
        // stay on screen long enough to read.
        this.calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    },

    /** Two-digit counter, so the number does not jump width at position 10. */
    get counter() {
        return String(this.current === 0 ? this.total : this.current).padStart(2, '0');
    },

    /** Live-region sentence. Announces progress, then the verdict. */
    get announcement() {
        if (this.verdict !== null) {
            return `${this.verdict.correct ? 'Acertou' : 'Errou'}. Era ${this.verdict.truth}.`;
        }

        if (this.current === 0) {
            return 'Rodada concluída.';
        }

        return `Imagem ${this.current} de ${this.total}.`;
    },

    /** Spent, awaiting an answer, or not yet reached. No fourth state. */
    segment(position) {
        return {
            'is-done': this.current === 0 || position < this.current,
            'is-current': position === this.current,
        };
    },

    onKey(event) {
        if (event.metaKey || event.ctrlKey || event.altKey) {
            return;
        }

        const index = ['1', '2'].indexOf(event.key);

        if (index === -1 || this.values[index] === undefined) {
            return;
        }

        event.preventDefault();

        this.answer(this.values[index]);
    },

    async answer(value) {
        if (this.busy || this.current === 0) {
            return;
        }

        // Disabling the button below takes focus with it, and the browser drops
        // it on <body> rather than returning it. Remember where it was so the
        // round stays playable with the keyboard past the first answer.
        this.returnFocusTo = document.activeElement instanceof HTMLElement
            && document.activeElement !== document.body
            ? document.activeElement
            : null;

        this.busy = true;
        this.notice = null;

        let response;

        try {
            response = await fetch(this.answerUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.token,
                },
                body: JSON.stringify({ position: this.current, answer: value }),
            });
        } catch {
            this.busy = false;
            this.notice = { text: 'Não foi possível falar com o servidor. Tente responder de novo.', href: null };

            return;
        }

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            this.refuse(response.status, payload);

            return;
        }

        const data = payload.data;

        this.verdict = {
            correct: data.correct,
            truth: this.labels[data.correct_label] ?? '',
        };

        this.flexing = true;
        await this.hold(this.calm ? 60 : 460);
        this.flexing = false;

        await this.hold(1100);

        if (data.is_last) {
            // Stays busy: the page is leaving.
            window.location.assign(this.resultUrl);

            return;
        }

        this.verdict = null;
        this.current = data.position + 1;
        this.busy = false;
        this.restoreFocus();
    },

    /** Puts focus back where the disabled button took it from. */
    restoreFocus() {
        const target = this.returnFocusTo;

        this.returnFocusTo = null;

        if (target === null || ! target.isConnected) {
            return;
        }

        // After the tick, so the button is enabled again before it is focused.
        this.$nextTick(() => target.focus());
    },

    refuse(status, payload) {
        this.busy = false;
        this.restoreFocus();

        // The round is already finished — the result page is where this belongs.
        if (status === 409) {
            window.location.assign(this.resultUrl);

            return;
        }

        // The session does not own this round. Nothing to retry.
        if (status === 403) {
            this.notice = {
                text: 'Esta rodada foi aberta em outro navegador. Comece uma rodada sua.',
                href: this.landingUrl,
            };

            return;
        }

        // The server and this page disagree about which position is next.
        // Reloading takes the server's answer, which is the authoritative one.
        if (status === 422) {
            window.location.reload();

            return;
        }

        if (status === 429) {
            this.notice = { text: 'Muitas respostas em pouco tempo. Espere alguns segundos.', href: null };

            return;
        }

        this.notice = {
            text: payload.message ?? 'Não foi possível registrar a resposta.',
            href: null,
        };
    },

    hold(ms) {
        return new Promise((resolve) => window.setTimeout(resolve, ms));
    },
}));

window.Alpine = Alpine;

Alpine.start();
