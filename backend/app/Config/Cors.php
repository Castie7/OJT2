<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Cors extends BaseConfig
{
    public array $default = [
        /**
         * --------------------------------------------------------------------------
         * Allowed Origins
         * --------------------------------------------------------------------------
         * ✅ FIX: Change this to ['*'] to allow ALL devices (laptops, phones)
         * to connect during development.
         */
        'allowedOrigins' => ['*'], 

        'allowedOriginsPatterns' => [],

        'supportsCredentials' => true,

        'allowedHeaders' => ['*'], 

        'exposedHeaders' => [],

        'allowedMethods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],

        'maxAge' => 7200,
    ];
}