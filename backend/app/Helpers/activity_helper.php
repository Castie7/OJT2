<?php

use App\Models\ActivityLogModel;

if (!function_exists('log_activity')) {
    /**
     * Log a user activity to the database.
     *
     * @param int|null $userId
     * @param string $userName
     * @param string $role
     * @param string $action (e.g., 'LOGIN', 'CREATE')
     * @param string|null $details
     */
    function log_activity($userId, $userName, $role, $action, $details = null)
    {
        $logModel = new ActivityLogModel();
        
        $data = [
            'user_id'    => $userId,
            'user_name'  => $userName ?: 'Guest',
            'role'       => $role ?: 'guest',
            'action'     => strtoupper($action),
            'details'    => $details,
            'ip_address' => service('request')->getIPAddress(),
        ];

        try {
            $logModel->insert($data);
        } catch (\Exception $e) {
            // Silently fail or log to file if DB logging fails to prevent app crash
            log_message('error', 'Failed to log activity: ' . $e->getMessage());
        }
    }
}
