<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Models\UserModel;

class UserController extends ResourceController
{
    use \CodeIgniter\API\ResponseTrait;

    private function handleCors() {
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") {
            header("HTTP/1.1 200 OK");
            exit();
        }
    }

    public function index()
    {
        $this->handleCors();

        $role = $this->request->getGet('role');

        $model = new UserModel();
        try {
            if ($role) {
                $data = $model->where('role', $role)->select('id, name, role')->findAll();
            } else {
                $data = $model->select('id, name, role')->findAll();
            }
            return $this->respond($data);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }
}
