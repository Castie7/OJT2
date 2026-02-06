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
         * ⚠️ Do not use '*' here if supportsCredentials is true.
         * We leave this empty and use 'allowedOriginsPatterns' instead.
         */
        'allowedOrigins' => [],

        /**
         * --------------------------------------------------------------------------
         * Allowed Origins Patterns
         * --------------------------------------------------------------------------
         * ✅ FIX: This Regex allows:
         * 1. Localhost (for you)
         * 2. Any 192.168.x.x IP (for friends on your Wi-Fi)
         * 3. Any 10.x.x.x IP (for corporate/school networks)
         */
        'allowedOriginsPatterns' => [
            'https?://localhost:5173',
            'https?://127\.0\.0\.1:5173',
            'https?://192\.168\.\d{1,3}\.\d{1,3}:5173', // Matches 192.168.1.5, 192.168.100.20, etc.
            'https?://10\.\d{1,3}\.\d{1,3}\.\d{1,3}:5173', // Matches 10.0.0.5, etc.
        ],

        /**
         * --------------------------------------------------------------------------
         * Supports Credentials
         * --------------------------------------------------------------------------
         * Must be true for Cookies/Sessions to work.
         */
        'supportsCredentials' => true,

        'allowedHeaders' => ['*'],

        'exposedHeaders' => [],

        'allowedMethods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],

        'maxAge' => 7200,
    ];
}