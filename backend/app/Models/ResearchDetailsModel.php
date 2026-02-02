<?php

namespace App\Models;

use CodeIgniter\Model;

class ResearchDetailsModel extends Model
{
    protected $table            = 'research_details';
    protected $primaryKey       = 'id';
    protected $allowedFields    = [
        'research_id', 
        'knowledge_type', 
        'publication_date', 
        'edition', 
        'publisher', 
        'physical_description', 
        'isbn_issn', 
        'subjects', 
        'shelf_location', 
        'item_condition', 
        'link'
    ];
}