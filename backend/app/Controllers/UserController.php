<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Services\UserService;

class UserController extends ResourceController
{
    use \CodeIgniter\API\ResponseTrait;

    protected $userService;

    public function __construct() {
        $this->userService = new UserService();
    }

    public function index()
    {
        // 🔒 SECURITY CHECK: Admins Only
        if (session()->get('role') !== 'admin') {
             return $this->failForbidden('Access Denied: Admins only.');
        }

        $role = $this->request->getGet('role');
        
        try {
            $data = $this->userService->getAllUsers($role);
            return $this->respond($data);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }
}