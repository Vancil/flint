<?php
declare(strict_types=1);

if (!function_exists('env')) {
    /** Get an environment variable with an optional default. */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}

if (!function_exists('config')) {
    /** Get a config value using dot notation: config('app.debug'). */
    function config(string $key, mixed $default = null): mixed
    {
        static $config = [];

        if (empty($config) && defined('BASE_PATH')) {
            foreach (glob(BASE_PATH . '/config/*.php') as $file) {
                $name = basename($file, '.php');
                $config[$name] = require $file;
            }
        }

        $segments = explode('.', $key);
        $value = $config;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
