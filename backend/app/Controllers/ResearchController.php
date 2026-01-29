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

    // 1. PUBLIC INDEX
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

    // 2. MY SUBMISSIONS
    public function mySubmissions()
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user) return $this->failUnauthorized('Access Denied');

        $model = new ResearchModel();
        $data = $model->where('uploaded_by', $user['id'])
                      ->where('is_archived', 0) 
                      ->orderBy('created_at', 'DESC')
                      ->findAll();
        return $this->respond($data);
    }

    // 3. PENDING (Admin)
    public function pending()
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user || $user['role'] !== 'admin') return $this->failForbidden('Access Denied');

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
        if ($user['role'] !== 'admin') {
            $builder->where('uploaded_by', $user['id']);
        }
        $data = $builder->orderBy('created_at', 'DESC')->findAll();
        return $this->respond($data);
    }

    // 5. CREATE (SECURED)
    public function create()
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user) return $this->failUnauthorized('Invalid Token');

        // 1. DEFINE VALIDATION RULES
        $rules = [
            'title'         => 'required|min_length[3]',
            'author'        => 'required|min_length[2]',
            'start_date'    => 'required|valid_date[Y-m-d]',
            'deadline_date' => 'required|valid_date[Y-m-d]',
            // pdf_file is handled separately via getFile()
        ];

        if (!$this->validate($rules)) {
            return $this->fail($this->validator->getErrors());
        }

        // 2. LOGICAL CHECKS (PHP Side)
        $start = $this->request->getPost('start_date');
        $deadline = $this->request->getPost('deadline_date');
        
        $startYear = date('Y', strtotime($start));
        $deadlineYear = date('Y', strtotime($deadline));

        // A. Prevent crazy years (e.g., 11111 or 1900)
        if ($startYear < 2000 || $startYear > 2100 || $deadlineYear < 2000 || $deadlineYear > 2100) {
            return $this->fail("Invalid Year. Dates must be between 2000 and 2100.");
        }

        // B. Ensure Deadline is AFTER Start
        if (strtotime($deadline) < strtotime($start)) {
            return $this->fail("Deadline cannot be before the Start Date.");
        }

        $model = new ResearchModel();
        
        $file = $this->request->getFile('pdf_file');
        $fileName = null;

        // 3. FILE VALIDATION
        if (!$file || !$file->isValid()) {
            return $this->fail("File is invalid or missing.");
        }
        
        // Ensure strictly PDF or Image
        $mime = $file->getMimeType();
        $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
        
        if(!in_array($mime, $allowedMimes)) {
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

    // 6. UPDATE
    // 6. UPDATE (SECURED)
    public function update($id = null)
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user) return $this->failUnauthorized();

        $model = new ResearchModel();
        
        // Optional: Verify ownership
        // $item = $model->find($id);
        // if($item['uploaded_by'] != $user['id'] && $user['role'] !== 'admin') return $this->failForbidden();

        // 1. VALIDATION RULES
        $rules = [
            'title'         => 'required|min_length[3]',
            'author'        => 'required|min_length[2]',
            'start_date'    => 'permit_empty|valid_date[Y-m-d]',
            'deadline_date' => 'required|valid_date[Y-m-d]',
        ];

        if (!$this->validate($rules)) {
            return $this->fail($this->validator->getErrors());
        }

        // 2. LOGICAL CHECKS (Dates)
        $start = $this->request->getPost('start_date');
        $deadline = $this->request->getPost('deadline_date');
        
        // Year Range Check
        $minYear = 2000; $maxYear = 2100;
        
        if ($deadline) {
            $dYear = date('Y', strtotime($deadline));
            if ($dYear < $minYear || $dYear > $maxYear) return $this->fail("Deadline year must be between $minYear and $maxYear");
        }
        
        if ($start) {
            $sYear = date('Y', strtotime($start));
            if ($sYear < $minYear || $sYear > $maxYear) return $this->fail("Start date year must be between $minYear and $maxYear");
            
            // Logic Check
            if ($deadline && strtotime($deadline) < strtotime($start)) {
                return $this->fail("Deadline cannot be before the Start Date.");
            }
        }

        $data = [
            'title'       => $this->request->getPost('title'),
            'author'      => $this->request->getPost('author'),
            'abstract'    => $this->request->getPost('abstract'),
            'start_date'  => $start,
            'deadline_date' => $deadline,
        ];

        // 3. FILE VALIDATION (Only if new file is sent)
        $file = $this->request->getFile('pdf_file');
        if ($file && $file->isValid()) {
            
            $mime = $file->getMimeType();
            $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
        
            if(!in_array($mime, $allowedMimes)) {
                 return $this->fail("Only PDF, JPG, or PNG files are allowed.");
            }

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
    // UPDATE: REJECT (Now saves the date)
    public function reject($id = null)
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user || $user['role'] !== 'admin') return $this->failForbidden();

        $model = new ResearchModel();
        // Set status to rejected AND save the current timestamp
        $model->update($id, [
            'status' => 'rejected',
            'rejected_at' => date('Y-m-d H:i:s')
        ]);
        
        return $this->respond(['status' => 'success']);
    }

    // NEW: GET REJECTED LIST (With Auto-Delete)
    public function rejectedList()
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user || $user['role'] !== 'admin') return $this->failForbidden();

        $model = new ResearchModel();

        // 1. AUTO-DELETE: Remove items rejected more than 30 days ago
        $cutoffDate = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        // This effectively deletes expired items before we even fetch the list
        $model->where('status', 'rejected')
              ->where('rejected_at <', $cutoffDate)
              ->delete();

        // 2. Fetch remaining rejected items
        $data = $model->where('status', 'rejected')
                      ->orderBy('rejected_at', 'DESC')
                      ->findAll();

        return $this->respond($data);
    }

    // NEW: RESTORE (Move back to Pending)
    public function restore($id = null)
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user || $user['role'] !== 'admin') return $this->failForbidden();

        $model = new ResearchModel();
        
        // Set back to pending and clear the rejection date
        $model->update($id, [
            'status' => 'pending',
            'rejected_at' => null 
        ]);

        return $this->respond(['status' => 'success']);
    }

    // 9. TOGGLE ARCHIVE
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

    // 12. GET MY ARCHIVED
    public function myArchived()
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user) return $this->failUnauthorized('Access Denied');

        $model = new ResearchModel();
        $data = $model->where('uploaded_by', $user['id'])
                      ->where('is_archived', 1) 
                      ->orderBy('created_at', 'DESC')
                      ->findAll();
        return $this->respond($data);
    }

    // 13. EXTEND DEADLINE
    public function extendDeadline($id = null)
    {
        // Force CORS First
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") die();

        $user = $this->validateUser();
        if (!$user || $user['role'] !== 'admin') return $this->failForbidden('Access Denied');

        $newDate = $this->request->getPost('new_deadline');
        if (!$newDate) return $this->fail('Date is required.');

        $model = new ResearchModel();
        if (!$model->db->fieldExists('deadline_date', 'researches')) {
            return $this->failServerError("Database Error: Column 'deadline_date' is missing.");
        }

        if ($model->update($id, ['deadline_date' => $newDate])) {
            return $this->respond(['status' => 'success']);
        }
        return $this->fail('Database update failed.');
    }

} // <--- THIS FINAL BRACKET IS CRITICAL. ALL FUNCTIONS MUST BE ABOVE THIS. 