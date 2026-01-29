<?php

namespace App\Models;

use CodeIgniter\Model;

class ResearchModel extends Model
{
    protected $table = 'researches'; // <--- Ensure this matches your table name
    protected $primaryKey = 'id';
    
    // Add 'start_date' and 'deadline_date' to this list
        protected $allowedFields = [
            'title', 
            'author', 
            'abstract', 
            'file_path', 
            'uploaded_by', 
            'status', 
            'is_archived',
            'start_date',     
            'deadline_date'   // <--- MAKE SURE THIS IS HERE!
        ];
    
    protected $useTimestamps = true; // Assuming you use created_at/updated_at
}