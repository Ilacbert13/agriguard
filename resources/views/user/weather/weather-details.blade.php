@php
    $currentDate = now();
    $currentDateIso = $currentDate->toDateString();
    $currentDateDisplay = $currentDate->format('l, F j, Y');

    $headerLocation = $farm_location_display ?: 'Barangay Calamagui, Amulung, Cagayan';

    $clayDataUri = static function (string $svg): string {
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    };

    $clay = [
        'sun' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><radialGradient id="cs" cx="32%" cy="28%" r="70%"><stop offset="0%" stop-color="#FFFBEB"/><stop offset="45%" stop-color="#FDE047"/><stop offset="100%" stop-color="#EAB308"/></radialGradient><filter id="ds"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".18"/></filter></defs><circle cx="24" cy="26" r="13" fill="url(#cs)" filter="url(#ds)"/><ellipse cx="19" cy="21" rx="5" ry="3" fill="#fff" opacity=".45"/></svg>',
        'partly_cloudy' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><radialGradient id="cs2" cx="30%" cy="25%" r="65%"><stop offset="0%" stop-color="#FEF9C3"/><stop offset="100%" stop-color="#FACC15"/></radialGradient><linearGradient id="cg" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#BAE6FD"/><stop offset="100%" stop-color="#0EA5E9"/></linearGradient><filter id="ds2"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0369a1" flood-opacity=".22"/></filter></defs><circle cx="14" cy="16" r="8" fill="url(#cs2)" filter="url(#ds2)"/><ellipse cx="18" cy="14" rx="3" ry="2" fill="#fff" opacity=".5"/><ellipse cx="26" cy="30" rx="14" ry="10" fill="url(#cg)" filter="url(#ds2)"/><ellipse cx="20" cy="28" rx="9" ry="7" fill="#7DD3FC"/><ellipse cx="32" cy="29" rx="8" ry="6" fill="#0284C7"/></svg>',
        'cloud' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="clg" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#BAE6FD"/><stop offset="100%" stop-color="#0284C7"/></linearGradient><filter id="dsc"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0369a1" flood-opacity=".22"/></filter></defs><ellipse cx="24" cy="28" rx="16" ry="11" fill="url(#clg)" filter="url(#dsc)"/><ellipse cx="16" cy="26" rx="10" ry="8" fill="#7DD3FC"/><ellipse cx="32" cy="26" rx="9" ry="7" fill="#0369A1"/></svg>',
        'overcast' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="ov" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#E2E8F0"/><stop offset="100%" stop-color="#94A3B8"/></linearGradient><filter id="dso"><feDropShadow dx="0" dy="2" stdDeviation="2.5" flood-color="#0f172a" flood-opacity=".2"/></filter></defs><ellipse cx="24" cy="28" rx="17" ry="12" fill="url(#ov)" filter="url(#dso)"/><ellipse cx="15" cy="26" rx="11" ry="9" fill="#CBD5E1"/><ellipse cx="34" cy="27" rx="10" ry="8" fill="#94A3B8"/></svg>',
        'rain' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="rc" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#F1F5F9"/><stop offset="100%" stop-color="#94A3B8"/></linearGradient><linearGradient id="rd" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#7DD3FC"/><stop offset="100%" stop-color="#38BDF8"/></linearGradient><filter id="dsr"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".16"/></filter></defs><ellipse cx="24" cy="22" rx="15" ry="10" fill="url(#rc)" filter="url(#dsr)"/><ellipse cx="17" cy="21" rx="9" ry="7" fill="#E2E8F0"/><path d="M16 34v8M24 32v9M32 34v7" stroke="url(#rd)" stroke-width="3.5" stroke-linecap="round" filter="url(#dsr)"/></svg>',
        'storm' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="stc" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#E2E8F0"/><stop offset="100%" stop-color="#64748B"/></linearGradient><filter id="dst"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".18"/></filter></defs><ellipse cx="24" cy="22" rx="15" ry="10" fill="url(#stc)" filter="url(#dst)"/><path d="M22 28 L18 36h6l-3 8 10-12h-7l4-4z" fill="#FDE047" stroke="#EAB308" stroke-width="0.5" filter="url(#dst)"/></svg>',
        'snow' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="sn" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#F8FAFC"/><stop offset="100%" stop-color="#CBD5E1"/></linearGradient><filter id="dss"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".14"/></filter></defs><ellipse cx="24" cy="22" rx="15" ry="10" fill="url(#sn)" filter="url(#dss)"/><circle cx="18" cy="34" r="2.2" fill="#E0F2FE"/><circle cx="24" cy="36" r="2" fill="#BAE6FD"/><circle cx="30" cy="33" r="2.2" fill="#E0F2FE"/></svg>',
        'wind' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="wg" x1="0" y1="0" x2="1" y2="0"><stop offset="0%" stop-color="#DDD6FE"/><stop offset="100%" stop-color="#A78BFA"/></linearGradient><filter id="dsw"><feDropShadow dx="0" dy="2" stdDeviation="1.5" flood-color="#0f172a" flood-opacity=".15"/></filter></defs><path d="M8 20 Q22 14 38 20 Q22 26 8 20" fill="none" stroke="url(#wg)" stroke-width="5" stroke-linecap="round" filter="url(#dsw)"/><path d="M10 30 Q24 24 40 30 Q24 36 10 30" fill="none" stroke="url(#wg)" stroke-width="4" stroke-linecap="round" opacity=".85" filter="url(#dsw)"/></svg>',
        'droplet' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><radialGradient id="dg" cx="35%" cy="25%" r="65%"><stop offset="0%" stop-color="#A7F3D0"/><stop offset="100%" stop-color="#34D399"/></radialGradient><filter id="dsd"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".16"/></filter></defs><path d="M24 10 C16 22 12 28 12 32a12 12 0 0 0 24 0c0-4-4-10-12-22z" fill="url(#dg)" filter="url(#dsd)"/><ellipse cx="20" cy="30" rx="4" ry="5" fill="#fff" opacity=".35"/></svg>',
        'pin' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><radialGradient id="pg" cx="35%" cy="20%" r="70%"><stop offset="0%" stop-color="#FDE68A"/><stop offset="100%" stop-color="#D97706"/></radialGradient><filter id="dsp"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".18"/></filter></defs><path d="M24 8c-6 0-10 4.5-10 10 0 8 10 22 10 22s10-14 10-22c0-5.5-4-10-10-10z" fill="url(#pg)" filter="url(#dsp)"/><circle cx="24" cy="18" r="4" fill="#fff" opacity=".5"/></svg>',
        'sprout' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="sg" x1="0" y1="1" x2="1" y2="0"><stop offset="0%" stop-color="#86EFAC"/><stop offset="100%" stop-color="#22C55E"/></linearGradient><filter id="dss2"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".14"/></filter></defs><path d="M24 38v-8" stroke="#A3E635" stroke-width="4" stroke-linecap="round" filter="url(#dss2)"/><path d="M24 30 Q14 22 18 12 Q24 18 24 30" fill="url(#sg)" filter="url(#dss2)"/><path d="M24 30 Q34 22 30 12 Q24 18 24 30" fill="url(#sg)" opacity=".9" filter="url(#dss2)"/></svg>',
        'gauge' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="gg" x1="0" y1="1" x2="1" y2="0"><stop offset="0%" stop-color="#FEF3C7"/><stop offset="100%" stop-color="#F59E0B"/></linearGradient><filter id="dsg"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".14"/></filter></defs><path d="M10 32 A16 16 0 0 1 38 32" fill="none" stroke="#E2E8F0" stroke-width="5" stroke-linecap="round"/><path d="M10 32 A16 16 0 0 1 28 18" fill="none" stroke="url(#gg)" stroke-width="5" stroke-linecap="round" filter="url(#dsg)"/><circle cx="24" cy="32" r="3" fill="#F59E0B" filter="url(#dsg)"/></svg>',
        'brain' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><radialGradient id="bg" cx="30%" cy="30%" r="65%"><stop offset="0%" stop-color="#EDE9FE"/><stop offset="100%" stop-color="#A78BFA"/></radialGradient><filter id="dsb"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".15"/></filter></defs><path d="M18 14c-4 0-7 3-7 7 0 2 1 4 2 5-1 1-2 3-2 5 0 5 4 9 9 9h10c5 0 9-4 9-9 0-2-1-4-2-5 1-1 2-3 2-5 0-4-3-7-7-7-1-3-4-5-8-5s-7 2-8 5z" fill="url(#bg)" filter="url(#dsb)"/></svg>',
        'alert' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="ag" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#FDE68A"/><stop offset="100%" stop-color="#F59E0B"/></linearGradient><filter id="dsa"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".16"/></filter></defs><path d="M24 8 L40 38H8L24 8z" fill="url(#ag)" filter="url(#dsa)"/><path d="M24 16v12M24 32v2" stroke="#92400E" stroke-width="2.5" stroke-linecap="round"/></svg>',
        'calendar' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="cal" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#FDE68A"/><stop offset="100%" stop-color="#FBBF24"/></linearGradient><filter id="dscal"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".14"/></filter></defs><rect x="10" y="14" width="28" height="26" rx="4" fill="#F8FAFC" stroke="#E2E8F0" stroke-width="2" filter="url(#dscal)"/><rect x="10" y="14" width="28" height="9" rx="4" fill="url(#cal)"/><path d="M16 10v8M32 10v8" stroke="#D97706" stroke-width="3" stroke-linecap="round"/></svg>',
        'grid' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><filter id="dsgr"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".12"/></filter></defs><rect x="8" y="8" width="14" height="14" rx="4" fill="#E0E7FF" filter="url(#dsgr)"/><rect x="26" y="8" width="14" height="14" rx="4" fill="#FEF3C7" filter="url(#dsgr)"/><rect x="8" y="26" width="14" height="14" rx="4" fill="#D1FAE5" filter="url(#dsgr)"/><rect x="26" y="26" width="14" height="14" rx="4" fill="#EDE9FE" filter="url(#dsgr)"/></svg>',
        'chart' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><filter id="dsch"><feDropShadow dx="0" dy="2" stdDeviation="1.5" flood-color="#0f172a" flood-opacity=".12"/></filter></defs><rect x="8" y="28" width="8" height="12" rx="2" fill="#86EFAC" filter="url(#dsch)"/><rect x="20" y="18" width="8" height="22" rx="2" fill="#A78BFA" filter="url(#dsch)"/><rect x="32" y="22" width="8" height="18" rx="2" fill="#FCD34D" filter="url(#dsch)"/></svg>',
        'dashboard' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><filter id="dsdb"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".12"/></filter></defs><rect x="8" y="8" width="30" height="14" rx="4" fill="#C4B5FD" filter="url(#dsdb)"/><rect x="8" y="26" width="13" height="14" rx="4" fill="#FDE68A" filter="url(#dsdb)"/><rect x="25" y="26" width="13" height="14" rx="4" fill="#A7F3D0" filter="url(#dsdb)"/></svg>',
        'thermo' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="tg" x1="0" y1="1" x2="0" y2="0"><stop offset="0%" stop-color="#FCA5A5"/><stop offset="100%" stop-color="#FEF08A"/></linearGradient><filter id="dsth"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".14"/></filter></defs><rect x="20" y="10" width="8" height="22" rx="4" fill="#F1F5F9" filter="url(#dsth)"/><circle cx="24" cy="34" r="8" fill="url(#tg)" filter="url(#dsth)"/></svg>',
        'eye' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="eg" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#E0E7FF"/><stop offset="100%" stop-color="#A5B4FC"/></linearGradient><filter id="dse"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".14"/></filter></defs><ellipse cx="24" cy="24" rx="18" ry="11" fill="url(#eg)" filter="url(#dse)"/><circle cx="24" cy="24" r="7" fill="#F8FAFC"/><circle cx="24" cy="24" r="4" fill="#6366F1"/></svg>',
        'moon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><radialGradient id="mg" cx="28%" cy="22%" r="75%"><stop offset="0%" stop-color="#EDE9FE"/><stop offset="55%" stop-color="#A78BFA"/><stop offset="100%" stop-color="#6D28D9"/></radialGradient><filter id="dsm"><feDropShadow dx="0" dy="2" stdDeviation="2.5" flood-color="#0f172a" flood-opacity=".22"/></filter></defs><circle cx="22" cy="24" r="14" fill="url(#mg)" filter="url(#dsm)"/><circle cx="33" cy="20" r="11" fill="#ffffff"/></svg>',
        'wifi_off' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><filter id="dswf"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".12"/></filter></defs><path d="M8 38 L40 10" stroke="#CBD5E1" stroke-width="3" stroke-linecap="round"/><path d="M12 28 Q24 18 36 28" stroke="#E2E8F0" stroke-width="3" fill="none" stroke-linecap="round" filter="url(#dswf)"/></svg>',
    ];

    $wImg = static function (string $key) use ($clay, $clayDataUri): string {
        $svg = $clay[$key] ?? $clay['cloud'];

        return $clayDataUri($svg);
    };

    $aiApiTempsByDate = collect($forecast ?? [])->take(5)->mapWithKeys(function ($day) {
        $date = isset($day['date']) ? (string) $day['date'] : '';
        if ($date === '') {
            return [];
        }

        $max = isset($day['temp_max']) && is_numeric($day['temp_max']) ? round((float) $day['temp_max'], 1) : null;
        $min = isset($day['temp_min']) && is_numeric($day['temp_min']) ? round((float) $day['temp_min'], 1) : null;

        if ($max !== null && $min !== null) {
            $label = $max . '° / ' . $min . '°C';
        } elseif ($max !== null) {
            $label = $max . '°C';
        } elseif ($min !== null) {
            $label = $min . '°C';
        } else {
            $label = 'Not available';
        }

        return [$date => $label];
    })->all();
    $todayTempLabel = is_numeric($weather['temp'] ?? null) ? round((float) $weather['temp'], 1) . '°C' : 'Not available';
    if (!array_key_exists($currentDateIso, $aiApiTempsByDate)) {
        $aiApiTempsByDate[$currentDateIso] = $todayTempLabel;
    }

    $apiTodayPrecip = is_array($weather ?? null) && isset($weather['today_rain_probability']) && is_numeric($weather['today_rain_probability'])
        ? (int) round((float) $weather['today_rain_probability'])
        : null;
@endphp
@extends('layouts.user')

@section('title', 'Weather Details – AGRIGUARD')

@push('head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
@endpush

@section('body-class', 'dashboard-page weather-page min-h-screen bg-[#f3f8fc]')

@section('main-class', 'pt-20')

@section('content')
    <section class="dashboard-shell dashboard-shell--dashboard-home py-4 sm:py-6 pb-24">
        <div class="dashboard-container max-w-3xl mx-auto px-4 sm:px-5 space-y-4 sm:space-y-5">
            @php
                $weatherReco = is_array($recommendation ?? null) ? $recommendation : [];
                $whCondId = is_array($weather ?? null)
                    ? (int) ($weather['condition']['id'] ?? 800)
                    : 800;
                $weatherHeroEmoji = \App\Http\Controllers\WeatherDetailsController::simpleWeatherEmoji($whCondId);
                $weatherHeroLabel = is_array($weather ?? null) && isset($weather['simple_label']) && $weather['simple_label'] !== ''
                    ? (string) $weather['simple_label']
                    : \App\Http\Controllers\WeatherDetailsController::simpleWeatherLabel($whCondId);
            @endphp
            <header class="dashboard-hero weather-page-hero ag-card" aria-labelledby="weather-page-hero-heading">
                <div class="dashboard-hero__accent" aria-hidden="true">
                    <span class="dashboard-hero__accent-shimmer"></span>
                </div>
                <div class="dashboard-hero__canvas" aria-hidden="true">
                    <span class="dashboard-hero__blob dashboard-hero__blob--a"></span>
                    <span class="dashboard-hero__blob dashboard-hero__blob--b"></span>
                    <span class="dashboard-hero__blob dashboard-hero__blob--c"></span>
                    <span class="dashboard-hero__grain"></span>
                </div>
                <div class="dashboard-hero__layout">
                    <div class="dashboard-hero__main">
                        <div class="dashboard-hero__title-row">
                            <span class="dashboard-hero__greet-badge weather-page-hero__badge" aria-hidden="true">
                                <span class="dashboard-hero__greet-badge-glow"></span>
                                <i data-lucide="cloud-sun" class="dashboard-hero__lucide dashboard-hero__lucide--greet"></i>
                            </span>
                            <div class="dashboard-hero__title-stack">
                                <h1 id="weather-page-hero-heading" class="dashboard-hero__title">
                                    <span class="dashboard-hero__title-line">Weather</span>
                                    <span class="dashboard-hero__title-emoji" aria-hidden="true">{{ $weatherHeroEmoji }}</span>
                                </h1>
                            </div>
                        </div>
                        <div class="dashboard-hero__meta">
                            <span class="dashboard-hero__pill weather-page-hero__pill">
                                <span class="dashboard-hero__pill-ic" aria-hidden="true">
                                    <i data-lucide="map-pin" class="dashboard-hero__lucide"></i>
                                </span>
                                <span class="dashboard-hero__pill-text">{{ $headerLocation }}</span>
                            </span>
                            <span class="dashboard-hero__pill weather-page-hero__pill">
                                <span class="dashboard-hero__pill-ic" aria-hidden="true">
                                    <i data-lucide="calendar-days" class="dashboard-hero__lucide"></i>
                                </span>
                                <time class="dashboard-hero__pill-text" datetime="{{ $currentDateIso }}">{{ $currentDateDisplay }}</time>
                            </span>
                        </div>
                    </div>
                    <div class="dashboard-hero__aside">
                        <span class="dashboard-hero__weather-card weather-page-hero__status-card" aria-hidden="true">
                            <span class="dashboard-hero__weather-glow" aria-hidden="true"></span>
                            <span class="dashboard-hero__weather-ring" aria-hidden="true">
                                <i data-lucide="cloud-sun" class="dashboard-hero__lucide dashboard-hero__lucide--greet"></i>
                            </span>
                            <span class="dashboard-hero__weather-body">
                                <span class="dashboard-hero__weather-label">
                                    <i data-lucide="cloud-sun" class="dashboard-hero__lucide dashboard-hero__lucide--sm" aria-hidden="true"></i>
                                    Live weather
                                </span>
                                <span class="dashboard-hero__weather-temp weather-page-hero__status-value">{{ $weatherHeroLabel ?: 'Clear' }}</span>
                                <span class="dashboard-hero__weather-desc">Live conditions</span>
                            </span>
                        </span>
                    </div>
                </div>
            </header>

            @php
                $todayPlan = is_array($weatherReco['today_plan'] ?? null) ? $weatherReco['today_plan'] : [];
                $wAiStatus = strtolower((string) ($weatherReco['ai_status'] ?? 'failed'));
                $wAiError = trim((string) ($weatherReco['ai_error'] ?? ''));
                $wAiAdvisoryReady = $wAiStatus === 'success';
                $wAiUnavailableMsg = 'AI advisory temporarily unavailable.';
                $wBlockedMsg = $wAiStatus === 'missing_context' && $wAiError !== ''
                    ? $wAiError
                    : ($wAiStatus === 'missing_context' ? 'Please update your crop type and farming stage in Farm Settings to receive AI advisory from Together AI.' : $wAiUnavailableMsg);
                $wSmartAction = $wAiAdvisoryReady ? trim((string) ($weatherReco['main_recommendation'] ?? '')) : $wBlockedMsg;
                if (strcasecmp($wSmartAction, 'Prepare for the upcoming rain to protect newly planted rice.') === 0) {
                    $wSmartAction = 'Monitor field conditions today and prepare your crop protection plan before the next rainfall.';
                }
            @endphp

            <section class="ag-card ai-ux-card overflow-hidden rounded-3xl border border-indigo-200/90 bg-gradient-to-br from-indigo-50 via-violet-50 to-sky-50 p-4 shadow-sm sm:p-5" aria-label="AI weather prediction">
                <div class="flex items-start justify-between gap-3 border-b border-indigo-100/80 pb-2.5">
                    <h2 class="inline-flex items-center gap-2 text-sm font-extrabold uppercase tracking-[0.1em] text-slate-800">
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700 ring-1 ring-indigo-200 animate-pulse">
                            <i data-lucide="brain" class="h-4 w-4"></i>
                        </span>
                        AI Weather Prediction
                    </h2>
                    <span class="inline-flex items-center gap-1 rounded-full border border-indigo-200/90 bg-white/80 px-2 py-1 text-[10px] font-bold uppercase tracking-[0.1em] text-indigo-700">
                        <i data-lucide="sparkles" class="h-3 w-3"></i>
                        Live model
                    </span>
                </div>
                <div class="mt-2 flex items-center justify-start">
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-indigo-200/90 bg-indigo-50/80 px-3 py-1 text-[11px] font-semibold text-indigo-700 shadow-sm">
                        <i data-lucide="calendar-days" class="h-3.5 w-3.5"></i>
                        <span class="uppercase tracking-wide text-[10px] font-bold text-indigo-600">Forecast</span>
                        <span class="text-slate-700">{{ now()->format('M j, Y') }}</span>
                    </span>
                </div>

                @php
                    $apiRainTier = $apiTodayPrecip === null ? 'moderate' : ($apiTodayPrecip <= 20 ? 'low' : ($apiTodayPrecip <= 60 ? 'moderate' : 'high'));
                @endphp
                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <article class="rounded-2xl border border-indigo-100 bg-white/85 px-3 py-2 transition hover:-translate-y-0.5 hover:shadow-sm">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500"><i data-lucide="thermometer" class="mr-1 inline h-3.5 w-3.5 text-rose-500"></i>Temperature (API)</p>
                        <p class="mt-0.5 text-sm font-bold text-slate-900">{{ is_numeric($weather['temp'] ?? null) ? round((float) $weather['temp'], 1) . ' °C' : 'Not available' }}</p>
                    </article>
                    <article class="rounded-2xl border border-indigo-100 bg-white/85 px-3 py-2 transition hover:-translate-y-0.5 hover:shadow-sm">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500"><i data-lucide="cloud-rain" class="mr-1 inline h-3.5 w-3.5 text-blue-500"></i>Rainfall</p>
                        <p id="ai-weather-rainfall" class="mt-0.5 text-sm font-bold text-slate-900">Loading...</p>
                    </article>
                    <article class="rounded-2xl border border-indigo-100 bg-white/85 px-3 py-2 transition hover:-translate-y-0.5 hover:shadow-sm">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500"><i data-lucide="wind" class="mr-1 inline h-3.5 w-3.5 text-cyan-600"></i>Wind Speed</p>
                        <p id="ai-weather-wind" class="mt-0.5 text-sm font-bold text-slate-900">Loading...</p>
                    </article>
                    <article class="rounded-2xl border border-indigo-100 bg-white/85 px-3 py-2 transition hover:-translate-y-0.5 hover:shadow-sm weather-rain-chance-card">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500"><span class="mr-1 inline-block text-base leading-none align-middle" aria-hidden="true">🌧️</span>Rain Chance (%)</p>
                        <div id="ai-weather-rain-chance-block" class="weather-rain-chance-block mt-1 weather-rain-chance-tier--{{ $apiRainTier }} weather-rain-chance-reveal" data-weather-rain-tier="{{ $apiRainTier }}">
                            <p id="ai-weather-rain-chance" class="text-lg font-extrabold tabular-nums leading-tight text-slate-900">{{ $apiTodayPrecip !== null ? $apiTodayPrecip . '%' : '…' }}</p>
                            <div id="ai-weather-rain-chance-bar" class="weather-rain-chance-bar mt-1.5" style="--weather-rain-pct: {{ $apiTodayPrecip ?? 0 }}"></div>
                        </div>
                    </article>
                    <article class="rounded-2xl border border-indigo-100 bg-white/85 px-3 py-2 transition hover:-translate-y-0.5 hover:shadow-sm sm:col-span-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500"><i data-lucide="shield-check" class="mr-1 inline h-3.5 w-3.5 text-emerald-600"></i>Status</p>
                        <p id="ai-weather-status" class="mt-0.5 text-sm font-bold text-slate-900">Loading...</p>
                    </article>
                </div>

                <div class="mt-3 rounded-2xl border border-slate-200 bg-white/90 p-3">
                    <p class="text-[11px] font-bold uppercase tracking-[0.1em] text-slate-600">
                        <i data-lucide="badge-check" class="mr-1 inline h-3.5 w-3.5 text-emerald-600"></i>
                        AI Model Performance
                    </p>
                    <div class="mt-2 grid grid-cols-1 gap-1.5 text-xs text-slate-700 sm:grid-cols-2">
                        <p class="rounded-xl border border-slate-100 bg-slate-50 px-2 py-1">Accuracy: <span id="ai-model-accuracy" class="font-semibold text-slate-900">Loading...</span></p>
                        <p class="rounded-xl border border-slate-100 bg-slate-50 px-2 py-1">Confidence: <span id="ai-model-confidence" class="font-semibold text-slate-900">Loading...</span></p>
                        <p class="rounded-xl border border-slate-100 bg-slate-50 px-2 py-1">Rainfall R²: <span id="ai-model-rain-r2" class="font-semibold text-slate-900">Loading...</span></p>
                        <p class="rounded-xl border border-slate-100 bg-slate-50 px-2 py-1">Wind R²: <span id="ai-model-wind-r2" class="font-semibold text-slate-900">Loading...</span></p>
                        <p class="rounded-xl border border-slate-100 bg-slate-50 px-2 py-1 sm:col-span-2">Dataset: <span id="ai-model-dataset" class="font-semibold text-slate-900">Loading...</span></p>
                    </div>
                </div>
            </section>

            <div class="ag-advisory-toggle-row">
                <button
                    type="button"
                    class="ag-advisory-toggle-btn"
                    data-ai-advisory-toggle
                    data-target="advisory-weather-section"
                    data-storage-key="advisory_visibility_weather"
                    aria-pressed="true"
                >
                    Hide AI Smart Advisory
                </button>
            </div>
            <section id="advisory-weather-section" data-ai-smart-advisory-section>
            <article class="ag-card dash-smart weather-page__smart rounded-3xl border border-emerald-200 bg-emerald-50/80 p-4 sm:p-5" aria-label="AI smart advisory">
                <div class="dash-smart__debug">
                    <p class="text-xs font-semibold text-slate-700">
                        @if ($wAiAdvisoryReady)
                            <span id="ai-smart-advisory-status" class="text-emerald-700">AI Smart Advisory: Active</span>
                        @elseif ($wAiStatus === 'missing_context')
                            <span id="ai-smart-advisory-status" class="text-amber-800">AI Smart Advisory: Profile incomplete</span>
                        @else
                            <span id="ai-smart-advisory-status" class="text-rose-700">AI Smart Advisory: Unavailable</span>
                        @endif
                    </p>
                </div>

                <div class="dash-smart__head">
                    <div class="dash-smart__title-wrap">
                        <span class="inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-xs font-extrabold uppercase tracking-[0.1em] text-slate-700 transition-all duration-300 hover:tracking-[0.12em] hover:text-slate-900" aria-hidden="true">
                            <i data-lucide="sparkles" class="h-3.5 w-3.5 text-emerald-600"></i>
                            Smart action
                        </span>
                    </div>
                </div>

                <div class="dash-smart__body">
                    <p id="ai-smart-action-text" class="dash-smart__action">{{ $wSmartAction }}</p>
                </div>
            </article>

            <section class="ag-card rounded-3xl border border-slate-200 bg-slate-50/90 p-4 sm:p-5 shadow-sm" aria-label="Today's plan">
                <h2 class="inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-sm font-extrabold uppercase tracking-[0.1em] text-slate-800 transition-all duration-300 hover:tracking-[0.12em] hover:text-slate-900">
                    <i data-lucide="calendar-check-2" class="h-4 w-4 text-amber-600"></i>
                    Today&rsquo;s Plan
                </h2>
                <div class="mt-3 space-y-3">
                    <article class="flex gap-3 rounded-2xl border border-amber-100/80 bg-amber-50 px-3 py-3">
                        <img src="{{ $wImg('sun') }}" alt="" class="weather-clay-ic weather-clay-ic--plan mt-0.5" width="22" height="22" decoding="async">
                        <div><p class="text-sm font-semibold text-slate-800">Morning</p><p class="text-sm text-slate-600">{{ $wAiAdvisoryReady ? trim((string) ($todayPlan['morning'] ?? '')) : $wBlockedMsg }}</p></div>
                    </article>
                    <article class="flex gap-3 rounded-2xl border border-sky-100/80 bg-sky-50 px-3 py-3">
                        <img src="{{ $wImg('partly_cloudy') }}" alt="" class="weather-clay-ic weather-clay-ic--plan mt-0.5" width="22" height="22" decoding="async">
                        <div><p class="text-sm font-semibold text-slate-800">Afternoon</p><p class="text-sm text-slate-600">{{ $wAiAdvisoryReady ? trim((string) ($todayPlan['afternoon'] ?? '')) : $wBlockedMsg }}</p></div>
                    </article>
                    <article class="flex gap-3 rounded-2xl border border-violet-100/80 bg-violet-50 px-3 py-3">
                        <img src="{{ $wImg('moon') }}" alt="" class="weather-clay-ic weather-clay-ic--plan mt-0.5" width="22" height="22" decoding="async">
                        <div><p class="text-sm font-semibold text-slate-800">Evening</p><p class="text-sm text-slate-600">{{ $wAiAdvisoryReady ? trim((string) ($todayPlan['evening'] ?? '')) : $wBlockedMsg }}</p></div>
                    </article>
                </div>
            </section>

            <section class="grid gap-3 sm:grid-cols-2" aria-label="Avoid and water strategy">
                <div class="ag-card rounded-3xl border border-rose-100 bg-rose-50/70 p-4">
                    <div class="dash-split__card dash-split__card--avoid">
                    <div class="dash-split__head inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-xs font-semibold uppercase tracking-[0.1em] text-slate-700 transition-all duration-300 hover:tracking-[0.12em] hover:text-slate-900">
                            <img src="{{ $wImg('alert') }}" class="dash-split__ic-img" width="15" height="15" alt="">
                            Avoid
                        </div>
                        <p class="dash-split__body">{{ $wAiAdvisoryReady ? trim((string) ($weatherReco['avoid'] ?? '')) : $wBlockedMsg }}</p>
                    </div>
                </div>
                <div class="ag-card rounded-3xl border border-cyan-100 bg-cyan-50/70 p-4">
                    <div class="dash-split__card dash-split__card--water">
                    <div class="dash-split__head inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-xs font-semibold uppercase tracking-[0.1em] text-slate-700 transition-all duration-300 hover:tracking-[0.12em] hover:text-slate-900">
                            <img src="{{ $wImg('droplet') }}" class="dash-split__ic-img" width="15" height="15" alt="">
                            Water
                        </div>
                        <p class="dash-split__body">{{ $wAiAdvisoryReady ? trim((string) ($weatherReco['water_strategy'] ?? '')) : $wBlockedMsg }}</p>
                    </div>
                </div>
            </section>
            <div class="flex items-start gap-2.5 rounded-2xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm">
                <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-indigo-600" aria-hidden="true">
                    <i data-lucide="info" class="h-3.5 w-3.5"></i>
                </span>
                <p class="pt-0.5 text-xs leading-relaxed text-slate-600">
                    AI-generated recommendations only. Based on system data including weather, rainfall, crop, and field conditions. For farm decision support.
                </p>
            </div>
            </section>

            <section class="ag-card ai-ux-card overflow-hidden rounded-3xl border border-violet-200/90 bg-gradient-to-br from-violet-50 via-indigo-50 to-sky-50 p-4 shadow-sm sm:p-5" aria-label="AI 5-day forecast model section">
                <div class="flex items-start justify-between gap-3 border-b border-violet-100 pb-2.5">
                    <h2 class="inline-flex items-center gap-2 text-sm font-extrabold uppercase tracking-[0.1em] text-slate-800">
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-xl bg-violet-100 text-violet-700 ring-1 ring-violet-200 animate-pulse">
                            <i data-lucide="calendar-days" class="h-4 w-4"></i>
                        </span>
                        AI 5-Day Forecast (Model)
                    </h2>
                    <span class="inline-flex items-center gap-1 rounded-full border border-violet-200/90 bg-white/80 px-2 py-1 text-[10px] font-bold uppercase tracking-[0.1em] text-violet-700">
                        <i data-lucide="sparkles" class="h-3 w-3"></i>
                        Next 5 days
                    </span>
                </div>
                <div id="ai-weather-5day" class="mt-3 space-y-2 text-xs text-slate-700">
                    <p>Loading 5-day AI forecast...</p>
                </div>
            </section>

        </div>
    </section>
@endsection

@push('scripts')
    {{-- Relative URL keeps fetch same-origin when APP_URL host differs from the browser (e.g. localhost vs 127.0.0.1). --}}
    <script id="ai-weather-config" type="application/json">@json(['prediction_url' => route('api.weather-prediction', [], false)])</script>
    <script id="ai-weather-initial-prediction" type="application/json">@json($ml_prediction ?? null)</script>
    <script id="ai-api-temps-json" type="application/json">@json($aiApiTempsByDate)</script>
    <script id="ai-weather-rain-json" type="application/json">@json([
        'api_today_precip_percent' => $apiTodayPrecip ?? null,
    ])</script>
@endpush

