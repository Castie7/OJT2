<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\API\ResponseTrait;

class AuthController extends BaseController
{
    use ResponseTrait;

    public function __construct()
    {
        // Load the Cookie Helper for all methods
        helper('cookie');
    }

    // ------------------------------------------------------------------
    // 1. LOGIN (Generate Session & Return CSRF)
    // ------------------------------------------------------------------
    public function login()
    {
        try {
            $json = $this->request->getJSON();
            $email = $json->email ?? '';
            $password = $json->password ?? '';

            $model = new UserModel();
            $user = $model->where('email', $email)->first();

            // Verify Password
            if ($user && password_verify($password, $user['password'])) {
                
                // A. SET SESSION DATA (Crucial for Database Sessions)
                // This populates the ci_sessions table so verify() can find us later.
                $sessionData = [
                    'id'         => $user['id'],
                    'name'       => $user['name'],
                    'email'      => $user['email'],
                    'role'       => $user['role'],
                    'isLoggedIn' => true,
                ];
                session()->set($sessionData);

                // B. PREPARE RESPONSE DATA
                $userData = [
                    'id'   => $user['id'],
                    'name' => $user['name'],
                    'role' => $user['role']
                ];

                return $this->respond([
                    'status'     => 'success',
                    'message'    => 'Login Successful!',
                    'user'       => $userData,
                    
                    // ✅ CRITICAL FIX: Send CSRF Token in JSON
                    // This allows your App.vue "Fail-Safe" to grab it if the cookie fails.
                    'csrf_token' => csrf_hash() 
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
        // Check if user session exists in the database
        if (!session()->get('isLoggedIn')) {
            // Return 200 OK for guests so the browser accepts the new CSRF cookie
            return $this->response->setJSON([
                'status'     => 'guest',
                'message'    => 'User is not logged in',
                'csrf_token' => csrf_hash() // Send new token for the login form
            ]);
        }

        // User is logged in
        return $this->response->setJSON([
            'status' => 'success',
            'user'   => [
                'id'    => session()->get('id'),
                'name'  => session()->get('name'),
                'role'  => session()->get('role'),
                'email' => session()->get('email'),
            ],
            // ✅ CRITICAL FIX: Rotate token on verify to keep connection fresh
            'csrf_token' => csrf_hash()
        ]);
    }

    // ------------------------------------------------------------------
    // 3. LOGOUT (Destroy Session)
    // ------------------------------------------------------------------
    public function logout()
    {
        // 1. Destroy the Database Session
        session()->destroy();

        // 2. Remove Cookies (Cleanup)
        // We delete the session cookie and the CSRF cookie to force a fresh start
        $sessionName = config('Session')->cookieName;
        $csrfName    = config('Security')->cookieName;

        delete_cookie($sessionName);
        delete_cookie($csrfName);

        return $this->respond(['status' => 'success', 'message' => 'Logged out successfully']);
    }

    // ------------------------------------------------------------------
    // 4. UPDATE PROFILE
    // ------------------------------------------------------------------
    public function updateProfile()
    {
        // 1. Validate Request
        $json = $this->request->getJSON();
        if (!$json || !isset($json->user_id)) {
            return $this->failUnauthorized('Invalid request');
        }

        // 2. Security: Ensure the user modifying the profile is the one logged in
        // We use the Session now, which is much more secure than the old manual token.
        $currentUserId = session()->get('id');
        $currentUserRole = session()->get('role');

        // Check if ID matches OR if user is Admin
        if ($currentUserId != $json->user_id && $currentUserRole !== 'admin') {
             return $this->failForbidden('You are not allowed to edit this profile.');
        }

        $model = new UserModel();
        
        // 3. Find User to Update
        $user = $model->find($json->user_id);
        if (!$user) return $this->failNotFound('User not found');

        // 4. Prepare Data
        $dataToUpdate = [];

        if (isset($json->name) && !empty(trim($json->name))) {
            $dataToUpdate['name'] = trim($json->name);
        }
        if (isset($json->email) && !empty(trim($json->email))) {
            $dataToUpdate['email'] = trim($json->email);
        }

        // 5. Password Logic
        if (!empty($json->new_password)) {
            if (empty($json->current_password)) {
                return $this->fail('To change password, you must enter your Current Password.');
            }
            if (!password_verify($json->current_password, $user['password'])) {
                return $this->fail('Incorrect Current Password.');
            }
            $dataToUpdate['password'] = password_hash($json->new_password, PASSWORD_DEFAULT);
        }

        if (empty($dataToUpdate)) {
            return $this->fail('No changes were provided.');
        }

        // 6. Execute Update
        if ($model->update($json->user_id, $dataToUpdate)) {
            $updatedUser = $model->find($json->user_id);
            unset($updatedUser['password']); 
            
            // Update Session if we changed our own name/email
            if ($currentUserId == $json->user_id) {
                session()->set([
                    'name' => $updatedUser['name'],
                    'email' => $updatedUser['email']
                ]);
            }
            
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
        $json = $this->request->getJSON();

        if (!$json) {
            return $this->fail('No data provided', 400);
        }

        if (!isset($json->email) || !isset($json->password) || !isset($json->name)) {
            return $this->fail('Missing required fields (name, email, password)', 400);
        }

        $userModel = new UserModel();

        if ($userModel->where('email', $json->email)->first()) {
            return $this->failResourceExists('Email already in use');
        }

        $data = [
            'name'     => $json->name,
            'email'    => $json->email,
            'password' => password_hash($json->password, PASSWORD_DEFAULT),
            'role'     => $json->role ?? 'user', 
        ];

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