<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\API\ResponseTrait;

class AuthController extends BaseController
{
    use ResponseTrait;

    // ------------------------------------------------------------------
    // 1. LOGIN (Generate Token & Return Role)
    // ------------------------------------------------------------------
    public function login()
    {
        $this->handleCors();

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
                
                // PREPARE USER DATA (Include ID and Role)
                $userData = [
                    'id'   => $user['id'],
                    'name' => $user['name'],
                    'role' => $user['role'] // <--- CRITICAL FOR PERMISSIONS
                ];

                return $this->respond([
                    'status'  => 'success',
                    'user'    => $userData, // We send the whole object now
                    'token'   => $token, 
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
    // 2. VERIFY SESSION (Restore ID and Role)
    // ------------------------------------------------------------------
    public function verify()
    {
        $this->handleCors();

        $json = $this->request->getJSON();
        $token = $json->token ?? '';

        if (!$token) return $this->failUnauthorized('No token provided');

        $model = new UserModel();
        $user = $model->where('auth_token', $token)->first();

        if ($user) {
            // Restore full user context so refresh doesn't lose admin status
            $userData = [
                'id'   => $user['id'],
                'name' => $user['name'],
                'role' => $user['role']
            ];

            return $this->respond([
                'status' => 'success',
                'user'   => $userData
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
        $this->handleCors();

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
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-API-KEY, X-Requested-With");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method == "OPTIONS") {
            header("HTTP/1.1 200 OK");
            exit(); 
        }
    }
}