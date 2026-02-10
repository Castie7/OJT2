<?php

namespace Config;

use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
// use CodeIgniter\Filters\SecureHeaders; // ❌ Disabled System Filter
use App\Filters\SecureHeaders;             // ✅ Uses YOUR custom filter

class Filters extends BaseFilters
{
    /**
     * Configures aliases for Filter classes to
     * make reading things nicer and simpler.
     */
    public array $aliases = [
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class, // Points to App\Filters\SecureHeaders
        'forcehttps'    => ForceHTTPS::class,
        'pagecache'     => PageCache::class,
        'performance'   => PerformanceMetrics::class,
        'throttler'     => \App\Filters\Throttler::class,
        
        // ✅ YOUR CUSTOM CORS FILTER
        'cors'          => \App\Filters\Cors::class, 
    ];

    /**
     * List of special required filters.
     * These run before everything else.
     */
    public array $required = [
        'before' => [
            //'forcehttps', // 🔒 Forces SSL (Good for your mkcert setup)
            'pagecache',  
        ],
        'after' => [
            'pagecache', 
            'performance', 
            'toolbar',   
        ],
    ];

    /**
     * List of filter aliases that are always
     * applied before and after every request.
     */
    public array $globals = [
        'before' => [
            // -----------------------------------------------------------------
            // 🛑 SECURITY PRIORITY 1: CORS
            // -----------------------------------------------------------------
            // Must be FIRST. It handles the "OPTIONS" preflight check and exits.
            // If CSRF runs before this, the handshake will fail.
            'cors', 
            
            // -----------------------------------------------------------------
            // 🛑 SECURITY PRIORITY 2: CSRF
            // -----------------------------------------------------------------
            // Protects against Cross-Site Request Forgery.
            // Your Vue app sends the X-CSRF-TOKEN header to pass this.
            'csrf', 

            // 3. Security Checks
            'invalidchars', 
        ],
        'after' => [
            // 4. Inject Security Headers (XSS, HSTS, etc.)
            'secureheaders',
        ],
    ];

    /**
     * List of filter aliases that works on a
     * particular HTTP method (GET, POST, etc.).
     */
    public array $methods = [];

    /**
     * List of filter aliases that should run on any
     * before or after URI patterns.
     */
    public array $filters = [
        // Limit API requests to 60 per minute per IP to prevent spam
        'throttler' => ['before' => ['api/*']], 
    ];
}