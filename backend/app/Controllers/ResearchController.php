<?php

namespace App\Controllers;

use App\Models\ResearchModel;
use CodeIgniter\API\ResponseTrait;

class ResearchController extends BaseController
{
    use ResponseTrait;

    // 1. GET APPROVED RESEARCHES (Public View)
    public function index()
    {
        header('Access-Control-Allow-Origin: *');
        $model = new ResearchModel();
        
        // ONLY GET ACTIVE (is_archived = 0) AND APPROVED items
        $data = $model->where('is_archived', 0)
                      ->where('status', 'approved') // <--- Only show approved
                      ->orderBy('created_at', 'DESC')
                      ->findAll();
        
        return $this->respond($data);
    }

    // 2. GET PENDING ITEMS (For Approval Page)
    public function pending()
    {
        header('Access-Control-Allow-Origin: *');
        $model = new ResearchModel();

        // Get items that are pending and not hidden
        $data = $model->where('status', 'pending')
                      ->where('is_archived', 0)
                      ->orderBy('created_at', 'ASC') // Oldest first
                      ->findAll();

        return $this->respond($data);
    }

    // 3. GET ARCHIVED ITEMS (Admin Only)
    public function archived()
    {
        header('Access-Control-Allow-Origin: *');
        $model = new ResearchModel();
        
        $data = $model->where('is_archived', 1)
                      ->orderBy('created_at', 'DESC')
                      ->findAll();
        
        return $this->respond($data);
    }

    // 4. APPROVE RESEARCH
    public function approve($id = null)
    {
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Methods: POST, OPTIONS");
        if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") die();

        $model = new ResearchModel();
        
        $data = ['status' => 'approved'];
        
        if ($model->update($id, $data)) {
            return $this->respond(['status' => 'success', 'message' => 'Research Approved Successfully']);
        } else {
            return $this->fail('Failed to approve');
        }
    }

    // 5. REJECT RESEARCH
    public function reject($id = null)
    {
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Methods: POST, OPTIONS");
        if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") die();

        $model = new ResearchModel();

        $data = ['status' => 'rejected']; 
        
        if ($model->update($id, $data)) {
            return $this->respond(['status' => 'success', 'message' => 'Research Rejected']);
        } else {
            return $this->fail('Failed to reject');
        }
    }

    // TOGGLE ARCHIVE STATUS (Hide/Restore)
    public function toggleArchive($id = null)
    {
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Methods: POST, OPTIONS");
        if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") die();

        $model = new ResearchModel();
        
        // Get current status
        $item = $model->find($id);
        if (!$item) return $this->failNotFound('Item not found');

        // Flip the status (0 -> 1, or 1 -> 0)
        $newStatus = $item['is_archived'] == 0 ? 1 : 0;
        
        $model->update($id, ['is_archived' => $newStatus]);

        return $this->respond(['status' => 'success', 'message' => 'Status Updated']);
    }

    // 6. CREATE NEW RESEARCH (WITH FILE UPLOAD)
    public function create()
    {
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: Content-Type");
        header("Access-Control-Allow-Methods: POST, OPTIONS");

        if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") {
            die();
        }

        $model = new ResearchModel();

        // 1. Get Text Data (Standard POST, not JSON anymore)
        $title = $this->request->getPost('title');
        $author = $this->request->getPost('author');
        $abstract = $this->request->getPost('abstract');
        $uploadedBy = $this->request->getPost('uploaded_by');
        
        // 2. Handle the File
        $file = $this->request->getFile('pdf_file'); // We will name the input 'pdf_file' in Vue
        $fileName = null;

        if ($file && $file->isValid() && !$file->hasMoved()) {
            // Check if it is really a PDF
            if ($file->getMimeType() !== 'application/pdf') {
                return $this->respond(['status' => 'error', 'message' => 'Only PDF files are allowed!']);
            }

            // Move file to 'public/uploads' folder
            $fileName = $file->getRandomName(); // Generate random name like "17382_asd.pdf"
            $file->move(ROOTPATH . 'public/uploads', $fileName);
        }

        // 3. Save to Database
        $data = [
            'title'  => $title,
            'author' => $author,
            'abstract' => $abstract,
            'file_path' => $fileName, 
            'uploaded_by' => $uploadedBy,
            'status' => 'pending' // <--- Explicitly set to pending
        ];

        $model->insert($data);

        return $this->respond(['status' => 'success', 'message' => 'Research Submitted for Approval!']);
    }

    public function update($id = null)
    {
       // $this->handleCors(); 

        $model = new ResearchModel();
        
        // 1. Get Text Data (Changed from getJSON to getPost)
        $data = [
            'title'    => $this->request->getPost('title'),
            'author'   => $this->request->getPost('author'),
            'abstract' => $this->request->getPost('abstract'),
        ];

        // 2. Handle New File Upload (Optional)
        $file = $this->request->getFile('pdf_file');

        if ($file && $file->isValid() && !$file->hasMoved()) {
            // Validate it is a PDF
            if ($file->getMimeType() !== 'application/pdf') {
                return $this->fail('Only PDF files are allowed');
            }

            // Upload new file
            $newName = $file->getRandomName();
            $file->move('uploads', $newName);
            
            // Add new filename to database update array
            $data['file_path'] = $newName; 
        }

        if ($model->update($id, $data)) {
            return $this->respond(['status' => 'success', 'message' => 'Research updated']);
        } else {
            return $this->fail('Failed to update');
        }
    }

    // ... existing code ...

    // 7. GET COMMENTS FOR A RESEARCH ITEM
    public function getComments($id = null)
    {
        header('Access-Control-Allow-Origin: *');
        
        $commentModel = new \App\Models\ResearchCommentModel();
        
        // Get all comments for this research ID, ordered by time
        $data = $commentModel->where('research_id', $id)
                             ->orderBy('created_at', 'ASC')
                             ->findAll();
                             
        return $this->respond($data);
    }

    // 8. ADD A COMMENT
    public function addComment()
    {
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Methods: POST, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type");
        
        if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") die();

        // Check if Model exists
        $commentModel = new \App\Models\ResearchCommentModel();
        
        // Get Input
        $json = $this->request->getJSON();

        // Safety Check: Did we receive data?
        if (!$json) {
            return $this->fail('No JSON data received');
        }

        $data = [
            'research_id' => $json->research_id ?? null,
            'user_id'     => $json->user_id ?? 0,
            'user_name'   => $json->user_name ?? 'Anonymous',
            'role'        => $json->role ?? 'user',
            'comment'     => $json->comment ?? ''
        ];

        try {
            if ($commentModel->insert($data)) {
                return $this->respondCreated(['status' => 'success']);
            } else {
                return $this->fail('Failed to save to database');
            }
        } catch (\Exception $e) {
            // This will show you the SQL error if the table is missing
            return $this->failServerError($e->getMessage());
        }
    }
}