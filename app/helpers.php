<?php

use App\Services\GlobalSettingService;

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return app(GlobalSettingService::class)->get($key, $default);
    }
}
