<?php namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class Cors implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // 1. Allow connections from ANYWHERE (*)
        header("Access-Control-Allow-Origin: *");
        
        // 2. Allow specific HTTP methods
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        
        // 3. Allow specific Headers (Content-Type and Authorization are crucial)
        header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method, Authorization");
        
        // 4. Handle the "Preflight" check immediately
        // If the browser asks "Can I connect?", we just say "Yes" and stop there.
        if ($request->getMethod() === 'options') {
            header("HTTP/1.1 200 OK");
            exit(); // Stop CodeIgniter from looking for a controller
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No action needed after the request
    }
}