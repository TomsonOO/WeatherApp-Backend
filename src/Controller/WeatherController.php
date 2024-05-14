<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/', name: 'app_weather')]
class WeatherController extends AbstractController
{

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    #[Route('/solar_energy', name: 'get_energy', methods: ['GET'])]
    public function getSolarEnergy(Request $request): JsonResponse
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
            $weatherResponse = $this->httpClient->request('GET', 'https://api.open-meteo.com/v1/forecast', [
                'query' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'hourly' => ['temperature_2m', 'sunshine_duration'],
                    'daily' => ['weather_code', 'temperature_2m_max', 'temperature_2m_min'],
                    'timezone' => "auto",
                    'forecast_days' => 7
                ]
            ]);

            $weatherData = $weatherResponse->toArray();
            $maxTemp = $weatherData['daily']['temperature_2m_max'];
            $minTemp = $weatherData['daily']['temperature_2m_min'];
            $weatherCode = $weatherData['daily']['weather_code'];

            $sunshineDuration = $weatherData['hourly']['sunshine_duration'];
            $time = $weatherData['hourly']['time'];

            $dailyData = [];
            foreach ($time as $index => $timestamp) {
                $day = (new \DateTime($timestamp))->format('Y-m-d');
                if (!isset($dailyData[$day])) {
                    $dailyData[$day] = ['sunshine_duration' => 0];
                }
                $dailyData[$day]['sunshine_duration'] += $sunshineDuration[$index] / 3600;
            }

            $index = 0;
            foreach ($dailyData as $day => &$data) {
                $data['max_temperature'] = $maxTemp[$index] ?? null;
                $data['min_temperature'] = $minTemp[$index] ?? null;
                $data['weather_code'] = $weatherCode[$index] ?? null;
                $index++;
            }

            $panelEfficiency = 0.2;
            $solarPanelPower = 2.5;
            foreach ($dailyData as $day => &$data) {
                if (isset($data['sunshine_duration'])) {
                    $data['energy_generated'] = $this->calculateSolarEnergy($data['sunshine_duration'], $panelEfficiency, $solarPanelPower);
                } else {
                    $data['energy_generated'] = 0;
                }
            }

            return $this->json($dailyData);
        } catch (TransportExceptionInterface $e) {
            return $this->json(['error' => 'Failed to retrieve weather data: '.$e->getMessage()], 500);
        }
    }

    private function calculateSolarEnergy(float $sunshineDuration, float $panelEfficiency, float $solarPanelPower): float
    {
        return $sunshineDuration * $panelEfficiency * $solarPanelPower;
    }



}
