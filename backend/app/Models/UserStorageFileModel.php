<?php

namespace App\Models;

use CodeIgniter\Model;

class UserStorageFileModel extends Model
{
    protected $table = 'user_storage_files';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'user_id',
        'item_type',
        'folder_path',
        'original_name',
        'stored_name',
        'mime_type',
        'size_bytes',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
