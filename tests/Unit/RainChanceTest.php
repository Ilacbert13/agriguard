<?php

namespace Tests\Unit;

use App\Support\RainChance;
use PHPUnit\Framework\TestCase;

class RainChanceTest extends TestCase
{
    public function test_weather_page_display_prefers_model_rainfall_over_api(): void
    {
        $this->assertSame(40, RainChance::weatherPageDisplay(1.5, 78));
    }

    public function test_weather_page_display_falls_back_to_api_when_model_missing(): void
    {
        $this->assertSame(78, RainChance::weatherPageDisplay(null, 78));
    }

    public function test_weather_page_display_returns_null_when_both_missing(): void
    {
        $this->assertNull(RainChance::weatherPageDisplay(null, null));
    }

    public function test_calculate_rain_chance_matches_weather_page_buckets(): void
    {
        $this->assertSame(5, RainChance::calculateRainChance(0.0));
        $this->assertSame(15, RainChance::calculateRainChance(0.3));
        $this->assertSame(40, RainChance::calculateRainChance(1.0));
        $this->assertSame(70, RainChance::calculateRainChance(5.0));
        $this->assertSame(90, RainChance::calculateRainChance(12.0));
    }
}
