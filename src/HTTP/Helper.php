<?php

declare(strict_types=1);

namespace NB\AppComponents\HTTP;

class Helper
{
    /**
     * 抄自GuzzleHTTP parse_query.
     *
     * 调整了空值策略,用空字符串取代null.
     *
     * @param string $str
     * @param bool|string $urlEncoding
     *
     * @return array
     */
    public static function parseQuery(string $str, $urlEncoding = true): array
    {
        $result = [];

        if ($str === '') {
            return $result;
        }

        if ($urlEncoding === true) {
            $decoder = function ($value) {
                return rawurldecode(str_replace('+', ' ', $value));
            };
        } elseif ($urlEncoding === PHP_QUERY_RFC3986) {
            $decoder = 'rawurldecode';
        } elseif ($urlEncoding === PHP_QUERY_RFC1738) {
            $decoder = 'urldecode';
        } else {
            $decoder = function ($str) { return $str; };
        }

        foreach (explode('&', $str) as $kvp) {
            $parts = explode('=', $kvp, 2);
            $key = $decoder($parts[0]);
            $value = isset($parts[1]) ? $decoder($parts[1]) : '';
            if (!isset($result[$key])) {
                $result[$key] = $value;
            } else {
                if (!is_array($result[$key])) {
                    $result[$key] = [$result[$key]];
                }
                $result[$key][] = $value;
            }
        }

        return $result;
    }
}