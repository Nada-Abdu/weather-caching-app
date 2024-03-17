<?php

namespace App\Services;

use App\Interfaces\WeatherInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Enums\TimeToLiveInMinutesEnums;
use Exception;

class WeatherServices implements WeatherInterface
{

    private $key;
    public function __construct()
    {
        // API key added in .env file to make it secure
        $this->key =  config('services.weatherAPI.key');
    }

    public function getCityWeather($city)
    {
        $city = strtolower($city);
        // get cached data by key (city name)
        $cityWeather = Cache::get($city);

        // if the weather data is not in the cache, we will get it from the API
        if (!$cityWeather) {
            $response = Http::get('http://api.weatherapi.com/v1/current.json?key=' . $this->key . '&q=' . $city);

            if (!$response->successful()) {
                // error handling
                $APICodeError = $response->json()['error']['code'];
                $error = $this->handleAPIError($APICodeError);
                throw $error;
            }

            //maintainability purpose: we can call it anywhere in the application, and we can also modify the function in one place without having to navigate through all the classes.
            $timeToLiveIn30Min = $this->getTimeToLiveIn30Min();

            // retrieve the data from the weather API and cache it.
            $cityWeather = (object) $response->json();
            Cache::put($city, $cityWeather, $timeToLiveIn30Min);
        }
        return  $cityWeather;
    }

    public function getCitiesWeather($cities)
    {
        try {
            $citiestWeatherForRequest = [];
            $cachedCitiestWeather = [];

            // convert all city to lower case
            $cities = array_map('strtolower', $cities);
            // Get all requested cities from the cache, if the city is in the cache it will return weather as a value else return null
            $requestedCitiestWeather = Cache::many($cities);

            // for loop through all requested cities to separate the cached cities (to return them) from non-cached cities (to request them)
            foreach ($requestedCitiestWeather as $city => $weather) {
                if ($weather) {
                    array_push($cachedCitiestWeather, $weather);
                } else {
                    // 'q' is requested format from weather API
                    array_push($citiestWeatherForRequest, ['q' => $city]);
                }
            }

            // 'locations' is requested format from weather API
            $citiestWeatherForRequest = [
                "locations" => $citiestWeatherForRequest
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])->post('http://api.weatherapi.com/v1/current.json?key=' . $this->key . '&q=bulk', $citiestWeatherForRequest);


            // error handling
            if (!$response->successful()) {
                $APICodeError = $response->json()['error']['code'];
                $error = $this->handleAPIError($APICodeError);
                throw $error;
            }

            // arranged and cache of cities weather
            $citiestWeather = $this->arrangedandCacheBulkData($response->json());
            return array_merge($cachedCitiestWeather, $citiestWeather);
        } catch (Exception $exception) {
            throw $exception;
        }
    }

    public function getStatisticsWeather()
    {
        try {
            // chech for statistics weather
            $statisticsWeather = Cache::get('statistics_weather');
            if ($statisticsWeather) {
                return $statisticsWeather;
            } else {
                $cities = ['makkah-sa', 'madina-sa', 'riyadh-sa', 'jeddah-sa', 'taif-sa',  'dammam-sa', 'abha-sa', 'jazan-sa'];

                // reuse getCitiesWeather function to get cached cities' weather and get others cities from API and cached it and returns all cities as an array of objects
                $citiesWeather = $this->getCitiesWeather($cities);
                $timeToLiveIn30Min = $this->getTimeToLiveIn30Min();

                $statisticsWeatherArray = $this->arrangedAndCachedStatisticsWeatherData($citiesWeather, count($cities));
                Cache::put('statistics_weather', $statisticsWeatherArray, $timeToLiveIn30Min);
                return $statisticsWeatherArray;
            }
        } catch (Exception $exception) {
            throw $exception;
        }
    }


    public function handleAPIError($errorCode)
    {
        // Determine error message based on the API error code
        switch ($errorCode) {
            case 1002:
                $errorMessage = 'API key not provided';
                $code = 401;
                break;
            case 2006:
                $errorMessage = 'API key provided is invalid';
                $code = 401;
                break;
            case 2007:
                $errorMessage = 'API key has exceeded calls per month.';
                $code = 403;
                break;
            case 2008:
                $errorMessage = 'API key has been disabled.';
                $code = 403;
                break;
            case 2009:
                $errorMessage = 'API key does not have access to the resource. Please check pricing page for what is allowed in your API subscription plan.';
                $code = 403;
                break;
            case 1003:
                $errorMessage = 'Parameter city not provided.';
                $code = 400;
                break;
            case 1005:
                $errorMessage = 'API request url is invalid';
                $code = 400;
                break;
            case 1006:
                $errorMessage = 'No weather found matching the city';
                $code = 400;
                break;
            case 9000:
                $errorMessage = 'Json body passed in bulk request is invalid. Please make sure it is valid json with utf-8 encoding.';
                $code = 400;
                break;
            case 9001:
                $errorMessage = 'Json body contains too many locations for bulk request. Please keep it below 50 in a single reques';
                $code = 400;
                break;
            case 9999:
                $errorMessage = 'Internal application error.';
                $code = 400;
                break;
            default:
                $code = 500;
                $errorMessage = 'An error occurred while processing your request.';
                break;
        }

        return new Exception($errorMessage, $code);
    }

    public function arrangedandCacheBulkData($citiesWeather)
    {
        try {
            $citiesWeather = $citiesWeather['bulk'];
            $arrangedWeatherData = collect($citiesWeather)->map(function ($cityWeather) {
                //if user sends wrong city name in the cities array, weather API does not return an error
                //In this case the API weather not handle it, we must handle manually (1006 refers to no matching city name)
                if (!isset($cityWeather['query']['location'])) {
                    $notFoundError = 1006;
                    throw $this->handleAPIError($notFoundError);
                }

                $locationData = $cityWeather['query']['location'];
                $cityName = strtolower($locationData['name']);
                $currentData = $cityWeather['query']['current'];

                $timeToLiveIn30Min = $this->getTimeToLiveIn30Min();

                $cityWeather = [
                    'location' => $locationData,
                    'current' => $currentData,
                ];

                $cityWeather = (object) $cityWeather;
                Cache::put($cityName, $cityWeather, $timeToLiveIn30Min);
                return  $cityWeather;
            });

            return $arrangedWeatherData->toArray();
        } catch (Exception $exception) {
            throw $exception;
        }
    }

    public function arrangedAndCachedStatisticsWeatherData($citiesWeather, $citiesCount)
    {
        try {
            $highestTemp = [];
            $lowestTemp = [];
            $tempSum = 0;

            $highestHumidity = [];
            $lowestHumidity = [];
            $humiditySum = 0;

            $speediestWind = [];
            $slowestWind = [];
            $speedWindSum = 0;

            $mostCoveredByCloud = [];
            $lessCoveredByCloud = [];
            $cloudCoverSum = 0;

            foreach ($citiesWeather as $cityWeather) {
                $cityName = strtolower($cityWeather->location['name']);

                // temp
                $highestTemp = $this->comparison($cityWeather->current['temp_c'], $cityName, $highestTemp, 'more');
                $lowestTemp = $this->comparison($cityWeather->current['temp_c'], $cityName, $lowestTemp, 'less');

                // humidity
                $highestHumidity = $this->comparison($cityWeather->current['humidity'], $cityName, $highestHumidity, 'more');
                $lowestHumidity = $this->comparison($cityWeather->current['humidity'], $cityName, $lowestHumidity, 'less');

                // wind
                $speediestWind = $this->comparison($cityWeather->current['wind_kph'], $cityName, $speediestWind, 'more');
                $slowestWind = $this->comparison($cityWeather->current['wind_kph'], $cityName, $slowestWind, 'less');

                // Cloud
                $mostCoveredByCloud = $this->comparison($cityWeather->current['cloud'], $cityName, $mostCoveredByCloud, 'more');
                $lessCoveredByCloud = $this->comparison($cityWeather->current['cloud'], $cityName, $lessCoveredByCloud, 'less');

                $tempSum += $cityWeather->current['temp_c'];
                $humiditySum += $cityWeather->current['humidity'];
                $speedWindSum += $cityWeather->current['wind_kph'];
                $cloudCoverSum += $cityWeather->current['cloud'];
            }

            $averageTemp = $tempSum / $citiesCount;
            $averageHumidity =  $humiditySum / $citiesCount;
            $averageSpeedWind = $speedWindSum / $citiesCount;
            $averageCloudCover = $cloudCoverSum / $citiesCount;

            $statisticsWeatherArray = [
                'temperature' => [
                    'avrage' => number_format($averageTemp, 2, '.', ','),
                    'highest' => $highestTemp,
                    'lowest' => $lowestTemp
                ],

                'humidity' => [
                    'avrage' => number_format($averageHumidity, 2, '.', ','),
                    'highest' => $highestHumidity,
                    'lowest' => $lowestHumidity
                ],

                'wind' => [
                    'avrage' => number_format($averageSpeedWind, 2, '.', ','),
                    'highest' => $speediestWind,
                    'lowest' => $slowestWind
                ],

                'cloud' => [
                    'avrage' => number_format($averageCloudCover, 2, '.', ','),
                    'highest' => $mostCoveredByCloud,
                    'lowest' => $lessCoveredByCloud
                ],
            ];

            return $statisticsWeatherArray;
        } catch (Exception $exception) {
            throw $exception;
        }
    }

    public function comparison($cityWeather, $cityName, $statisticsWeatherData, $comparisonType)
    {
        if (isset($statisticsWeatherData['num'])) {
            if ($cityWeather == $statisticsWeatherData['num']) {
                $statisticsWeatherData['city'] = $statisticsWeatherData['city'] . ', ' . $cityName;
                return $statisticsWeatherData;
            }

            if ($comparisonType == 'more') {
                if ($cityWeather  > $statisticsWeatherData['num']) {
                    $statisticsWeatherData['city'] = $cityName;
                    $statisticsWeatherData['num'] = $cityWeather;
                }
            } else {
                if ($cityWeather < $statisticsWeatherData['num']) {
                    $statisticsWeatherData['city'] = $cityName;
                    $statisticsWeatherData['num'] = $cityWeather;
                }
            }
        } else {
            $statisticsWeatherData['city'] = $cityName;
            $statisticsWeatherData['num'] = $cityWeather;
        }

        return $statisticsWeatherData;
    }

    // scalability purpose: function named with number of minutes, if in the future I want to create another function with a different Time to live
    // readability purpose: function named with number of minutes.
    public function getTimeToLiveIn30Min()
    {
        //scalability purpose: create TimeToLiveInMinutesEnums enum class, if in the future I want to add a new time to live.
        // readability purpose: Enums are named with a number of minutes.
        return now()->addMinutes(TimeToLiveInMinutesEnums::Min_30->value);
    }
}
