<?php

namespace XWMS\Package\Services;

use Stevebauman\Location\Facades\Location;

class IpService
{
    public static function getIpData(): array
    {
        $ip = request()->ip();
        if (in_array($ip, ['127.0.0.1', '::1'])) {
            return [
                'ipaddress' => '127.0.0.1',
                'status' => 200,
                'city' => 'NoMansCity',
                'region' => 'NoMansLand',
                'countrycode' => 'NL',
                'countryname' => 'No Mans Land',
                'lat' => '51.8562',
                'long' => '9.3651',
            ];
        }

        $position = Location::get($ip);

        if (!$position) {
            return [
                'ipaddress' => $ip,
                'status' => 206,
                'city' => '',
                'region' => '',
                'countrycode' => 'NL',
                'countryname' => '',
                'btw' => null,
                'lat' => '',
                'long' => '',
            ];
        }

        return [
            'ipaddress' => $position->ip,
            'status' => 200,
            'city' => $position->cityName,
            'region' => $position->regionName,
            'countrycode' => $position->countryCode,
            'countryname' => $position->countryName,
            'lat' => $position->latitude,
            'long' => $position->longitude,
        ];
    }
}