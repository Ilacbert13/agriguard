(() => {
    'use strict';

    const rainEl = document.getElementById('ai-weather-rainfall');
    const windEl = document.getElementById('ai-weather-wind');
    const statusEl = document.getElementById('ai-weather-status');
    const fiveDayEl = document.getElementById('ai-weather-5day');
    const rainChanceTextEl = document.getElementById('ai-weather-rain-chance');
    const rainChanceBarEl = document.getElementById('ai-weather-rain-chance-bar');
    const rainChanceBlockEl = document.getElementById('ai-weather-rain-chance-block');
    const modelAccuracyEl = document.getElementById('ai-model-accuracy');
    const modelConfidenceEl = document.getElementById('ai-model-confidence');
    const modelRainR2El = document.getElementById('ai-model-rain-r2');
    const modelWindR2El = document.getElementById('ai-model-wind-r2');
    const modelDatasetEl = document.getElementById('ai-model-dataset');
    const smartStatusEl = document.getElementById('ai-smart-advisory-status');
    const smartActionEl = document.getElementById('ai-smart-action-text');

    if (!rainEl || !windEl || !statusEl || !fiveDayEl) {
        return;
    }

    const parseJsonScript = (id) => {
        const node = document.getElementById(id);
        if (!node) {
            return {};
        }
        try {
            const parsed = JSON.parse(node.textContent || '{}');
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (_error) {
            return {};
        }
    };

    const config = parseJsonScript('ai-weather-config');
    const predictionUrl = typeof config.prediction_url === 'string' ? config.prediction_url : '';
    if (predictionUrl === '') {
        return;
    }

    const apiTempsByDate = parseJsonScript('ai-api-temps-json');
    const rainConfig = parseJsonScript('ai-weather-rain-json');
    const apiTodayPrecipRaw = rainConfig.api_today_precip_percent;

    const calculateRainChance = (rainfall) => {
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
    };

    const rainChanceTier = (pct) => {
        const p = Math.max(0, Math.min(100, Math.round(Number(pct) || 0)));
        if (p <= 20) {
            return 'low';
        }
        if (p <= 60) {
            return 'moderate';
        }
        return 'high';
    };

    const prefersReducedMotion = () =>
        typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const animateCountUp = (el, targetPct, durationMs) => {
        if (!el) {
            return;
        }
        if (prefersReducedMotion()) {
            el.textContent = `${Math.round(targetPct)}%`;
            return;
        }
        const target = Math.round(targetPct);
        const dur = Math.max(120, durationMs);
        let startTime = null;
        const step = (now) => {
            if (startTime === null) {
                startTime = now;
            }
            const k = Math.min(1, (now - startTime) / dur);
            const eased = 1 - (1 - k) * (1 - k);
            const v = Math.round(target * eased);
            el.textContent = `${v}%`;
            if (k < 1) {
                requestAnimationFrame(step);
            }
        };
        requestAnimationFrame(step);
    };

    const applyRainChanceBlock = (pct, { animate } = { animate: true }) => {
        if (!rainChanceTextEl || !rainChanceBlockEl) {
            return;
        }
        const tier = rainChanceTier(pct);
        rainChanceBlockEl.classList.remove('weather-rain-chance-tier--low', 'weather-rain-chance-tier--moderate', 'weather-rain-chance-tier--high');
        rainChanceBlockEl.classList.add(`weather-rain-chance-tier--${tier}`);
        rainChanceBlockEl.dataset.weatherRainTier = tier;
        if (rainChanceBarEl) {
            rainChanceBarEl.style.setProperty('--weather-rain-pct', String(Math.max(0, Math.min(100, pct))));
        }
        if (animate && !prefersReducedMotion()) {
            animateCountUp(rainChanceTextEl, Math.round(pct), 520);
        } else {
            rainChanceTextEl.textContent = `${Math.round(pct)}%`;
        }
    };

    const todayRainChancePercent = () => {
        if (apiTodayPrecipRaw !== null && apiTodayPrecipRaw !== undefined && Number.isFinite(Number(apiTodayPrecipRaw))) {
            return Math.max(0, Math.min(100, Math.round(Number(apiTodayPrecipRaw))));
        }
        return null;
    };

    const rainfallStatus = (rainfall) => {
        if (rainfall >= 20) {
            return 'Heavy Rain';
        }
        if (rainfall >= 8) {
            return 'Moderate';
        }
        return 'Normal';
    };

    const formatRainfall = (rainfall) => `${Number(rainfall).toFixed(3)} mm`;
    const formatWindSpeed = (windSpeed) => `${Number(windSpeed).toFixed(3)} km/h`;

    const renderModelPerformance = (modelPerformance) => {
        if (!modelAccuracyEl || !modelConfidenceEl || !modelRainR2El || !modelWindR2El || !modelDatasetEl) {
            return;
        }

        const isNumber = (v) => typeof v === 'number' && Number.isFinite(v);
        const accuracy = isNumber(modelPerformance?.overall_accuracy)
            ? `${Number(modelPerformance.overall_accuracy).toFixed(1)}%`
            : 'Not provided';
        const confidence =
            typeof modelPerformance?.confidence === 'string' && modelPerformance.confidence.trim() !== ''
                ? modelPerformance.confidence
                : 'Unknown';
        const rainR2 = isNumber(modelPerformance?.rainfall_r2)
            ? Number(modelPerformance.rainfall_r2).toFixed(3)
            : 'Not provided';
        const windR2 = isNumber(modelPerformance?.wind_r2)
            ? Number(modelPerformance.wind_r2).toFixed(3)
            : 'Not provided';
        const dataset =
            typeof modelPerformance?.dataset === 'string' && modelPerformance.dataset.trim() !== ''
                ? modelPerformance.dataset
                : 'Unknown';

        modelAccuracyEl.innerText = accuracy;
        modelConfidenceEl.innerText = confidence;
        modelRainR2El.innerText = rainR2;
        modelWindR2El.innerText = windR2;
        modelDatasetEl.innerText = dataset;
    };

    const renderFiveDay = (forecast) => {
        fiveDayEl.innerHTML = '';
        forecast.slice(0, 5).forEach((item) => {
            const row = document.createElement('div');
            row.className =
                'ai-forecast-item rounded-2xl border border-violet-100 bg-white/85 px-3 py-2.5 transition duration-200 hover:-translate-y-0.5 hover:shadow-sm';
            row.style.setProperty('--ai-delay', String(item.day - 1));
            const dayLabel = item.date ? `Day ${item.day} (${item.date})` : `Day ${item.day}`;
            const tempLabel = item.date && apiTempsByDate[item.date] ? apiTempsByDate[item.date] : 'Not available';
            const severity = rainfallStatus(Number(item.rainfall));
            const severityClass =
                severity === 'Heavy Rain'
                    ? 'bg-rose-100 text-rose-700 border-rose-200'
                    : severity === 'Moderate'
                      ? 'bg-amber-100 text-amber-700 border-amber-200'
                      : 'bg-emerald-100 text-emerald-700 border-emerald-200';
            const rcPct = calculateRainChance(Number(item.rainfall));
            const rcTier = rainChanceTier(rcPct);
            row.innerHTML = `
                <div class="flex items-start justify-between gap-2">
                    <p class="font-semibold text-slate-800">${dayLabel}</p>
                    <span class="ai-pill inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ${severityClass}">
                        ${severity}
                    </span>
                </div>
                <div class="mt-2 grid grid-cols-1 gap-1.5 sm:grid-cols-3">
                    <p class="ai-pill rounded-xl border border-slate-100 bg-slate-50 px-2 py-1 text-slate-700"><i data-lucide="thermometer" class="mr-1 inline h-3.5 w-3.5 text-rose-500"></i>${tempLabel}</p>
                    <p class="ai-pill rounded-xl border border-slate-100 bg-slate-50 px-2 py-1 text-slate-700"><i data-lucide="cloud-rain" class="mr-1 inline h-3.5 w-3.5 text-blue-500"></i>${formatRainfall(item.rainfall)}</p>
                    <p class="ai-pill rounded-xl border border-slate-100 bg-slate-50 px-2 py-1 text-slate-700"><i data-lucide="wind" class="mr-1 inline h-3.5 w-3.5 text-cyan-600"></i>${formatWindSpeed(item.wind_speed)}</p>
                </div>
                <div class="weather-ai-day-rain-chance mt-2 rounded-xl border border-violet-100/80 bg-violet-50/50 px-2.5 py-2">
                    <p class="flex flex-wrap items-center gap-1.5 text-[11px] font-semibold text-slate-700">
                        <span aria-hidden="true" class="text-sm leading-none">🌧️</span>
                        <span>Rain Chance:</span>
                        <strong class="tabular-nums text-slate-900">${rcPct}%</strong>
                    </p>
                    <div class="weather-rain-chance-bar weather-rain-chance-bar--forecast weather-rain-chance-tier--${rcTier} mt-1.5" style="--weather-rain-pct: ${rcPct}" role="presentation"></div>
                </div>
            `;
            fiveDayEl.appendChild(row);
        });
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    };

    const advisoryFromModel = (rainfall, windSpeed) => {
        const actions = [];

        if (rainfall >= 20) {
            actions.push('Heavy rainfall risk is high. Open drainage channels and postpone spraying today.');
        } else if (rainfall >= 8) {
            actions.push('Moderate rainfall expected. Check field runoff paths and adjust irrigation timing.');
        } else {
            actions.push('Rainfall remains low. Continue regular field activities with normal water scheduling.');
        }

        if (windSpeed >= 25) {
            actions.push('Strong wind is expected. Secure seedlings, netting, and light farm materials.');
        } else if (windSpeed >= 15) {
            actions.push('Breezy conditions are expected. Avoid precision spray windows during peak wind.');
        } else {
            actions.push('Wind speed is generally safe for most routine farm operations.');
        }

        return actions.join(' ');
    };

    const renderSmartAdvisoryFromModel = (rainfall, windSpeed) => {
        if (smartActionEl) {
            smartActionEl.innerText = advisoryFromModel(rainfall, windSpeed);
        }
        if (smartStatusEl) {
            smartStatusEl.className = 'text-emerald-700';
            smartStatusEl.innerText = 'AI Smart Advisory: Synced to ML forecast';
        }
    };

    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 100000);

    const apiPct = todayRainChancePercent();
    if (apiPct !== null && rainChanceTextEl && rainChanceBlockEl) {
        applyRainChanceBlock(apiPct, { animate: false });
    }

    fetch(predictionUrl, {
        signal: controller.signal,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
        .then(async (response) => {
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const parts = [data.error, data.detail].filter(Boolean);
                throw new Error(parts.length ? parts.join(' — ') : `HTTP ${response.status}`);
            }
            return data;
        })
        .then((data) => {
            if (!Array.isArray(data.forecast) || data.forecast.length === 0) {
                throw new Error('Invalid AI payload');
            }

            const today = data.forecast[0];
            const rainfall = Number(today.rainfall);
            const windSpeed = Number(today.wind_speed);
            if (!Number.isFinite(rainfall) || !Number.isFinite(windSpeed)) {
                throw new Error('Non-numeric AI payload');
            }

            rainEl.innerText = formatRainfall(rainfall);
            windEl.innerText = formatWindSpeed(windSpeed);
            statusEl.innerText = rainfallStatus(rainfall);

            const modelRainChance = calculateRainChance(rainfall);
            if (rainChanceTextEl && rainChanceBlockEl) {
                applyRainChanceBlock(modelRainChance, { animate: false });
            }

            renderFiveDay(data.forecast);
            renderModelPerformance(data.model_performance || {});
            renderSmartAdvisoryFromModel(rainfall, windSpeed);
        })
        .catch((err) => {
            rainEl.innerText = 'Not available';
            windEl.innerText = 'Not available';
            statusEl.innerText = 'Unavailable';
            const msg =
                err && err.name === 'AbortError'
                    ? 'Prediction request timed out. If this persists, increase PHP max_execution_time and confirm .venv has ML packages.'
                    : (err && err.message) || 'Prediction request failed. Check laravel.log.';
            fiveDayEl.innerHTML = `<p>${msg}</p>`;
            renderModelPerformance({});
            if (rainChanceTextEl && rainChanceBlockEl) {
                if (apiPct !== null) {
                    applyRainChanceBlock(apiPct, { animate: false });
                } else {
                    rainChanceTextEl.textContent = 'Not available';
                    if (rainChanceBarEl) {
                        rainChanceBarEl.style.setProperty('--weather-rain-pct', '0');
                    }
                }
            }
            if (smartStatusEl) {
                smartStatusEl.className = 'text-rose-700';
                smartStatusEl.innerText = 'AI Smart Advisory: Unavailable (ML feed error)';
            }
            if (smartActionEl) {
                smartActionEl.innerText = 'Smart advisory is waiting for a valid ML forecast payload.';
            }
        })
        .finally(() => {
            clearTimeout(timeout);
        });
})();
