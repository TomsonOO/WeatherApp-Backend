<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api', name: 'app_weather')]
class WeatherController extends AbstractController
{

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    #[Route('/weather', name: 'api_weather', methods: ['GET'])]
    public function getWeather(Request $request): JsonResponse
    {
        $latitude = $request->query->get('latitude');
        $longitude = $request->query->get('longitude');


        if(!$latitude || !$longitude)
        {
            return $this->json(['error' => 'Latitude and longitude are required'], 400);
        }

        if (!is_numeric($latitude) || !is_numeric($longitude))
        {
            return $this->json(['error' => 'Invalid latitude or longitude'], 400);
        }

        try {
            $response = $this->httpClient->request('GET', 'https://api.open-meteo.com/v1/forecast', [
                'query' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'hourly' => ['temperature_2m', 'weather_code', 'sunshine_duration'],
                    'timezone' => "Europe/Berlin",
                    'forecast_days' => 1
                ]
            ]);

            $weatherData = $response->toArray();
            return $this->json($weatherData);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to retrieve weather data'], 500);
        }
    }



}
