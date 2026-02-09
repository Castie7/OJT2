<?php

namespace App\Controllers;

use App\Services\AuthService;
use CodeIgniter\API\ResponseTrait;

class AuthController extends BaseController
{
    use ResponseTrait;

    protected $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    // ------------------------------------------------------------------
    // 1. LOGIN
    // ------------------------------------------------------------------
    public function login()
    {
        try {
            $json = $this->request->getJSON();
            $email = $json->email ?? '';
            $password = $json->password ?? '';

            $user = $this->authService->login($email, $password);

            if ($user) {
                return $this->respond([
                    'status'     => 'success',
                    'message'    => 'Login Successful!',
                    'user'       => [
                        'id'   => $user->id,
                        'name' => $user->name,
                        'role' => $user->role
                    ],
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
    // 2. VERIFY SESSION
    // ------------------------------------------------------------------
    public function verify()
    {
        $sessionData = $this->authService->verifySession();

        if (!$sessionData) {
            return $this->response->setJSON([
                'status'     => 'guest',
                'message'    => 'User is not logged in',
                'csrf_token' => csrf_hash()
            ]);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'user'   => $sessionData,
            'csrf_token' => csrf_hash()
        ]);
    }

    // ------------------------------------------------------------------
    // 3. LOGOUT
    // ------------------------------------------------------------------
    public function logout()
    {
        $this->authService->logout();
        return $this->respond(['status' => 'success', 'message' => 'Logged out successfully']);
    }

    // ------------------------------------------------------------------
    // 4. UPDATE PROFILE
    // ------------------------------------------------------------------
    public function updateProfile()
    {
        $json = $this->request->getJSON();
        if (!$json || !isset($json->user_id)) {
            return $this->failUnauthorized('Invalid request');
        }

        try {
            // Get current user from session (handled inside service or passed here?)
            // Service expects arguments. Best to passthrough session data from Controller context
            // since Service shouldn't strictly depend on global session if we want to be pure, 
            // but for now relying on Helper usage in Service is fine for CI4.
            // Actually, I passed `currentUserId` to `updateProfile` in AuthService.
            
            $currentUserId = session()->get('id');
            $currentUserRole = session()->get('role');

            $updatedUser = $this->authService->updateProfile($json->user_id, $json, $currentUserId, $currentUserRole);

            return $this->respond([
                'status' => 'success',
                'message' => 'Account updated successfully',
                'user' => [
                    'id'    => $updatedUser->id,
                    'name'  => $updatedUser->name,
                    'email' => $updatedUser->email,
                    'role'  => $updatedUser->role,
                ]
            ]);

        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    // ------------------------------------------------------------------
    // 5. REGISTER
    // ------------------------------------------------------------------
    public function register()
    {
        $json = $this->request->getJSON();

        if (!$json) {
            return $this->fail('No data provided', 400);
        }

        try {
            $this->authService->register($json);
            return $this->respondCreated([
                'status'  => 'success',
                'message' => 'User added successfully'
            ]);
        } catch (\Exception $e) {
            // Handle specific codes if needed
             if ($e->getCode() == 409) {
                 return $this->failResourceExists($e->getMessage());
             }
            return $this->failServerError($e->getMessage());
        }
    }
}
