<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CityWeatherRequest extends FormRequest
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
            'city' => ['required', 'string', 'max:100']
        ];
    }

    public function messages()
    {
        return [
            'city.required' => 'City name is required !',
            'city.string' => 'City name must be string !',
            'city.max' => 'Maximum length of city name is 100 characters',
        ];
    }

    public function all($keys = null)
    {
        // Include the route parameter 'city' in the input data
        return array_merge(parent::all(), $this->route()->parameters());
    }

}
