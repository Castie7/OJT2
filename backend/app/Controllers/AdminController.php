<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\UserModel;

class AdminController extends BaseController
{
    use ResponseTrait;

    // GET /admin/users
    public function index()
    {
        // 1. Handle CORS
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: GET, OPTIONS");

        if ($this->request->getMethod() === 'options') {
            die();
        }

        // 2. Fetch Users
        $userModel = new UserModel();
        
        // Select specific fields (security best practice: don't send passwords)
        $users = $userModel->select('id, name, email, role, created_at')->findAll();

        return $this->respond($users);
    }
    
    // POST /admin/reset-password
    public function resetPassword()
    {
        // 1. Handle CORS
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: POST, OPTIONS");
        
        if ($this->request->getMethod() === 'options') {
            die();
        }

        $json = $this->request->getJSON();
        
        if (!isset($json->user_id) || !isset($json->new_password)) {
            return $this->fail('Missing required fields', 400);
        }

        $userModel = new UserModel();
        $userModel->update($json->user_id, [
            'password' => password_hash($json->new_password, PASSWORD_DEFAULT)
        ]);

        return $this->respond(['status' => 'success', 'message' => 'Password reset successful']);
    }
}