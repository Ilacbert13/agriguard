@extends('layouts.user')

@section('title', 'AI Assistant · AgriGuard')

@push('head')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('body-class', 'dashboard-page assistant-page min-h-screen overflow-x-hidden bg-[#EEF1F6]')
@section('main-class', 'pt-[4.5rem] sm:pt-20')

@section('content')
    @php
        $ctx = $assistant_context ?? [];
        $chatHistory = $assistant_chat_history ?? [];
        $weatherText = (string) data_get($ctx, 'current_weather_text', '24°C · Clear sky');
        $weatherStatus = trim((string) (str_contains($weatherText, '·') ? explode('·', $weatherText)[1] : $weatherText));
    @endphp

    <section class="dashboard-shell assistant-dashboard-shell min-w-0 py-4 sm:py-6">
        <div
            id="assistant-page"
            class="dashboard-container assistant-main w-full min-w-0 max-w-3xl mx-auto px-4 sm:px-5"
            data-chat-url="{{ route('assistant.chat') }}"
            data-clear-url="{{ route('assistant.clear') }}"
            data-prediction-url="{{ route('api.weather-prediction', [], false) }}"
            data-api-rain-fallback="{{ is_numeric(data_get($ctx, 'api_rain_fallback_percent')) ? (int) data_get($ctx, 'api_rain_fallback_percent') : '' }}"
            data-context='@json($ctx)'
            data-history='@json($chatHistory)'
        >
            <header class="dashboard-hero assistant-page-hero ag-card" aria-labelledby="assistant-page-hero-heading">
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
                            <span class="dashboard-hero__greet-badge assistant-page-hero__badge" aria-hidden="true">
                                <span class="dashboard-hero__greet-badge-glow"></span>
                                <i data-lucide="bot" class="dashboard-hero__lucide dashboard-hero__lucide--greet"></i>
                            </span>
                            <div class="dashboard-hero__title-stack">
                                <h1 id="assistant-page-hero-heading" class="dashboard-hero__title">
                                    <span class="dashboard-hero__title-line">AgriGuard AI Assistant</span>
                                    <span class="dashboard-hero__title-emoji" aria-hidden="true">🌾</span>
                                </h1>
                                <p class="dashboard-hero__subtitle">
                                    <span class="dashboard-hero__subtitle-ic" aria-hidden="true">
                                        <i data-lucide="sparkles" class="dashboard-hero__lucide dashboard-hero__lucide--xs"></i>
                                    </span>
                                    <span>Farming &amp; disaster-aware companion for daily decisions</span>
                                </p>
                            </div>
                        </div>
                        <div class="dashboard-hero__meta">
                            <span class="dashboard-hero__pill assistant-page-hero__pill">
                                <span class="dashboard-hero__pill-ic" aria-hidden="true">
                                    <i data-lucide="map-pin" class="dashboard-hero__lucide"></i>
                                </span>
                                <span class="dashboard-hero__pill-text">{{ data_get($ctx, 'location_display', auth()->user()?->farm_location_display ?? 'Set your farm location in Settings') }}</span>
                            </span>
                            <span class="dashboard-hero__pill assistant-page-hero__pill">
                                <span class="dashboard-hero__pill-ic" aria-hidden="true">
                                    <i data-lucide="calendar-days" class="dashboard-hero__lucide"></i>
                                </span>
                                <time class="dashboard-hero__pill-text" datetime="{{ now()->toDateString() }}">{{ now()->format('l, F j, Y') }}</time>
                            </span>
                        </div>
                    </div>
                    <div class="dashboard-hero__aside">
                        <span class="dashboard-hero__weather-card assistant-page-hero__assistant-card" aria-hidden="true">
                            <span class="dashboard-hero__weather-glow"></span>
                            <span class="dashboard-hero__weather-ring">
                                <i data-lucide="cpu" class="dashboard-hero__lucide dashboard-hero__lucide--greet"></i>
                            </span>
                            <span class="dashboard-hero__weather-body">
                                <span class="dashboard-hero__weather-label">🟢 AI Active</span>
                                <span class="dashboard-hero__weather-desc">🌐 Multi-language supported</span>
                            </span>
                        </span>
                    </div>
                </div>
            </header>

            <section class="assistant-snapshot" id="assistant-snapshot">
                Today&apos;s snapshot: {{ is_numeric(data_get($ctx, 'display_rain_chance_percent')) && (int) data_get($ctx, 'display_rain_chance_percent') < 50 ? 'Good conditions for field work today.' : 'Stay alert for rain and choose lower-risk field tasks.' }}
            </section>

            <section class="assistant-summary-shell ag-card" aria-label="Farm and weather context">
                <div class="assistant-cards assistant-cards--single-row" aria-label="Crop, stage, weather, and rain chance">
                    <article class="assistant-card assistant-card--crop">
                        <div class="assistant-card-head">
                            <span class="assistant-card-icon"><i data-lucide="wheat"></i></span>
                            <p class="assistant-card-label">Crop</p>
                        </div>
                        <p class="assistant-card-value">{{ data_get($ctx, 'crop_type', 'Rice') }}</p>
                    </article>

                    <article class="assistant-card assistant-card--stage">
                        <div class="assistant-card-head">
                            <span class="assistant-card-icon"><i data-lucide="sprout"></i></span>
                            <p class="assistant-card-label">Stage</p>
                        </div>
                        <p class="assistant-card-value">{{ data_get($ctx, 'growth_stage', 'Vegetative') }}</p>
                    </article>

                    <article class="assistant-card assistant-card--weather">
                        <div class="assistant-card-head">
                            <span class="assistant-card-icon"><i data-lucide="cloud-sun"></i></span>
                            <p class="assistant-card-label">Weather</p>
                        </div>
                        <p class="assistant-card-value">{{ data_get($ctx, 'current_weather_text', '24°C · Clear sky') }}</p>
                    </article>

                    <article class="assistant-card assistant-card--weather">
                        <div class="assistant-card-head">
                            <span class="assistant-card-icon"><i data-lucide="cloud-rain"></i></span>
                            <p class="assistant-card-label">Rain Chance</p>
                        </div>
                        <p id="assistant-rain-chance-value" class="assistant-card-value">{{ data_get($ctx, 'risk_snapshot.rain_chance_display', '—') }}</p>
                    </article>
                </div>
            </section>

            <section class="assistant-chat-shell">
                <div class="assistant-chat-head assistant-top-info" role="region" aria-label="AI Assistant status">
                    <div class="assistant-top-info__left">
                        <span class="assistant-top-info__item assistant-top-info__item--weather">
                            <i data-lucide="cloud-sun" class="assistant-top-info__icon"></i>
                            <span class="assistant-top-info__label">{{ $weatherStatus ?: 'Clear' }}</span>
                        </span>
                    </div>
                    <div class="assistant-chat-actions">
                        <span id="assistant-fallback-badge" class="assistant-fallback-badge hidden">Using basic farm guidance</span>
                        <button id="assistant-clear-btn" type="button" class="assistant-clear-btn">Clear</button>
                    </div>
                </div>

                <div class="assistant-welcome assistant-intro-card">
                    <div class="assistant-welcome-head">
                        <span class="assistant-welcome-avatar">🌾</span>
                        <div>
                            <p class="assistant-welcome-title">AgriGuard AI Assistant</p>
                            <p class="assistant-intro-subtitle">Farming and disaster-aware guidance for smarter daily decisions.</p>
                        </div>
                    </div>
                </div>

                <div id="assistant-messages" class="assistant-messages" role="log" aria-live="polite"></div>

                <div id="assistant-typing" class="assistant-typing hidden">
                    <span class="assistant-typing-dot"></span>
                    <span class="assistant-typing-dot"></span>
                    <span class="assistant-typing-dot"></span>
                    Assistant is thinking...
                </div>

                <section class="assistant-bottom-bar assistant-bottom-bar--inline" aria-label="AI Assistant input actions">
                    <div class="assistant-bottom-inner">
                        <div class="assistant-quick-actions" aria-label="Suggested quick prompts">
                            <button type="button" class="assistant-quick-chip" data-assistant-prompt="Give crop advice based on today's weather and growth stage.">🌱 Crop advice</button>
                            <button type="button" class="assistant-quick-chip" data-assistant-prompt="What is the rain chance for my farm today?">🌧 Rain chance</button>
                            <button type="button" class="assistant-quick-chip" data-assistant-prompt="How should I handle irrigation today?">💧 Irrigation</button>
                            <button type="button" class="assistant-quick-chip" data-assistant-prompt="What should I do on my farm before heavy rain or a storm, including drainage, harvest timing, and securing tools or structures?">🌀 Flood &amp; storm prep</button>
                            <button type="button" class="assistant-quick-chip" data-assistant-prompt="What pest control steps should I prioritize this week?">🐛 Pest control</button>
                            <button type="button" class="assistant-quick-chip" data-assistant-prompt="What fertilizer or nutrient timing should I consider for my crop stage, given today's weather and soil moisture outlook?">🌾 Fertilizer &amp; nutrients</button>
                        </div>

                        <form id="assistant-form" class="assistant-modern-composer" autocomplete="off">
                            <textarea
                                id="assistant-input"
                                class="assistant-input assistant-input--modern"
                                rows="1"
                                placeholder="Ask about crops, weather, irrigation, floods, or preparedness..."
                                @if (!data_get($ctx, 'has_gps')) disabled @endif
                            ></textarea>
                            <button id="assistant-send-btn" type="submit" class="assistant-send-btn assistant-send-btn--modern" @if (!data_get($ctx, 'has_gps')) disabled @endif>
                                <i data-lucide="send"></i>
                            </button>
                        </form>
                    </div>
                </section>

                @if (!data_get($ctx, 'has_gps'))
                    <p class="assistant-note">Save GPS in <a href="{{ route('map.index') }}">Map</a> for more accurate weather- and location-aware responses.</p>
                @endif
            </section>
        </div>
    </section>
@endsection

