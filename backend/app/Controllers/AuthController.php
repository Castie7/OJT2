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
                    'message' => 'Login Successful!',
                    'user' => $user
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
                'email' => $user['email'],
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
        // 1. Handle CORS
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: POST, OPTIONS");
        if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") die();

        // 2. Get Token from Header
        $request = service('request');
        $token = $request->getHeaderLine('Authorization');

        if ($token) {
            $userModel = new UserModel();
            $user = $userModel->where('auth_token', $token)->first();
            
            // 3. Invalidate Token in Database
            if ($user) {
                $userModel->update($user['id'], ['auth_token' => null]);
            }
        }

        return $this->respond(['status' => 'success', 'message' => 'Logged out successfully']);
    }

    // ------------------------------------------------------------------
    // 4. UPDATE PROFILE
    // ------------------------------------------------------------------
    public function updateProfile()
    {
        // 1. CORS & Preflight (Standard Setup)
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

        if ($this->request->getMethod() === 'options') {
            return $this->response->setStatusCode(200);
        }

        // 2. Validate Request
        $json = $this->request->getJSON();
        if (!$json || !isset($json->user_id)) {
            return $this->failUnauthorized('Invalid request');
        }

        $model = new \App\Models\UserModel();
        $user = $model->find($json->user_id);

        if (!$user) {
            return $this->failNotFound('User not found');
        }

        // 3. Prepare Data (Partial Update Logic)
        $dataToUpdate = [];

        // Only update Name if it was sent and is not empty
        if (isset($json->name) && !empty(trim($json->name))) {
            $dataToUpdate['name'] = trim($json->name);
        }

        // Only update Email if it was sent and is not empty
        if (isset($json->email) && !empty(trim($json->email))) {
            $dataToUpdate['email'] = trim($json->email);
        }

        // 4. Password Change Logic (Independent)
        if (!empty($json->new_password)) {
            if (empty($json->current_password)) {
                return $this->fail('To change password, you must enter your Current Password.');
            }
            // Verify OLD password
            if (!password_verify($json->current_password, $user['password'])) {
                return $this->fail('Incorrect Current Password.');
            }
            // Set NEW password
            $dataToUpdate['password'] = password_hash($json->new_password, PASSWORD_DEFAULT);
        }

        // 5. Check if there is anything to update
        if (empty($dataToUpdate)) {
            return $this->fail('No changes were provided.');
        }

        // 6. Execute Update
        if ($model->update($json->user_id, $dataToUpdate)) {
            $updatedUser = $model->find($json->user_id);
            unset($updatedUser['password']); // Hide hash
            
            return $this->respond([
                'status' => 'success',
                'message' => 'Account updated successfully',
                'user' => $updatedUser
            ]);
        }

        return $this->fail('Database update failed');
    }

    // ------------------------------------------------------------------
    // 5. REGISTER (Add New User)
    // ------------------------------------------------------------------
    public function register()
    {
        // 1. Handle CORS Manually
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: POST, OPTIONS");
        
        if ($this->request->getMethod() === 'options') {
            die();
        }

        // 2. Get JSON data
        $json = $this->request->getJSON();

        if (!$json) {
            return $this->fail('No data provided', 400);
        }

        // 3. Validate Required Fields
        if (!isset($json->email) || !isset($json->password) || !isset($json->name)) {
            return $this->fail('Missing required fields (name, email, password)', 400);
        }

        $userModel = new UserModel();

        // 4. Check if email already exists
        if ($userModel->where('email', $json->email)->first()) {
            return $this->failResourceExists('Email already in use');
        }

        // 5. Prepare Data
        $data = [
            'name'     => $json->name,
            'email'    => $json->email,
            'password' => password_hash($json->password, PASSWORD_DEFAULT), // 🔒 Hash Password
            'role'     => $json->role ?? 'user', // Default to 'user' if not provided
        ];

        // 6. Insert into Database
        try {
            $userModel->insert($data);
            
            return $this->respondCreated([
                'status'  => 'success',
                'message' => 'User added successfully'
            ]);
        } catch (\Exception $e) {
            return $this->failServerError('Failed to create user: ' . $e->getMessage());
        }
    }
}