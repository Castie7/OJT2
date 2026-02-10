<?php namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class SecureHeaders implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Do nothing before the request
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Add Security Headers
        //$response->setHeader('X-Frame-Options', 'DENY'); // Prevents Clickjacking
        //$response->setHeader('X-Content-Type-Options', 'nosniff'); // Prevents MIME sniffing
        //$response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        //$response->setHeader('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline';"); // Adjust strictly for Vue
        
        // HSTS (Strict-Transport-Security) - Tells browser to ONLY use HTTPS for next year
        //$response->setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}