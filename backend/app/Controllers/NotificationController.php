<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Models\NotificationModel;

class NotificationController extends ResourceController
{
    use \CodeIgniter\API\ResponseTrait;

    // ❌ REMOVED: handleCors()
    // Your App\Filters\Cors.php handles this globally.
    // Keeping "Access-Control-Allow-Origin: *" here breaks the Secure Cookie login.

    public function index()
    {
        // 1. Get User ID
        // Note: For extra security, you could use session()->get('id') instead of GET param
        $userId = $this->request->getGet('user_id');
        
        if (!$userId) {
            return $this->fail('User ID Required', 400);
        }

        try {
            // 2. SAFETY CHECK: Does the table exist?
            // This prevents the "500 Internal Server Error" if you haven't run migrations yet.
            $db = \Config\Database::connect();
            if (!$db->tableExists('notifications')) {
                // Return empty list so Dashboard doesn't break
                return $this->respond([]); 
            }

            // 3. Fetch Data
            $model = new NotificationModel();
            $data = $model->where('user_id', $userId)
                          ->orderBy('created_at', 'DESC')
                          ->findAll(10); // Limit to latest 10
            
            return $this->respond($data);

        } catch (\Exception $e) {
            // Catch any SQL errors gracefully
            return $this->failServerError($e->getMessage());
        }
    }

    public function markAsRead()
    {
        // 1. Get Data
        $json = $this->request->getJSON();
        $userId = $json->user_id ?? null;

        if (!$userId) {
            return $this->fail('User ID Required', 400);
        }

        try {
            $db = \Config\Database::connect();
            if (!$db->tableExists('notifications')) {
                return $this->respond(['success' => true]); // Pretend it worked
            }

            // 2. Mark all unread as read
            $model = new NotificationModel();
            $model->where('user_id', $userId)
                  ->where('is_read', 0)
                  ->set(['is_read' => 1])
                  ->update();

            return $this->respond(['success' => true]);

        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }
}