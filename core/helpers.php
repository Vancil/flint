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

if (!function_exists('cache')) {
    /** Get the cache singleton from the container. */
    function cache(): \Flint\Cache\Cache
    {
        return $GLOBALS['__flint_app']->make(\Flint\Cache\Cache::class);
    }
}

if (!function_exists('session')) {
    /** Get the session singleton from the container. */
    function session(): \Flint\Session
    {
        return $GLOBALS['__flint_app']->make(\Flint\Session::class);
    }
}

if (!function_exists('old')) {
    /** Get an old input value from the previous request's flash data. */
    function old(string $key, mixed $default = null): mixed
    {
        if (!isset($GLOBALS['__flint_app'])) {
            return $default;
        }
        $input = $GLOBALS['__flint_app']->make(\Flint\Session::class)->getFlash('_old_input', []);
        return $input[$key] ?? $default;
    }
}

if (!function_exists('csrf_field')) {
    /** Return the CSRF hidden input field HTML. */
    function csrf_field(): string
    {
        if (!isset($GLOBALS['__flint_app'])) {
            return '';
        }
        return $GLOBALS['__flint_app']->make(\Flint\Csrf::class)->tokenField();
    }
}

if (!function_exists('csrf_token')) {
    /** Return the raw CSRF token string. */
    function csrf_token(): string
    {
        if (!isset($GLOBALS['__flint_app'])) {
            return '';
        }
        return $GLOBALS['__flint_app']->make(\Flint\Csrf::class)->token();
    }
}
