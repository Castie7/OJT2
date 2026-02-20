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
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }
}