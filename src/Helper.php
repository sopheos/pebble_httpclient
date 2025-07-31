<?php

namespace Pebble\HttpClient;

class Helper
{
    public static function arrayLowerKey($data)
    {
        if (! is_array($data)) {
            return $data;
        }

        $out = [];
        foreach ($data as $key => $val) {
            $key = is_numeric($key) ? $key : mb_strtolower($key);
            $out[$key] = self::arrayLowerKey($val);
        }

        return $out;
    }
}
