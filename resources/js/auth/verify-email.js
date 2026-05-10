import { domReady } from '../shared/domReady';

domReady(() => {
    const input = document.getElementById('code');
    if (input && input.tagName === 'INPUT') {
        const sanitize = () => {
            input.value = input.value.replace(/\D/g, '').slice(0, 6);
        };
        input.addEventListener('input', sanitize);
        sanitize();
    }

    const wrap = document.getElementById('verify-code-countdown');
    const display = wrap?.querySelector('[data-countdown-display]');
    const iso = wrap?.getAttribute('data-expires-at');
    if (!wrap || !display || !iso) return;

    const end = Date.parse(iso);
    if (Number.isNaN(end)) return;

    const tick = () => {
        const ms = end - Date.now();
        if (ms <= 0) {
            wrap.classList.add('is-expired');
            display.textContent = 'expired';
            return;
        }
        const totalSec = Math.ceil(ms / 1000);
        const m = Math.floor(totalSec / 60);
        const s = totalSec % 60;
        display.textContent = `${m}:${String(s).padStart(2, '0')}`;
        window.setTimeout(tick, 1000);
    };

    tick();
});
