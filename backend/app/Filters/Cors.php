<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class Cors implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
<<<<<<< HEAD
        // ---------------------------------------------------------------------
        // 1. DYNAMIC ORIGIN HANDLING
        // ---------------------------------------------------------------------
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';

        // Set headers on the Response object so Exceptions retain them in CI4
        $res = service('response');
        $res->setHeader('Access-Control-Allow-Origin', $origin)
            ->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, DELETE, PATCH')
            ->setHeader('Access-Control-Allow-Headers', 'X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method, Authorization, X-CSRF-TOKEN')
            ->setHeader('Access-Control-Allow-Credentials', 'true')
            ->setHeader('Access-Control-Max-Age', '3600');

        // Keep raw headers specifically for the OPTIONS exit below
        header("Access-Control-Allow-Origin: {$origin}");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE, PATCH");
        header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method, Authorization, X-CSRF-TOKEN");
        header("Access-Control-Allow-Credentials: true"); // Required for Cookies
        header("Access-Control-Max-Age: 3600");

        // ---------------------------------------------------------------------
        // 3. HANDLE PREFLIGHT (OPTIONS)
        // ---------------------------------------------------------------------
        // If this is an OPTIONS request, exit immediately so CSRF doesn't block it.
        if ($request->getMethod(true) === 'OPTIONS') {
            header("HTTP/1.1 200 OK");
            exit(); 
=======
        $origin = $request->getHeaderLine('Origin');
        $corsConfig = config('Cors');
        $settings = $corsConfig->default ?? [];

        if ($request->getMethod(true) !== 'OPTIONS') {
            return null;
>>>>>>> 2f65e60 (Added Security Feature (see the security_test_checklist.md for more information)
        }

        $response = service('response');

        if (!$this->isOriginAllowed($origin, $settings)) {
            return $response->setStatusCode(403);
        }

        $this->applyCorsHeaders($response, $origin, $settings);

        return $response->setStatusCode(204);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $origin = $request->getHeaderLine('Origin');
        $corsConfig = config('Cors');
        $settings = $corsConfig->default ?? [];

        if ($this->isOriginAllowed($origin, $settings)) {
            $this->applyCorsHeaders($response, $origin, $settings);
        }
    }

    private function isOriginAllowed(string $origin, array $settings): bool
    {
        if ($origin === '') {
            return false;
        }

        $allowedOrigins = $settings['allowedOrigins'] ?? [];
        if (in_array($origin, $allowedOrigins, true)) {
            return true;
        }

        $allowedPatterns = $settings['allowedOriginsPatterns'] ?? [];
        foreach ($allowedPatterns as $pattern) {
            $regex = '#^' . $pattern . '$#i';
            if (@preg_match($regex, $origin) === 1) {
                return true;
            }
        }

        return false;
    }

    private function applyCorsHeaders(ResponseInterface $response, string $origin, array $settings): void
    {
        $allowedMethods = $settings['allowedMethods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        $allowedHeaders = $settings['allowedHeaders'] ?? ['Content-Type', 'Authorization', 'X-CSRF-TOKEN'];
        $exposedHeaders = $settings['exposedHeaders'] ?? [];
        $supportsCredentials = (bool) ($settings['supportsCredentials'] ?? false);
        $maxAge = (int) ($settings['maxAge'] ?? 0);

        $response->setHeader('Vary', 'Origin');
        $response->setHeader('Access-Control-Allow-Origin', $origin);
        $response->setHeader('Access-Control-Allow-Methods', implode(', ', $allowedMethods));
        $response->setHeader('Access-Control-Allow-Headers', implode(', ', $allowedHeaders));

        if (!empty($exposedHeaders)) {
            $response->setHeader('Access-Control-Expose-Headers', implode(', ', $exposedHeaders));
        }

        if ($supportsCredentials) {
            $response->setHeader('Access-Control-Allow-Credentials', 'true');
        }

        if ($maxAge > 0) {
            $response->setHeader('Access-Control-Max-Age', (string) $maxAge);
        }
    }
}
