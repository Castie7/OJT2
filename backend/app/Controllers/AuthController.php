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
        helper(['activity']); // Load Logging Helper
    }

    // ------------------------------------------------------------------
    // 1. LOGIN
    // ------------------------------------------------------------------
    public function login()
    {
        try {
            $ip = $this->request->getIPAddress();
            
            // --- RATE LIMITING (THROTtLER) ---
            $throttler = \Config\Services::throttler();
            $maxAttempts = 5;
            $lockoutSecs = 60; // 1 minute lockout
            
            // Check if this IP is currently blocked
            if ($throttler->check('login_attempts_' . md5($ip), $maxAttempts, $lockoutSecs) === false) {
                // Determine how many seconds remain before they can try again
                $retryAfter = $throttler->getTokenTime(); // Usually returns time of the bucket replenish
                // Calculate simple remaining time for error message (rough estimate based on config)
                // For a more precise wait time, getTokenTime can be used if configured specifically, 
                // but just responding with the configured lockout is standard practice.
                 return $this->response
                    ->setStatusCode(429)
                    ->setJSON([
                    'status' => 'error',
                    'message' => "Too many failed attempts. Please try again later.",
                    'retry_after' => $lockoutSecs,
                    'csrf_token' => csrf_hash()
                ]);
            }

            $json = $this->request->getJSON();
            $email = $json->email ?? '';
            $password = $json->password ?? '';

            $user = $this->authService->login($email, $password);

            if ($user) {
                // Success: reset throttler intentionally if possible, though CI4 throttler 
                // doesn't have a direct 'clear' method. Valid logins will just pass.
                // We could let the token bucket decay naturally.

                // LOG ACTIVITY
                log_activity($user->id, $user->name, $user->role, 'LOGIN', "User logged in via email: $email");

                return $this->respond([
                    'status' => 'success',
                    'message' => 'Login Successful!',
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role
                    ],
                    'csrf_token' => csrf_hash()
                ]);
            }
            else {
                // Failure: the throttler check() above already registered a hit.
                // We just need to notify the user.
                return $this->failUnauthorized("Invalid email or password.");
            }
        }
        catch (\Throwable $e) {
            log_message('critical', '[Login Error] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
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
                'status' => 'guest',
                'message' => 'User is not logged in',
                'csrf_token' => csrf_hash()
            ]);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'user' => $sessionData,
            'csrf_token' => csrf_hash()
        ]);
    }

    // ------------------------------------------------------------------
    // 3. LOGOUT
    // ------------------------------------------------------------------
    public function logout()
    {
        // Get user before destroying session
        $userId = session()->get('id');
        $userName = session()->get('name');
        $role = session()->get('role');

        if ($userId) {
            log_activity($userId, $userName, $role, 'LOGOUT', 'User logged out');
        }

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

            // LOG ACTIVITY
            log_activity($currentUserId, session()->get('name'), $currentUserRole, 'UPDATE_PROFILE', "Updated profile for user ID: {$json->user_id}");

            return $this->respond([
                'status' => 'success',
                'message' => 'Account updated successfully',
                'user' => [
                    'id' => $updatedUser->id,
                    'name' => $updatedUser->name,
                    'email' => $updatedUser->email,
                    'role' => $updatedUser->role,
                ]
            ]);

        }
        catch (\Exception $e) {
            return $this->fail($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    // ------------------------------------------------------------------
    // 5. REGISTER
    // ------------------------------------------------------------------
    public function register()
    {
        if (!session()->get('isLoggedIn') || session()->get('role') !== 'admin') {
            return $this->failForbidden('Access Denied: Admins only.');
        }

        $json = $this->request->getJSON();

        if (!$json) {
            return $this->fail('No data provided', 400);
        }

        try {
            if (!isset($json->role) || !in_array($json->role, ['user', 'admin'], true)) {
                $json->role = 'user';
            }

            $this->authService->register($json);

            $adminId = session()->get('id');
            $adminName = session()->get('name');
            $adminRole = session()->get('role');

            log_activity($adminId, $adminName, $adminRole, 'REGISTER_USER', "Registered new user: " . ($json->email ?? 'unknown'));

            return $this->respondCreated([
                'status' => 'success',
                'message' => 'User added successfully'
            ]);
        }
        catch (\Throwable $e) {
            log_message('error', '[Register] Error: ' . $e->getMessage());

            // Handle specific codes if needed
            if ($e->getCode() == 409) {
                return $this->failResourceExists($e->getMessage());
            }
            return $this->failServerError($e->getMessage());
        }
    }
}
