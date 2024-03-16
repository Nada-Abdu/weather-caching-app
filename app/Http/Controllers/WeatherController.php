<?php

namespace App\Http\Controllers;


use Exception;
use App\Services\WeatherServices;
use App\Services\WeatherInterface;
use App\Http\Requests\CityWeatherRequest;
use App\Http\Requests\CitiesWeatherRequest;

class WeatherController extends Controller
{
    private $weatherService;

    public function __construct(WeatherServices $weatherService)
    {
        $this->weatherService = $weatherService;
    }


    // simplicity and purposes: Create GetCityWeatherRequest class to validate request parameters to make the controller as simple as possible.
    public function cityWeather(CityWeatherRequest $request)
    {
        try {

            //simplicity purpose: GetCityWeatherRequest class deals with validation errors and will return an appropriate message to the user
            $request->validated();

            // simplicity and maintainability purposes: Create WeatherServices class to holde the logic and make the controller as simple as possible.
            $cityWeather = $this->weatherService->getCityWeather($request->city);

            return response()->json($cityWeather, 200);
        } catch (Exception $exception) {
            return response()->json($exception->getMessage(), $exception->getCode());
        }
    }

    public function citiesWeather(CitiesWeatherRequest $request)
    {
        try {
            //simplicity purpose: GetCityWeatherRequest class deals with validation errors and will return an appropriate message to the user
            $request->validated();

            // simplicity and maintainability purposes: Create WeatherServices class to holde the logic and make the controller as simple as possible.
            $citiesWeather = $this->weatherService->getCitiesWeather($request->cities);

            return response()->json($citiesWeather, 200);
        } catch (Exception $exception) {
            return response()->json($exception->getMessage(), $exception->getCode());
        }
    }
    
    public function getStatisticsWeather()
    {
    }
}
