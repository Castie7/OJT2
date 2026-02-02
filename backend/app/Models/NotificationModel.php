<?php

namespace App\Models;

use CodeIgniter\Model;

class NotificationModel extends Model
{
    protected $table            = 'notifications';
    protected $primaryKey       = 'id';
    protected $allowedFields    = ['user_id', 'sender_id', 'research_id', 'message', 'is_read', 'created_at'];
    protected $returnType       = 'array';
    protected $useTimestamps    = false; // We set created_at manually or via DB default
}