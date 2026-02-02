<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Models\NotificationModel;

class NotificationController extends ResourceController
{
    use \CodeIgniter\API\ResponseTrait;

    // --- CORS HELPER ---
    private function handleCors() {
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With"); 
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        
        // Handle Pre-flight
        if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") {
            header("HTTP/1.1 200 OK");
            exit();
        }
    }

    public function index()
    {
        $this->handleCors(); // <--- CRITICAL

        $userId = $this->request->getGet('user_id');
        if (!$userId) return $this->fail('User ID Required');

        $model = new NotificationModel();
        // Check if model works
        try {
            $data = $model->where('user_id', $userId)
                          ->orderBy('created_at', 'DESC')
                          ->findAll(10);
            return $this->respond($data);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }

    public function markAsRead()
    {
        $this->handleCors(); // <--- CRITICAL

        $json = $this->request->getJSON();
        $userId = $json->user_id ?? null;

        if (!$userId) return $this->fail('User ID Required');

        $model = new NotificationModel();
        $model->where('user_id', $userId)
              ->where('is_read', 0)
              ->set(['is_read' => 1])
              ->update();

        return $this->respond(['success' => true]);
    }
}