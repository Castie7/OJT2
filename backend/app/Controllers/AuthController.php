<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\API\ResponseTrait;

class AuthController extends BaseController
{
    use ResponseTrait;

    // ------------------------------------------------------------------
    // 1. LOGIN (Generate Token)
    // ------------------------------------------------------------------
    public function login()
    {
        $this->handleCors(); // <--- 1. Check CORS first. If it's OPTIONS, script dies here.

        try {
            $json = $this->request->getJSON();
            $email = $json->email ?? '';
            $password = $json->password ?? '';

            $model = new UserModel();
            $user = $model->where('email', $email)->first();

            // Verify Password
            if ($user && password_verify($password, $user['password'])) {
                
                // GENERATE SECURE TOKEN
                $token = bin2hex(random_bytes(32));

                // Save Token to Database
                $model->update($user['id'], ['auth_token' => $token]);

                return $this->respond([
                    'status' => 'success',
                    'user' => $user['name'],
                    'token' => $token, 
                    'message' => 'Login Successful!'
                ]);
            } else {
                return $this->failUnauthorized('Invalid email or password');
            }
        } catch (\Throwable $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // 2. VERIFY SESSION (Check Token)
    // ------------------------------------------------------------------
    public function verify()
    {
        $this->handleCors(); // <--- CORS Check

        $json = $this->request->getJSON();
        $token = $json->token ?? '';

        if (!$token) return $this->failUnauthorized('No token provided');

        $model = new UserModel();
        $user = $model->where('auth_token', $token)->first();

        if ($user) {
            return $this->respond([
                'status' => 'success',
                'user' => $user['name']
            ]);
        } else {
            return $this->failUnauthorized('Invalid or expired token');
        }
    }

    // ------------------------------------------------------------------
    // 3. LOGOUT (Destroy Token)
    // ------------------------------------------------------------------
    public function logout()
    {
        $this->handleCors(); // <--- CORS Check

        $json = $this->request->getJSON();
        $token = $json->token ?? '';

        if ($token) {
            $model = new UserModel();
            $user = $model->where('auth_token', $token)->first();
            
            if ($user) {
                $model->update($user['id'], ['auth_token' => null]);
            }
        }
        
        return $this->respond(['status' => 'success']);
    }

    // ------------------------------------------------------------------
    // Helper: BRUTE FORCE CORS HANDLE
    // ------------------------------------------------------------------
    private function handleCors() {
        // 1. Allow access from anywhere
        header('Access-Control-Allow-Origin: *');
        
        // 2. Allow specific headers (Authorization is crucial for your tokens)
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-API-KEY, X-Requested-With");
        
        // 3. Allow methods
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        
        // 4. THE FIX: If it's an OPTIONS request, STOP EVERYTHING and say "OK"
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method == "OPTIONS") {
            header("HTTP/1.1 200 OK");
            exit(); // <--- This prevents the framework from loading more logic and crashing
        }
    }
}