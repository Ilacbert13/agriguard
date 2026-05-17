/**
 * Canonical display rain chance (%): matches Weather page + App\Support\RainChance.
 * Publishes updates so Assistant (and other pages) stay in sync in real time.
 */

export const DISPLAY_RAIN_CHANCE_STORAGE_KEY = 'agriguard:weather-display-rain-chance';

const channel =
    typeof BroadcastChannel !== 'undefined' ? new BroadcastChannel('agriguard-display-rain-chance') : null;

/** @param {number} rainfall mm */
export function calculateRainChanceFromRainfall(rainfall) {
    const r = Number(rainfall);
    if (!Number.isFinite(r) || r <= 0) {
        return 5;
    }
    if (r <= 0.1) {
        const scaled = 5 + Math.sqrt(r / 0.1) * 9;

        return Math.max(5, Math.min(14, Math.round(scaled)));
    }
    if (r <= 0.5) {
        return 15;
    }
    if (r <= 2) {
        return 40;
    }
    if (r <= 10) {
        return 70;
    }
    return 90;
}

/** @returns {{ percent: number, updatedAt: number, source?: string } | null} */
export function readCachedDisplayRainChance() {
    try {
        const raw = localStorage.getItem(DISPLAY_RAIN_CHANCE_STORAGE_KEY);
        if (!raw) {
            return null;
        }
        const parsed = JSON.parse(raw);
        const percent = Number(parsed?.percent);
        if (!Number.isFinite(percent)) {
            return null;
        }
        return {
            percent: Math.max(0, Math.min(100, Math.round(percent))),
            updatedAt: Number(parsed.updatedAt) || 0,
            source: typeof parsed.source === 'string' ? parsed.source : undefined,
        };
    } catch (_) {
        return null;
    }
}

/**
 * @param {number} percent
 * @param {{ source?: string }} [meta]
 */
export function publishDisplayRainChance(percent, meta = {}) {
    const pct = Math.max(0, Math.min(100, Math.round(Number(percent) || 0)));
    const payload = {
        percent: pct,
        updatedAt: Date.now(),
        source: meta.source || 'model',
    };
    try {
        localStorage.setItem(DISPLAY_RAIN_CHANCE_STORAGE_KEY, JSON.stringify(payload));
    } catch (_) {
        /* quota / private mode */
    }
    if (channel) {
        channel.postMessage(payload);
    }
    return pct;
}

/**
 * @param {(payload: { percent: number, updatedAt: number, source?: string }) => void} callback
 * @returns {() => void}
 */
export function subscribeDisplayRainChance(callback) {
    const handler = (payload) => {
        const percent = Number(payload?.percent);
        if (!Number.isFinite(percent)) {
            return;
        }
        callback({
            percent: Math.max(0, Math.min(100, Math.round(percent))),
            updatedAt: Number(payload.updatedAt) || Date.now(),
            source: typeof payload.source === 'string' ? payload.source : undefined,
        });
    };

    const onStorage = (event) => {
        if (event.key !== DISPLAY_RAIN_CHANCE_STORAGE_KEY || !event.newValue) {
            return;
        }
        try {
            handler(JSON.parse(event.newValue));
        } catch (_) {
            /* ignore */
        }
    };

    window.addEventListener('storage', onStorage);

    const onChannelMessage = (event) => handler(event.data);
    if (channel) {
        channel.addEventListener('message', onChannelMessage);
    }

    return () => {
        window.removeEventListener('storage', onStorage);
        if (channel) {
            channel.removeEventListener('message', onChannelMessage);
        }
    };
}

/**
 * Same resolution order as the Weather page: ML today rainfall, else API fallback.
 *
 * @param {{ forecast?: Array<{ rainfall?: number }> }} prediction
 * @param {number | null | undefined} apiFallbackPercent
 * @returns {number | null}
 */
export function resolveDisplayRainChancePercent(prediction, apiFallbackPercent) {
    const forecast = Array.isArray(prediction?.forecast) ? prediction.forecast : [];
    const today = forecast[0];
    const rainfall = today && Number.isFinite(Number(today.rainfall)) ? Number(today.rainfall) : null;
    if (rainfall !== null) {
        return calculateRainChanceFromRainfall(rainfall);
    }
    if (apiFallbackPercent !== null && apiFallbackPercent !== undefined && Number.isFinite(Number(apiFallbackPercent))) {
        return Math.max(0, Math.min(100, Math.round(Number(apiFallbackPercent))));
    }
    return null;
}
