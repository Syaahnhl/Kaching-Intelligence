<?php

declare(strict_types=1);

namespace Tests\Unit;

use Kaching\Intelligence\DTOs\CommodityResultDTO;
use Kaching\Intelligence\DTOs\WeatherResultDTO;
use Kaching\Intelligence\Providers\BapanasCommodityPriceProvider;
use Kaching\Intelligence\Providers\OpenMeteoWeatherProvider;
use PHPUnit\Framework\TestCase;

class ExternalProvidersUnitTest extends TestCase
{
    protected function tearDown(): void
    {
        BapanasCommodityPriceProvider::setMockTransport(null);
        OpenMeteoWeatherProvider::setMockTransport(null);
        parent::tearDown();
    }

    public function test_bapanas_returns_unconfigured_when_no_api_key(): void
    {
        $provider = new BapanasCommodityPriceProvider(apiKey: null);
        $this->assertFalse($provider->isConfigured());

        $res = $provider->fetchPrices();
        $this->assertFalse($res->success);
        $this->assertSame(CommodityResultDTO::STATUS_UNCONFIGURED, $res->status);
        $this->assertStringContainsString('not configured', (string) $res->errorMessage);
    }

    public function test_bapanas_parses_valid_payload(): void
    {
        BapanasCommodityPriceProvider::setMockTransport(function (string $url, array $headers) {
            $this->assertArrayHasKey('x-api-key', $headers);
            $this->assertSame('secret-test-key', $headers['x-api-key']);

            return [
                'status' => 200,
                'body' => json_encode([
                    'status' => 'success',
                    'data' => [
                        [
                            'commodity_code' => 'CABAI_RAWIT_MERAH',
                            'commodity_name' => 'Cabai Rawit Merah',
                            'price' => 45000,
                            'uom' => 'kg',
                        ],
                        [
                            'commodity_code' => 'BERAS_PREMIUM',
                            'commodity_name' => 'Beras Premium',
                            'price' => 15500,
                            'uom' => 'kg',
                        ],
                    ],
                ]),
                'headers' => [],
            ];
        });

        $provider = new BapanasCommodityPriceProvider(apiKey: 'secret-test-key');
        $res = $provider->fetchPrices(['regency_city' => 'JAKARTA PUSAT']);

        $this->assertTrue($res->success);
        $this->assertSame(CommodityResultDTO::STATUS_SUCCESS, $res->status);
        $this->assertCount(2, $res->items);
        $this->assertSame('CABAI_RAWIT_MERAH', $res->items[0]->commodityCode);
        $this->assertSame(45000.0, $res->items[0]->price);
        $this->assertSame('JAKARTA PUSAT', $res->items[0]->marketRegion);
    }

    public function test_bapanas_handles_unauthorized_response(): void
    {
        BapanasCommodityPriceProvider::setMockTransport(function () {
            return [
                'status' => 401,
                'body' => '{"message":"Unauthorized"}',
                'headers' => [],
            ];
        });

        $provider = new BapanasCommodityPriceProvider(apiKey: 'invalid-key');
        $res = $provider->fetchPrices();

        $this->assertFalse($res->success);
        $this->assertSame(CommodityResultDTO::STATUS_UNAVAILABLE, $res->status);
        $this->assertSame(401, $res->httpStatus);
        $this->assertStringContainsString('Unauthorized', (string) $res->errorMessage);
    }

    public function test_bapanas_handles_timeout(): void
    {
        BapanasCommodityPriceProvider::setMockTransport(function () {
            throw new \RuntimeException('cURL error 28: Operation timed out after 5000 milliseconds');
        });

        $provider = new BapanasCommodityPriceProvider(apiKey: 'test-key');
        $res = $provider->fetchPrices();

        $this->assertFalse($res->success);
        $this->assertSame(CommodityResultDTO::STATUS_TIMEOUT, $res->status);
        $this->assertStringContainsString('timed out', (string) $res->errorMessage);
    }

    public function test_bapanas_handles_malformed_json(): void
    {
        BapanasCommodityPriceProvider::setMockTransport(function () {
            return [
                'status' => 200,
                'body' => '<html><body>Bad Gateway</body></html>',
                'headers' => [],
            ];
        });

        $provider = new BapanasCommodityPriceProvider(apiKey: 'test-key');
        $res = $provider->fetchPrices();

        $this->assertFalse($res->success);
        $this->assertSame(CommodityResultDTO::STATUS_INVALID_RESPONSE, $res->status);
        $this->assertStringContainsString('Malformed JSON', (string) $res->errorMessage);
    }

    public function test_open_meteo_parses_valid_payload(): void
    {
        OpenMeteoWeatherProvider::setMockTransport(function (string $url) {
            $this->assertStringContainsString('latitude=-6.2', $url);
            $this->assertStringContainsString('longitude=106.8', $url);

            return [
                'status' => 200,
                'body' => json_encode([
                    'latitude' => -6.2,
                    'longitude' => 106.8,
                    'timezone' => 'Asia/Jakarta',
                    'current' => [
                        'time' => '2026-11-01T14:00',
                        'temperature_2m' => 31.5,
                        'relative_humidity_2m' => 75,
                        'precipitation' => 2.4,
                        'weather_code' => 61, // Slight rain
                        'wind_speed_10m' => 12.3,
                    ],
                    'hourly' => [
                        'time' => ['2026-11-01T15:00', '2026-11-01T16:00'],
                        'temperature_2m' => [30.0, 28.5],
                        'precipitation' => [3.0, 1.0],
                        'weather_code' => [63, 61],
                    ],
                ]),
                'headers' => [],
            ];
        });

        $provider = new OpenMeteoWeatherProvider();
        $res = $provider->fetchWeather(-6.2, 106.8);

        $this->assertTrue($res->success);
        $this->assertSame(WeatherResultDTO::STATUS_SUCCESS, $res->status);
        $this->assertNotNull($res->current);
        $this->assertSame(31.5, $res->current->temperatureC);
        $this->assertSame(61, $res->current->weatherCode);
        $this->assertStringContainsString('Hujan Ringan', $res->current->weatherCondition);
        $this->assertCount(2, $res->forecastItems);
    }

    public function test_open_meteo_handles_timeout_and_empty(): void
    {
        OpenMeteoWeatherProvider::setMockTransport(function () {
            throw new \RuntimeException('Operation timed out');
        });

        $provider = new OpenMeteoWeatherProvider();
        $res = $provider->fetchWeather(-6.2, 106.8);

        $this->assertFalse($res->success);
        $this->assertSame(WeatherResultDTO::STATUS_TIMEOUT, $res->status);
    }
}
