<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class Cors implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $origin = $request->getHeaderLine('Origin');
        $corsConfig = config('Cors');
        $settings = $corsConfig->default ?? [];

        if ($request->getMethod(true) !== 'OPTIONS') {
            return null;
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
