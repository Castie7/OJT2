<?php

namespace App\Models;

use CodeIgniter\Model;

class DirectMessageModel extends Model
{
    protected $table            = 'direct_messages';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = ['sender_id', 'recipient_id', 'message', 'is_read', 'created_at'];

    protected $useTimestamps = false;
}
