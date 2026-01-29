<?php

namespace App\Models;

use CodeIgniter\Model;

class ResearchModel extends Model
{
    protected $table            = 'researches';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false; // Ensure this is false for our custom logic

    // UPDATE THIS ARRAY TO INCLUDE ALL NEW COLUMNS
    protected $allowedFields = [
        'title', 
        'author', 
        'abstract', 
        'start_date', 
        'deadline_date', 
        'file_path', 
        'uploaded_by', 
        'status',          // <--- MUST BE HERE
        'archived_at',     // <--- MUST BE HERE
        'rejected_at',     
        'approved_at',
        'created_at',
        'updated_at'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}