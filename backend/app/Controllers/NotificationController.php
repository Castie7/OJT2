<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Services\NotificationService;

class NotificationController extends ResourceController
{
    use \CodeIgniter\API\ResponseTrait;

    protected $notifService;

    public function __construct() {
        $this->notifService = new NotificationService();
    }

    public function index()
    {
        $userId = $this->request->getGet('user_id');
        
        if (!$userId) {
            return $this->fail('User ID Required', 400);
        }

        try {
            $data = $this->notifService->getUserNotifications($userId);
            return $this->respond($data);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    public function markAsRead()
    {
        $json = $this->request->getJSON();
        $userId = $json->user_id ?? null;

        if (!$userId) {
            return $this->fail('User ID Required', 400);
        }

        try {
            $this->notifService->markAsRead($userId);
            return $this->respond(['success' => true]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }
}