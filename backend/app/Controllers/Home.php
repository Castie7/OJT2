<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
        return view('welcome_message');
    }

    public function testApi()
    {
        // PERMISSION HEADERS
        // Allow Vue (running on port 5173) to talk to us
        header('Access-Control-Allow-Origin: *'); 
        header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        
        // Handle the "Pre-flight" check (Browser asks "Can I talk?")
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method == "OPTIONS") {
            die();
        }

        $data = [
            'status' => 'success',
            'message' => 'Hello from CodeIgniter 4 Backend!',
            'timestamp' => date('Y-m-d H:i:s')
        ];

        return $this->response->setJSON($data);
    }
}