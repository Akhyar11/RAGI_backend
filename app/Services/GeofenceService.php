<?php

namespace App\Services;

class GeofenceService
{
    public const EARTH_RADIUS_METERS = 6371000;

    /**
     * Menghitung jarak antara 2 koordinat (Latitude & Longitude) dalam satuan meter
     * menggunakan rumus Haversine.
     */
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lon1Rad = deg2rad($lon1);
        $lat2Rad = deg2rad($lat2);
        $lon2Rad = deg2rad($lon2);

        $latDelta = $lat2Rad - $lat1Rad;
        $lonDelta = $lon2Rad - $lon1Rad;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos($lat1Rad) * cos($lat2Rad) *
            sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round(self::EARTH_RADIUS_METERS * $c, 2);
    }

    /**
     * Memeriksa apakah koordinat pengguna berada dalam radius meter yang ditentukan dari kantor.
     */
    public function isWithinRadius(
        float $userLat,
        float $userLon,
        float $officeLat,
        float $officeLon,
        int|float $radiusMeters
    ): bool {
        $distance = $this->calculateDistance($userLat, $userLon, $officeLat, $officeLon);
        return $distance <= $radiusMeters;
    }
}
