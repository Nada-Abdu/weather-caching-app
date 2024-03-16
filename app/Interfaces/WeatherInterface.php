<?php

namespace App\Interfaces;

interface WeatherInterface
{
    public function getCityWeather($city);
    public function getCitiesWeather($cities);
    public function getStatisticsWeather();
    public function handleAPIError($errorCode);
}
