<?php namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class Cors implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // ---------------------------------------------------------------------
        // 1. DYNAMIC ORIGIN HANDLING
        // ---------------------------------------------------------------------
        // Allow any origin that connects to us (useful for local dev with changing IPs).
        // In production, you might want to restrict this to specific domains.
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';

        // ---------------------------------------------------------------------
        // 2. SET HEADERS MANUALLY
        // ---------------------------------------------------------------------
        // We use raw header() calls to ensure they are sent regardless of what CI4 does later.
        header("Access-Control-Allow-Origin: {$origin}");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method, Authorization, X-CSRF-TOKEN");
        header("Access-Control-Allow-Credentials: true"); // Required for Cookies
        header("Access-Control-Max-Age: 3600"); // Cache this permission for 1 hour (Speed boost)

        // ---------------------------------------------------------------------
        // 3. HANDLE PREFLIGHT (OPTIONS)
        // ---------------------------------------------------------------------
        // If this is an OPTIONS request, exit immediately so CSRF doesn't block it.
        if ($request->getMethod(true) === 'OPTIONS') {
            header("HTTP/1.1 200 OK");
            exit(); 
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }
}