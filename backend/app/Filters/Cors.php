<?php namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class Cors implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method, Authorization");
        
        // ---------------------------------------------------------------------
        // ✅ FIX: Use "strtoupper" to ensure we catch 'OPTIONS' or 'options'
        // ---------------------------------------------------------------------
        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        
        if ($method === 'OPTIONS') {
            header("HTTP/1.1 200 OK");
            exit(); // KILL THE SCRIPT. Do not let CodeIgniter continue.
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }
}