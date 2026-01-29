<?php

namespace App\Controllers;

use App\Models\ResearchModel;
use App\Models\UserModel; // <--- Critical Import
use CodeIgniter\API\ResponseTrait;

class ResearchController extends BaseController
{
    use ResponseTrait;

    // --- SECURITY HELPER ---
    private function validateUser() {
        $request = service('request');
        // Get Token from Header
        $token = $request->getHeaderLine('Authorization');
        
        if(!$token) return false;

        $userModel = new UserModel();
        return $userModel->where('auth_token', $token)->first();
    }

    private function handleCors() {
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: Content-Type, Authorization"); 
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") die();
    }

    // 1. PUBLIC INDEX (Approved Only)
    public function index()
    {
        $this->handleCors();
        $model = new ResearchModel();
        $data = $model->where('is_archived', 0)
                      ->where('status', 'approved')
                      ->orderBy('created_at', 'DESC')
                      ->findAll();
        return $this->respond($data);
    }

    // 2. MY SUBMISSIONS (Researcher View - All Statuses)
    public function mySubmissions()
    {
        $this->handleCors();

        $user = $this->validateUser();
        if (!$user) return $this->failUnauthorized('Access Denied');

        $model = new ResearchModel();
        // Get items uploaded by THIS user (Active items only)
        $data = $model->where('uploaded_by', $user['id'])
                      ->where('is_archived', 0) 
                      ->orderBy('created_at', 'DESC')
                      ->findAll();

        return $this->respond($data);
    }

    // 3. PENDING (Admin Only)
    public function pending()
    {
        $this->handleCors();
        
        $user = $this->validateUser();
        if (!$user || $user['role'] !== 'admin') {
            return $this->failForbidden('Access Denied');
        }

        $model = new ResearchModel();
        $data = $model->where('status', 'pending')
                      ->where('is_archived', 0)
                      ->orderBy('created_at', 'ASC')
                      ->findAll();
        return $this->respond($data);
    }

    // 4. ARCHIVED (Secure)
    public function archived()
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user) return $this->failUnauthorized('Access Denied');

        $model = new ResearchModel();
        $builder = $model->where('is_archived', 1);

        // If NOT admin, only show my own files
        if ($user['role'] !== 'admin') {
            $builder->where('uploaded_by', $user['id']);
        }

        $data = $builder->orderBy('created_at', 'DESC')->findAll();
        return $this->respond($data);
    }

    // 5. CREATE
    public function create()
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user) return $this->failUnauthorized('Invalid Token');

        $model = new ResearchModel();

        $title = $this->request->getPost('title');
        $author = $this->request->getPost('author');
        $abstract = $this->request->getPost('abstract');
        
        $file = $this->request->getFile('pdf_file');
        $fileName = null;
        if ($file && $file->isValid() && !$file->hasMoved()) {
            $fileName = $file->getRandomName();
            $file->move(ROOTPATH . 'public/uploads', $fileName);
        }

        $data = [
            'title'  => $title,
            'author' => $author,
            'abstract' => $abstract,
            'file_path' => $fileName,
            'uploaded_by' => $user['id'], 
            'status' => 'pending'
        ];

        $model->insert($data);
        return $this->respond(['status' => 'success']);
    }

    // 6. UPDATE
    public function update($id = null)
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user) return $this->failUnauthorized();

        $model = new ResearchModel();
        
        // Optional: Check if user owns this file before updating
        // $item = $model->find($id);
        // if($item['uploaded_by'] != $user['id'] && $user['role'] !== 'admin') return $this->failForbidden();

        $data = [
            'title'    => $this->request->getPost('title'),
            'author'   => $this->request->getPost('author'),
            'abstract' => $this->request->getPost('abstract'),
        ];

        $file = $this->request->getFile('pdf_file');
        if ($file && $file->isValid() && !$file->hasMoved()) {
            $newName = $file->getRandomName();
            $file->move('public/uploads', $newName);
            $data['file_path'] = $newName; 
        }

        if ($model->update($id, $data)) {
            return $this->respond(['status' => 'success']);
        }
        return $this->fail('Failed to update');
    }

    // 7. APPROVE
    public function approve($id = null)
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user || $user['role'] !== 'admin') return $this->failForbidden();

        $model = new ResearchModel();
        $model->update($id, ['status' => 'approved']);
        return $this->respond(['status' => 'success']);
    }

    // 8. REJECT
    public function reject($id = null)
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user || $user['role'] !== 'admin') return $this->failForbidden();

        $model = new ResearchModel();
        $model->update($id, ['status' => 'rejected']);
        return $this->respond(['status' => 'success']);
    }

    // 9. ARCHIVE TOGGLE
    public function toggleArchive($id = null)
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user) return $this->failUnauthorized();

        $model = new ResearchModel();
        $item = $model->find($id);
        
        if ($user['role'] !== 'admin' && $item['uploaded_by'] != $user['id']) {
            return $this->failForbidden();
        }

        $newStatus = $item['is_archived'] == 0 ? 1 : 0;
        $model->update($id, ['is_archived' => $newStatus]);
        return $this->respond(['status' => 'success']);
    }

    // 10. GET COMMENTS
    public function getComments($id = null)
    {
        $this->handleCors();
        $commentModel = new \App\Models\ResearchCommentModel();
        $data = $commentModel->where('research_id', $id)->orderBy('created_at', 'ASC')->findAll();
        return $this->respond($data);
    }

    // 11. ADD COMMENT
    public function addComment()
    {
        $this->handleCors();
        $user = $this->validateUser(); 
        // Note: You might want to enforce validation here too if strict
        
        $commentModel = new \App\Models\ResearchCommentModel();
        $json = $this->request->getJSON();

        $data = [
            'research_id' => $json->research_id,
            'user_id'     => $json->user_id,
            'user_name'   => $json->user_name,
            'role'        => $json->role,
            'comment'     => $json->comment
        ];

        $commentModel->insert($data);
        return $this->respondCreated(['status' => 'success']);
    }
    // ... inside ResearchController.php ...

    // 12. GET MY ARCHIVED FILES (Strictly my own)
    public function myArchived()
    {
        $this->handleCors();
        
        $user = $this->validateUser();
        if (!$user) return $this->failUnauthorized('Access Denied');

        $model = new ResearchModel();

        // STRICTLY filter by the logged-in user's ID
        $data = $model->where('uploaded_by', $user['id'])
                      ->where('is_archived', 1) // Only Archived
                      ->orderBy('created_at', 'DESC')
                      ->findAll();

        return $this->respond($data);
    }
}