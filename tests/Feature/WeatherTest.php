<?php

namespace Tests\Feature;

use Exception;
use Tests\TestCase;
use App\Services\WeatherServices;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use PhpParser\Node\Expr\Cast\Object_;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WeatherTest extends TestCase
{
    public function test_city_weather_api_with_valid_city_name()
    {
        // fake response as json file
        $body = file_get_contents(base_path('tests/Fixtures/CityWeatherResponse.json'));

        Http::fake([
            'http://api.weatherapi.com/*' => Http::response($body, 200),
        ]);

        // When the getWeatherData method is called within the test, it will receive the fake response instead of making an actual API call,
        $weatherService = new WeatherServices();
        $weatherData = $weatherService->getCityWeather('Jeddah');

        $response = json_decode($body, true);

        $this->assertSame($response['location'], $weatherData->location);
        $this->assertSame($response['current'], $weatherData->current);
    }

    public function test_city_weather_api_with_invalid_city_name()
    {

        Http::fake([
            'http://api.weatherapi.com/*' => Http::response(['error' => [
                'message' => 'No weather found matching the city',
                'code' => 1006

            ]], 400),
        ]);

        try {
            $weatherService = new WeatherServices();
            $response = $weatherService->getCityWeather('dekd');
        } catch (Exception $exception) {
            $this->assertEquals(400, $exception->getCode());
        }
    }

    public function test_use_api_without_key()
    {

        Http::fake([
            'http://api.weatherapi.com/*' => Http::response(['error' => [
                'message' => 'API key not provided',
                'code' => 1002
            ]], 401),
        ]);


        try {
            $weatherService = new WeatherServices();
            $response = $weatherService->getCityWeather('jeddah');
        } catch (Exception $exception) {
            $this->assertEquals(401, $exception->getCode());
        }
    }

    public function test_use_api_with_invalid_key()
    {

        Http::fake([
            'http://api.weatherapi.com/*' => Http::response(['error' => [
                'message' => 'API key provided is invalid',
                'code' => 2006

            ]], 401),
        ]);


        try {
            $weatherService = new WeatherServices();
            $response = $weatherService->getCityWeather('jeddah');
        } catch (Exception $exception) {
            $this->assertEquals(401, $exception->getCode());
        }
    }

    public function test_city_weather_api_with_array_of_city_name()
    {
        // fake response as json file
        $body = file_get_contents(base_path('tests/Fixtures/CitiesWeatherResponse.json'));
        Http::fake([
            'http://api.weatherapi.com/*' => Http::response($body, 200),
        ]);

        $cities = ['jeddah-sa', 'makkah-sa', 'riyadh-sa',];
        $weatherService = new WeatherServices();
        $weatherData = $weatherService->getCitiesWeather($cities);

        $jeddahWeather = $weatherData[0];
        $makkahWeather = $weatherData[1];
        $riyadhWeather = $weatherData[2];

        $response = json_decode($body, true);
        $response = $response['bulk'];

        $this->assertSame($response[0]['query']['location'], $jeddahWeather->location);
        $this->assertSame($response[0]['query']['current'], $jeddahWeather->current);

        $this->assertSame($response[1]['query']['location'], $makkahWeather->location);
        $this->assertSame($response[1]['query']['current'], $makkahWeather->current);

        $this->assertSame($response[2]['query']['location'], $riyadhWeather->location);
        $this->assertSame($response[2]['query']['current'], $riyadhWeather->current);
    }

    public function test_cache_is_running()
    {
        $cityWeather = file_get_contents(base_path('tests/Fixtures/CityWeatherResponse.json'));
        $cityWeather = (object) json_decode($cityWeather, true);

        Cache::put('jeddah', $cityWeather);
        $jeddahWeather = Cache::get('jeddah');

        $this->assertNotEmpty($jeddahWeather);
    }

    public function test_statistics_weather()
    {
        // fake response as json file
        $body = file_get_contents(base_path('tests/Fixtures/CitiesWeatherResponse.json'));
        Http::fake([
            'http://api.weatherapi.com/*' => Http::response($body, 200),
        ]);

        $weatherService = new WeatherServices();
        $statisticsWeather = $weatherService->getStatisticsWeather();

        $response = file_get_contents(base_path('tests/Fixtures/StatisticsWeatherResponse.json'));
        $response = json_decode($response , true);

        $this->assertSame($response, $statisticsWeather);
    }
}
