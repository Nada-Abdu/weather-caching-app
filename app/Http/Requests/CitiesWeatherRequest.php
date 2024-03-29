<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CitiesWeatherRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [

            'cities' => ['required', 'array' , 'min:1'],
            'cities.*' => ['required', 'string', 'max:100']
        ];
    }

    public function messages()
    {
        return [
            'cities.min' => 'City array must contain at least one string city name',
            'cities.required' => 'City array is required !',
            'cities.array' => 'City must be an array !',
            'cities.*.required' => 'City name is required !',
            'cities.*.string' => 'City name must be string !',
            'cities.*.max' => 'Maximum length of city name is 100 characters',
        ];
    }
}
