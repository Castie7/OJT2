<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Models\UserModel;

class UserController extends ResourceController
{
    use \CodeIgniter\API\ResponseTrait;

    // ❌ REMOVED: handleCors()
    // Your App\Filters\Cors.php handles this globally.
    // Keeping "Access-Control-Allow-Origin: *" here breaks the Secure Cookie login.

    public function index()
    {
        // 🔒 SECURITY CHECK: Admins Only
        if (session()->get('role') !== 'admin') {
             return $this->failForbidden('Access Denied: Admins only.');
        }

        $role = $this->request->getGet('role');
        $model = new UserModel();
        
        try {
            if ($role) {
                // Return users filtered by role
                $data = $model->where('role', $role)
                              ->select('id, name, email, role, created_at') // Added useful fields
                              ->findAll();
            } else {
                // Return all users
                $data = $model->select('id, name, email, role, created_at')
                              ->findAll();
            }
            return $this->respond($data);
            
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }
}