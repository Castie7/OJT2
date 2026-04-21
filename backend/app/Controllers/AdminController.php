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
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
            'must_change_password' => 1, // Force user to set their own password on next login
        ]);

        // 🔒 FORCE IMMEDIATE LOGOUT: Destroy all active sessions for the target user.
        // The ci_sessions table stores serialized PHP data. We search for 'id|'
        // followed by the user ID pattern in the session data blob.
        $db = \Config\Database::connect();
        $targetUserId = (int) $json->user_id;
        $currentSessionId = session_id();

        // Find and delete all sessions belonging to this user (but not the admin's own session)
        $sessions = $db->table('ci_sessions')->get()->getResultArray();
        foreach ($sessions as $sess) {
            // Skip the admin's own session
            if ($sess['id'] === $currentSessionId) {
                continue;
            }
            // Check if this session belongs to the target user
            $data = $sess['data'] ?? '';
            if (str_contains($data, "id|i:{$targetUserId};") || 
                str_contains($data, "\"id\";i:{$targetUserId};")) {
                $db->table('ci_sessions')->where('id', $sess['id'])->delete();
            }
        }

        return $this->respond(['status' => 'success', 'message' => 'Password reset successful. User has been logged out.']);
    }
}
