<?php

namespace App\Models;

use CodeIgniter\Model;

class ResearchModel extends Model
{
    protected $table = 'researches';
    protected $primaryKey = 'id';
    // App/Models/ResearchModel.php
protected $allowedFields = ['title', 'author', 'abstract', 'file_path', 'uploaded_by', 'is_archived', 'status'];
    }