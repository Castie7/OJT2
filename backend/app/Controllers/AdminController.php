<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\UserModel;

class AdminController extends BaseController
{
    use ResponseTrait;

    private function validatePasswordStrength(string $password): ?string
    {
        if (strlen($password) < 10) {
            return 'Password must be at least 10 characters long.';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return 'Password must include at least one uppercase letter.';
        }

        if (!preg_match('/[a-z]/', $password)) {
            return 'Password must include at least one lowercase letter.';
        }

        if (!preg_match('/\d/', $password)) {
            return 'Password must include at least one number.';
        }

        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            return 'Password must include at least one special character.';
        }

        return null;
    }

    // GET /admin/users
    public function index()
    {
        // 🔒 SECURITY CHECK: Strict Admin Only
        // We check the session because we are using cookies
        if (session()->get('role') !== 'admin') {
             return $this->failForbidden('Access Denied: Admins only.');
        }

        // ❌ REMOVED: Manual CORS headers
        // The Global App\Filters\Cors handles this safely now.

        // 2. Fetch Users
        $userModel = new UserModel();
        
        // Select specific fields (security best practice: don't send passwords)
        $users = $userModel->select('id, name, email, role, created_at')
                           ->orderBy('created_at', 'DESC')
                           ->findAll();

        return $this->respond($users);
    }
    
    // POST /admin/reset-password
    public function resetPassword()
    {
        // 🔒 SECURITY CHECK
        if (session()->get('role') !== 'admin') {
             return $this->failForbidden('Access Denied');
        }

        // ❌ REMOVED: Manual CORS headers

        $json = $this->request->getJSON();

        if (!$json || !isset($json->user_id) || !isset($json->new_password)) {
            return $this->fail('Missing required fields', 400);
        }

        $newPassword = trim((string) $json->new_password);
        $passwordError = $this->validatePasswordStrength($newPassword);
        if ($passwordError !== null) {
            return $this->fail($passwordError, 422);
        }

        $userModel = new UserModel();
        
        // Verify user exists first
        if (!$userModel->find($json->user_id)) {
            return $this->failNotFound('User not found');
        }

        $userModel->update($json->user_id, [
            'password' => password_hash($newPassword, PASSWORD_DEFAULT)
        ]);

        return $this->respond(['status' => 'success', 'message' => 'Password reset successful']);
    }
}
