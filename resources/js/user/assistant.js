import {
    readCachedDisplayRainChance,
    resolveDisplayRainChancePercent,
    subscribeDisplayRainChance,
} from '../shared/displayRainChance.js';

(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') fn();
        else document.addEventListener('DOMContentLoaded', fn);
    }

    function parseJson(raw, fallback) {
        try {
            return JSON.parse(raw || '');
        } catch (_) {
            return fallback;
        }
    }

    /** Laravel XSRF-TOKEN cookie (HttpOnly=false); send as X-XSRF-TOKEN so CSRF matches the session cookie. */
    function readXsrfCookie() {
        var prefix = 'XSRF-TOKEN=';
        var chunks = document.cookie.split(';');
        for (var i = 0; i < chunks.length; i += 1) {
            var c = chunks[i].trim();
            if (c.indexOf(prefix) !== 0) continue;
            var raw = c.slice(prefix.length);
            try {
                return decodeURIComponent(raw);
            } catch (_) {
                return raw;
            }
        }
        return '';
    }

    function csrfFetchHeaders(extra) {
        var h = Object.assign(
            {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            extra || {}
        );
        var meta = document.querySelector('meta[name="csrf-token"]');
        var metaToken = meta && meta.getAttribute('content');
        var xsrf = readXsrfCookie();
        if (xsrf) {
            h['X-XSRF-TOKEN'] = xsrf;
        } else if (metaToken) {
            h['X-CSRF-TOKEN'] = metaToken;
        }
        return h;
    }

    function splitParagraphs(text) {
        return String(text || '')
            .split(/\n{2,}/)
            .map(function (part) { return part.trim(); })
            .filter(Boolean);
    }

    function extractGuideSteps(text) {
        var rows = String(text || '')
            .split('\n')
            .map(function (line) { return line.trim(); })
            .filter(Boolean);

        if (rows.length < 4) return [];
        var stepPattern = /^(\d+[\).\s-]|[-*•]\s+|(?:step)\s*\d*[:.\-\s]*)/i;
        var steps = rows.filter(function (line) { return stepPattern.test(line); }).map(function (line) {
            return line.replace(stepPattern, '').trim();
        });

        return steps.length >= 3 ? steps : [];
    }

    ready(function () {
        var root = document.getElementById('assistant-page');
        if (!root) return;

        var chatUrl = root.dataset.chatUrl || '';
        var clearUrl = root.dataset.clearUrl || '';
        var context = parseJson(root.dataset.context, {});
        var history = parseJson(root.dataset.history, []);

        var rainChanceEl = document.getElementById('assistant-rain-chance-value');
        var apiRainFallback = root.dataset.apiRainFallback;
        var apiRainFallbackNum =
            apiRainFallback !== undefined && apiRainFallback !== '' && Number.isFinite(Number(apiRainFallback))
                ? Math.max(0, Math.min(100, Math.round(Number(apiRainFallback))))
                : null;
        var predictionUrl = root.dataset.predictionUrl || '';

        function applyAssistantRainChance(percent) {
            if (!rainChanceEl || percent === null || percent === undefined || !Number.isFinite(Number(percent))) {
                return;
            }
            var pct = Math.max(0, Math.min(100, Math.round(Number(percent))));
            rainChanceEl.textContent = pct + '%';
            context.rainfall_probability = pct;
            if (context.risk_snapshot && typeof context.risk_snapshot === 'object') {
                context.risk_snapshot.rain_chance_display = pct + '%';
            }
            var snapshotEl = document.getElementById('assistant-snapshot');
            if (snapshotEl) {
                snapshotEl.textContent =
                    "Today's snapshot: " +
                    (pct < 50
                        ? 'Good conditions for field work today.'
                        : 'Stay alert for rain and choose lower-risk field tasks.');
            }
        }

        var cachedRain = readCachedDisplayRainChance();
        if (cachedRain) {
            applyAssistantRainChance(cachedRain.percent);
        }

        subscribeDisplayRainChance(function (payload) {
            applyAssistantRainChance(payload.percent);
        });

        if (predictionUrl) {
            fetch(predictionUrl, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then(function (r) {
                    return r.json().then(function (data) {
                        return { ok: r.ok, data: data };
                    });
                })
                .then(function (wrapped) {
                    if (!wrapped || !wrapped.ok) return;
                    var pct = resolveDisplayRainChancePercent(wrapped.data, apiRainFallbackNum);
                    if (pct !== null) {
                        applyAssistantRainChance(pct);
                    }
                })
                .catch(function () {
                    if (apiRainFallbackNum !== null) {
                        applyAssistantRainChance(apiRainFallbackNum);
                    }
                });
        }

        var canChat = !!(context && context.has_gps);

        var formEl = document.getElementById('assistant-form');
        var inputEl = document.getElementById('assistant-input');
        var sendBtn = document.getElementById('assistant-send-btn');
        var clearBtn = document.getElementById('assistant-clear-btn');
        var messagesEl = document.getElementById('assistant-messages');
        var typingEl = document.getElementById('assistant-typing');
        var fallbackBadge = document.getElementById('assistant-fallback-badge');
        var quickButtons = Array.prototype.slice.call(document.querySelectorAll('.assistant-quick-chip'));

        var state = { isLoading: false, messages: [], requestController: null };

        function normalizePayload(payload) {
            var p = payload || {};
            return {
                message: String(p.message || p.direct_answer || '').trim(),
                meta: p.meta || {},
            };
        }

        function createTextContainer(text) {
            var wrap = document.createElement('div');
            wrap.className = 'assistant-message-text';
            var paragraphs = splitParagraphs(text);

            if (paragraphs.length <= 1) {
                var p = document.createElement('p');
                p.textContent = text;
                wrap.appendChild(p);
                return wrap;
            }

            paragraphs.forEach(function (part) {
                var p = document.createElement('p');
                p.textContent = part;
                wrap.appendChild(p);
            });
            return wrap;
        }

        function createGuideCard(steps) {
            var card = document.createElement('div');
            card.className = 'assistant-guide-card';

            var title = document.createElement('p');
            title.className = 'assistant-guide-title';
            title.textContent = '🌱 Planting Guide Today';
            card.appendChild(title);

            var list = document.createElement('div');
            list.className = 'assistant-guide-steps';
            steps.forEach(function (step, index) {
                var row = document.createElement('div');
                row.className = 'assistant-guide-step';

                var idx = document.createElement('span');
                idx.className = 'assistant-guide-index';
                idx.textContent = String(index + 1);

                var text = document.createElement('span');
                text.textContent = step;

                row.appendChild(idx);
                row.appendChild(text);
                list.appendChild(row);
            });

            card.appendChild(list);
            return card;
        }

        function messageBubble(role, text) {
            var row = document.createElement('div');
            row.className = role === 'user' ? 'assistant-row assistant-row--user' : 'assistant-row assistant-row--assistant';

            var wrap = document.createElement('div');
            wrap.className = 'assistant-bubble-wrap';

            if (role === 'assistant') {
                var avatar = document.createElement('span');
                avatar.className = 'assistant-avatar';
                avatar.textContent = '🌾';
                wrap.appendChild(avatar);
            }

            var bubble = document.createElement('div');
            bubble.className = role === 'user' ? 'assistant-bubble assistant-bubble--user' : 'assistant-bubble assistant-bubble--assistant';

            bubble.appendChild(createTextContainer(text));

            if (role === 'assistant') {
                var steps = extractGuideSteps(text);
                if (steps.length) bubble.appendChild(createGuideCard(steps));
            }

            if (role === 'user') {
                var userAvatar = document.createElement('span');
                userAvatar.className = 'assistant-avatar assistant-avatar--user';
                userAvatar.textContent = '👨';
                wrap.appendChild(bubble);
                wrap.appendChild(userAvatar);
            } else {
                wrap.appendChild(bubble);
            }

            row.appendChild(wrap);
            return row;
        }

        function render() {
            messagesEl.innerHTML = '';
            state.messages.forEach(function (m) {
                if (m.role === 'user') {
                    messagesEl.appendChild(messageBubble('user', String(m.text || '')));
                } else {
                    var data = normalizePayload(m.payload || {});
                    messagesEl.appendChild(messageBubble('assistant', data.message || 'I can help with farming, weather, and preparedness questions.'));
                }
            });
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }

        function setLoading(flag) {
            state.isLoading = !!flag;
            if (typingEl) typingEl.classList.toggle('hidden', !state.isLoading);
            if (inputEl) inputEl.disabled = !canChat || state.isLoading;
            if (sendBtn) sendBtn.disabled = !canChat || state.isLoading;
            quickButtons.forEach(function (btn) { btn.disabled = !canChat || state.isLoading; });
        }

        function setFallbackBadge(payload) {
            if (!fallbackBadge) return;
            var data = normalizePayload(payload);
            var on = !!(data.meta && data.meta.fallback_mode);
            fallbackBadge.classList.toggle('hidden', !on);
            fallbackBadge.textContent = on && data.meta.fallback_note ? String(data.meta.fallback_note) : 'Using basic farm guidance';
        }

        function resizeInput() {
            if (!inputEl) return;
            inputEl.style.height = 'auto';
            inputEl.style.height = Math.min(inputEl.scrollHeight, 110) + 'px';
        }

        function sendMessage(text) {
            var message = String(text || '').trim();
            if (!message || !canChat || !chatUrl || state.isLoading) return;

            if (state.requestController) state.requestController.abort();
            state.requestController = new AbortController();

            state.messages.push({ role: 'user', text: message });
            render();
            setLoading(true);

            fetch(chatUrl, {
                method: 'POST',
                signal: state.requestController.signal,
                credentials: 'same-origin',
                headers: csrfFetchHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ message: message }),
            })
                .then(function (r) {
                    if (r.status === 419) {
                        window.location.reload();
                        return Promise.reject(new Error('csrf'));
                    }
                    return r.text().then(function (text) {
                        return { ok: r.ok, status: r.status, text: text };
                    });
                })
                .then(function (wrapped) {
                    if (!wrapped || wrapped instanceof Response) return;
                    var data = parseJson(wrapped.text, {});
                    if (!wrapped.ok) {
                        var rawMsg = (data && data.message) ? String(data.message) : '';
                        var isCsrfMsg = /csrf|token mismatch|page expired/i.test(rawMsg);
                        var userMsg = isCsrfMsg
                            ? 'Your session was refreshed. Please send your message again.'
                            : rawMsg || 'Something went wrong. Please try again in a moment.';
                        if (wrapped.status === 419 || isCsrfMsg) {
                            window.location.reload();
                            return;
                        }
                        state.messages.push({
                            role: 'assistant',
                            payload: {
                                message: userMsg,
                                meta: { fallback_mode: true, fallback_note: 'Using basic farm guidance' },
                            },
                        });
                        setFallbackBadge(state.messages[state.messages.length - 1].payload);
                        render();
                        return;
                    }
                    if (!data || data.ok !== true) {
                        var failText = (data && data.message) ? String(data.message) : '';
                        state.messages.push({
                            role: 'assistant',
                            payload: {
                                message: failText || 'I cannot answer right now, but please check your field and drainage first while weather is uncertain.',
                                meta: { fallback_mode: true, fallback_note: 'Using basic farm guidance' },
                            },
                        });
                        setFallbackBadge(state.messages[state.messages.length - 1].payload);
                        render();
                        return;
                    }

                    state.messages.push({ role: 'assistant', payload: data });
                    setFallbackBadge(data);
                    render();
                })
                .catch(function (error) {
                    if (error && error.name === 'AbortError') return;
                    if (error && error.message === 'csrf') return;
                    state.messages.push({
                        role: 'assistant',
                        payload: {
                            message: 'I hit a connection issue. For now, start with a quick field check, inspect drainage, and hold risky operations if rain looks likely.',
                            meta: { fallback_mode: true, fallback_note: 'Using basic farm guidance' },
                        },
                    });
                    setFallbackBadge(state.messages[state.messages.length - 1].payload);
                    render();
                })
                .finally(function () {
                    state.requestController = null;
                    setLoading(false);
                });
        }

        function hydrate() {
            var rows = Array.isArray(history) ? history : [];
            state.messages = rows
                .filter(function (row) { return row && typeof row === 'object'; })
                .map(function (row) {
                    if (row.role === 'user') {
                        return {
                            role: 'user',
                            text: String(row.text || row.content || ''),
                        };
                    }
                    if (row.role === 'assistant') {
                        return { role: 'assistant', payload: row.payload || {} };
                    }
                    return null;
                })
                .filter(Boolean);
        }

        if (formEl) {
            formEl.addEventListener('submit', function (e) {
                e.preventDefault();
                var text = inputEl ? inputEl.value : '';
                sendMessage(text);
                if (inputEl) {
                    inputEl.value = '';
                    resizeInput();
                }
            });
        }

        if (inputEl) {
            inputEl.addEventListener('input', resizeInput);
            inputEl.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    if (formEl) formEl.dispatchEvent(new Event('submit', { cancelable: true }));
                }
            });
        }

        quickButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!inputEl || state.isLoading || !canChat) return;
                var prompt = btn.getAttribute('data-assistant-prompt') || '';
                inputEl.value = prompt;
                resizeInput();
                sendMessage(prompt);
                inputEl.value = '';
                resizeInput();
            });
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                if (!clearUrl || state.isLoading) return;
                fetch(clearUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: csrfFetchHeaders({}),
                })
                    .then(function (r) {
                        if (r.status === 419) {
                            window.location.reload();
                            return Promise.reject(new Error('csrf'));
                        }
                        return r.json();
                    })
                    .then(function (data) {
                        if (!data) return;
                        history = data && data.ok && Array.isArray(data.history) ? data.history : [];
                        hydrate();
                        render();
                        setFallbackBadge({});
                    })
                    .catch(function () {});
            });
        }

        hydrate();
        render();
        setFallbackBadge(state.messages[state.messages.length - 1] ? state.messages[state.messages.length - 1].payload : {});
        setLoading(false);
        resizeInput();
        if (typeof window.lucide !== 'undefined') window.lucide.createIcons();
    });
})();
