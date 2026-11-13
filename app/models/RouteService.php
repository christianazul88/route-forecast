<?php
declare(strict_types=1);

final class RouteService
{
    private const PROFILE = 'driving-hgv';
    private const ENDPOINT = 'https://api.heigit.org/openrouteservice/v2/directions/driving-hgv';

    public static function route(array $coordinates): array
    {
        $hash = hash('sha256', self::PROFILE . '|' . json_encode($coordinates, JSON_THROW_ON_ERROR));
        $statement = db()->prepare('SELECT response_json FROM ors_cache WHERE route_hash = ? AND expires_at > NOW()');
        $statement->execute([$hash]);
        $cached = $statement->fetchColumn();
        if ($cached !== false) {
            try {
                return self::normalize(json_decode($cached, true, 512, JSON_THROW_ON_ERROR), true);
            } catch (Throwable $error) {
                error_log('Invalid ORS cache row ' . $hash . ': ' . $error->getMessage());
                db()->prepare('DELETE FROM ors_cache WHERE route_hash = ?')->execute([$hash]);
            }
        }

        $key = trim((string) config('ORS_API_KEY', ''));
        if ($key === '') {
            throw new RoutingException('Routing is not configured yet. Ask an admin to add the free ORS API key to the server configuration.', 503);
        }
        if (!extension_loaded('curl')) {
            throw new RoutingException('The PHP cURL extension is required for routing.', 503);
        }

        $body = json_encode(['coordinates' => $coordinates, 'instructions' => false, 'elevation' => false], JSON_THROW_ON_ERROR);
        $handle = curl_init(self::ENDPOINT);
        if ($handle === false) {
            throw new RoutingException('Could not start the routing request.', 503);
        }
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Authorization: ' . $key, 'Accept: application/json', 'Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $curlError = curl_error($handle);
        curl_close($handle);
        if ($response === false) {
            error_log('ORS transport error: ' . $curlError);
            throw new RoutingException('The routing service is temporarily unreachable. Try again shortly.', 503);
        }
        if ($status === 429) {
            throw new RoutingException('The free routing service is busy. Wait a minute and try again.', 429);
        }
        if ($status === 401 || $status === 403) {
            throw new RoutingException('The ORS API key was rejected. Ask an admin to check the key.', 503);
        }
        if ($status === 400 || $status === 404 || $status === 422) {
            throw new RoutingException('ORS could not find a drivable truck route for these branches. Check their map pins.', 422);
        }
        if ($status < 200 || $status >= 300) {
            error_log('ORS returned HTTP ' . $status . ': ' . substr($response, 0, 500));
            throw new RoutingException('The routing service is temporarily unavailable. Try again shortly.', 503);
        }

        try {
            $parsed = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            $route = self::normalize($parsed, false);
        } catch (Throwable $error) {
            error_log('Invalid ORS response: ' . $error->getMessage());
            throw new RoutingException('The routing service returned an unexpected result. Try again.', 502);
        }
        $hours = (int) Setting::number('ors_cache_hours', 168);
        $hours = max(1, min(720, $hours));
        try {
            $statement = db()->prepare('INSERT INTO ors_cache (route_hash, response_json, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR)) ON DUPLICATE KEY UPDATE response_json = VALUES(response_json), created_at = NOW(), expires_at = VALUES(expires_at)');
            $statement->execute([$hash, $response, $hours]);
        } catch (PDOException $error) {
            error_log('Could not cache ORS response: ' . $error->getMessage());
        }
        return $route;
    }

    private static function normalize(array $response, bool $cached): array
    {
        $route = $response['routes'][0] ?? null;
        if (!is_array($route) || !is_string($route['geometry'] ?? null) || $route['geometry'] === '' ||
            !is_numeric($route['summary']['distance'] ?? null) || !is_numeric($route['summary']['duration'] ?? null)) {
            throw new UnexpectedValueException('Missing route geometry or summary.');
        }
        $legs = [];
        foreach ($route['segments'] ?? [] as $segment) {
            if (!is_numeric($segment['distance'] ?? null) || !is_numeric($segment['duration'] ?? null)) {
                $legs = [];
                break;
            }
            $legs[] = [
                'distance_m' => (float) $segment['distance'],
                'duration_s' => (float) $segment['duration'],
            ];
        }
        return [
            'geometry' => $route['geometry'],
            'distance_m' => (float) $route['summary']['distance'],
            'duration_s' => (float) $route['summary']['duration'],
            'legs' => $legs,
            'cached' => $cached,
        ];
    }
}
