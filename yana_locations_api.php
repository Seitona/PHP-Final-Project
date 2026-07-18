<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$base_url = 'https://psgc.rootscratch.com';
$action = $_GET['action'] ?? 'regions';
$region = trim((string) ($_GET['region'] ?? ''));
$province = trim((string) ($_GET['province'] ?? ''));
$city = trim((string) ($_GET['city'] ?? ''));

function yana_location_response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function yana_fetch_locations(string $url): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Accept: application/json\r\nUser-Agent: Party4U/1.0\r\n",
            'timeout' => 10,
        ],
    ]);

    $json = @file_get_contents($url, false, $context);

    if ($json === false && function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'Party4U/1.0',
            CURLOPT_TIMEOUT => 10,
        ]);
        $json = curl_exec($curl);
        curl_close($curl);
    }

    if (!is_string($json) || $json === '') {
        yana_location_response(502, ['error' => 'Unable to load location list right now.']);
    }

    $payload = json_decode($json, true);
    if (!is_array($payload)) {
        yana_location_response(502, ['error' => 'Location service returned an invalid response.']);
    }

    if (isset($payload['data']) && is_array($payload['data'])) {
        return $payload['data'];
    }

    return $payload;
}

function yana_normalize_locations(array $items): array
{
    $locations = [];

    foreach ($items as $item) {
        if (!is_array($item) || empty($item['name'])) {
            continue;
        }

        $code = (string) ($item['code'] ?? $item['psgc_id'] ?? $item['correspondence_code'] ?? '');
        if ($code === '') {
            continue;
        }

        $locations[] = [
            'code' => $code,
            'name' => (string) $item['name'],
            'type' => (string) ($item['type'] ?? $item['geographic_level'] ?? $item['status'] ?? ''),
        ];
    }

    usort($locations, static function (array $a, array $b): int {
        return strcasecmp($a['name'], $b['name']);
    });

    return $locations;
}

$path = '';

if ($action === 'regions') {
    $path = '/region';
} elseif ($action === 'provinces' && $region !== '') {
    $path = '/province?id=' . rawurlencode($region);
} elseif ($action === 'cities' && $province !== '') {
    $path = '/municipal-city?id=' . rawurlencode($province);
} elseif ($action === 'cities' && $region !== '') {
    $path = '/municipal-city?id=' . rawurlencode($region);
} elseif ($action === 'barangays' && $city !== '') {
    $path = '/barangay?id=' . rawurlencode($city);
} else {
    yana_location_response(400, ['error' => 'Invalid location request.']);
}

$items = yana_fetch_locations($base_url . $path);
yana_location_response(200, ['locations' => yana_normalize_locations($items)]);
