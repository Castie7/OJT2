<?php

namespace App\Controllers;

use App\Models\ResearchModel;
use App\Models\UserModel;
use CodeIgniter\API\ResponseTrait;

class ResearchController extends BaseController
{
    use ResponseTrait;

    // --- SECURITY HELPER ---
    private function validateUser() {
        $request = service('request');
        $token = $request->getHeaderLine('Authorization');
        
        if(!$token) return false;

        $userModel = new UserModel();
        return $userModel->where('auth_token', $token)->first();
    }

    private function handleCors() {
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With"); 
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") die();
    }

    // 1. PUBLIC INDEX (Library)
    public function index()
    {
        $this->handleCors();
        $model = new ResearchModel();
        // Show only approved researches that are NOT archived
        $data = $model->where('status', 'approved')
                      ->orderBy('created_at', 'DESC')
                      ->findAll();
        return $this->respond($data);
    }

    // 2. MY SUBMISSIONS (Active Workspace)
    public function mySubmissions()
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user) return $this->failUnauthorized('Access Denied');

        $model = new ResearchModel();
        // Show everything EXCEPT 'archived' status for this user
        $data = $model->where('uploaded_by', $user['id'])
                      ->where('status !=', 'archived') 
                      ->orderBy('created_at', 'DESC')
                      ->findAll();
        return $this->respond($data);
    }

    // 3. MY ARCHIVED (User's Recycle Bin - 60 Days Auto-Delete)
    // FETCH MY ARCHIVED (Fixed to show old items too)
    public function myArchived()
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user) return $this->failUnauthorized();

        $model = new ResearchModel();

        // 1. AUTO-DELETE: Only remove items if they have a date AND are > 60 days old
        $cutoffDate = date('Y-m-d H:i:s', strtotime('-60 days'));

        $model->where('uploaded_by', $user['id'])
              ->where('status', 'archived')
              ->where('archived_at IS NOT NULL') // Only check expiration if date exists
              ->where('archived_at <', $cutoffDate)
              ->delete();

        // 2. FETCH ALL ARCHIVED ITEMS
        // We removed the "archived_at IS NOT NULL" check so old data appears
        $data = $model->where('uploaded_by', $user['id'])
                      ->where('status', 'archived')
                      ->orderBy('archived_at', 'DESC')
                      ->orderBy('updated_at', 'DESC') // Fallback sort for items without dates
                      ->findAll();

        return $this->respond($data);
    }

    public function archived()
    {
        $this->handleCors();
        
        // Security: Only allow Admins to see the global archive
        $user = $this->validateUser();
        if (!$user || $user['role'] !== 'admin') {
             return $this->failForbidden('Access Denied');
        }

        $model = new ResearchModel();
        
        // Fetch all items with status 'archived'
        $data = $model->where('status', 'archived')
                      ->orderBy('archived_at', 'DESC')
                      ->findAll();
                      
        return $this->respond($data);
    }

    // 4. PENDING LIST (Admin Only)
    public function pending()
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user || $user['role'] !== 'admin') return $this->failForbidden('Access Denied');

        $model = new ResearchModel();
        $data = $model->where('status', 'pending')
                      ->orderBy('created_at', 'ASC')
                      ->findAll();
        return $this->respond($data);
    }

    // 5. REJECTED LIST (Admin Recycle Bin - 30 Days Auto-Delete)
    public function rejectedList()
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user || $user['role'] !== 'admin') return $this->failForbidden();

        $model = new ResearchModel();

        // A. AUTO-DELETE > 30 days
        $cutoffDate = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        $model->where('status', 'rejected')
              ->where('rejected_at <', $cutoffDate)
              ->delete();

        // B. Fetch remaining
        $data = $model->where('status', 'rejected')
                      ->orderBy('rejected_at', 'DESC')
                      ->findAll();

        return $this->respond($data);
    }

    // 6. CREATE (Submit New Research)
    public function create()
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user) return $this->failUnauthorized('Invalid Token');

        $rules = [
            'title'         => 'required|min_length[3]',
            'author'        => 'required|min_length[2]',
            'start_date'    => 'required|valid_date[Y-m-d]',
            'deadline_date' => 'required|valid_date[Y-m-d]',
        ];

        if (!$this->validate($rules)) {
            return $this->fail($this->validator->getErrors());
        }

        $start = $this->request->getPost('start_date');
        $deadline = $this->request->getPost('deadline_date');
        
        // Year & Logic Checks
        $startYear = date('Y', strtotime($start));
        $deadlineYear = date('Y', strtotime($deadline));

        if ($startYear < 2000 || $startYear > 2100 || $deadlineYear < 2000 || $deadlineYear > 2100) {
            return $this->fail("Invalid Year. Dates must be between 2000 and 2100.");
        }
        if (strtotime($deadline) < strtotime($start)) {
            return $this->fail("Deadline cannot be before the Start Date.");
        }

        $model = new ResearchModel();
        $file = $this->request->getFile('pdf_file');
        $fileName = null;

        if (!$file || !$file->isValid()) {
            return $this->fail("File is invalid or missing.");
        }
        
        $mime = $file->getMimeType();
        if(!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'])) {
             return $this->fail("Only PDF, JPG, or PNG files are allowed.");
        }

        if (!$file->hasMoved()) {
            $fileName = $file->getRandomName();
            $file->move(ROOTPATH . 'public/uploads', $fileName);
        }

        $data = [
            'title'       => $this->request->getPost('title'),
            'author'      => $this->request->getPost('author'),
            'abstract'    => $this->request->getPost('abstract'),
            'start_date'  => $start,
            'deadline_date' => $deadline,
            'file_path'   => $fileName,
            'uploaded_by' => $user['id'], 
            'status'      => 'pending'
        ];

        $model->insert($data);
        return $this->respond(['status' => 'success']);
    }

    // 7. UPDATE (Edit Research)
    public function update($id = null)
    {
        $this->handleCors();
        
        // 1. STRICT METHOD CHECK
        if (!$this->request->is('post')) {
            return $this->failMethodNotAllowed('Only POST requests allowed');
        }

        $user = $this->validateUser();
        if (!$user) return $this->failUnauthorized();

        $model = new ResearchModel();
        
        // Verify ownership
        $item = $model->find($id);
        if(!$item || ($item['uploaded_by'] != $user['id'] && $user['role'] !== 'admin')) {
             return $this->failForbidden();
        }

        $rules = [
            'title'         => 'required|min_length[3]',
            'author'        => 'required|min_length[2]',
            'start_date'    => 'permit_empty|valid_date[Y-m-d]',
            'deadline_date' => 'required|valid_date[Y-m-d]',
        ];

        if (!$this->validate($rules)) return $this->fail($this->validator->getErrors());

        $start = $this->request->getPost('start_date');
        $deadline = $this->request->getPost('deadline_date');
        
        // Date Logic Checks (Same as Create)
        if ($deadline && strtotime($deadline) < strtotime($start)) {
            return $this->fail("Deadline cannot be before Start Date.");
        }

        $data = [
            'title'       => $this->request->getPost('title'),
            'author'      => $this->request->getPost('author'),
            'abstract'    => $this->request->getPost('abstract'),
            'start_date'  => $start,
            'deadline_date' => $deadline,
        ];

        $file = $this->request->getFile('pdf_file');
        if ($file && $file->isValid()) {
            if (!$file->hasMoved()) {
                $newName = $file->getRandomName();
                $file->move('public/uploads', $newName);
                $data['file_path'] = $newName; 
            }
        }

        if ($model->update($id, $data)) {
            return $this->respond(['status' => 'success']);
        }
        return $this->fail('Failed to update');
    }

    // 8. ACTION: APPROVE
    public function approve($id = null)
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user || $user['role'] !== 'admin') return $this->failForbidden();

        $model = new ResearchModel();
        $model->update($id, [
            'status' => 'approved',
            'approved_at' => date('Y-m-d H:i:s') 
        ]);
        
        return $this->respond(['status' => 'success']);
    }

    // 9. ACTION: REJECT
    public function reject($id = null)
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user || $user['role'] !== 'admin') return $this->failForbidden();

        $model = new ResearchModel();
        $model->update($id, [
            'status' => 'rejected',
            'rejected_at' => date('Y-m-d H:i:s')
        ]);
        
        return $this->respond(['status' => 'success']);
    }

    // 10. ACTION: ARCHIVE (Move to User Recycle Bin)
    public function archive($id = null)
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user) return $this->failUnauthorized();

        $model = new ResearchModel();
        $item = $model->find($id);
        
        // Ownership Check
        if ($item['uploaded_by'] != $user['id'] && $user['role'] !== 'admin') {
            return $this->failForbidden("Cannot archive others' work");
        }

        $model->update($id, [
            'status' => 'archived',
            'archived_at' => date('Y-m-d H:i:s') 
        ]);

        return $this->respond(['status' => 'success']);
    }

    // 11. ACTION: RESTORE (From Archive or Reject -> Pending)
    public function restore($id = null)
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user) return $this->failUnauthorized();

        $model = new ResearchModel();
        $item = $model->find($id);

        // Allow restore if Admin OR Owner
        if ($user['role'] !== 'admin' && $item['uploaded_by'] != $user['id']) {
             return $this->failForbidden();
        }
        
        $model->update($id, [
            'status' => 'pending',
            'rejected_at' => null,
            'archived_at' => null
        ]);

        return $this->respond(['status' => 'success']);
    }

    // 12. EXTEND DEADLINE
    public function extendDeadline($id = null)
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user || $user['role'] !== 'admin') return $this->failForbidden();

        $newDate = $this->request->getPost('new_deadline');
        if (!$newDate) return $this->fail('Date is required.');

        $model = new ResearchModel();
        $model->update($id, ['deadline_date' => $newDate]);
        
        return $this->respond(['status' => 'success']);
    }

    // 13. COMMENTS
    public function getComments($id = null)
    {
        $this->handleCors();
        $commentModel = new \App\Models\ResearchCommentModel();
        $data = $commentModel->where('research_id', $id)->orderBy('created_at', 'ASC')->findAll();
        return $this->respond($data);
    }

    public function addComment()
    {
        $this->handleCors();
        $user = $this->validateUser(); 
        
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

}