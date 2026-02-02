<?php

namespace App\Controllers;

use App\Models\ResearchModel;
use App\Models\ResearchDetailsModel;
use App\Models\UserModel;
use CodeIgniter\API\ResponseTrait;

class ResearchController extends BaseController
{
    use ResponseTrait;

    // --- HELPER: columns to select to avoid ID collision ---
    private $selectString = 'researches.*, 
                             research_details.knowledge_type, 
                             research_details.publication_date, 
                             research_details.edition, 
                             research_details.publisher, 
                             research_details.physical_description, 
                             research_details.isbn_issn, 
                             research_details.subjects, 
                             research_details.shelf_location, 
                             research_details.item_condition, 
                             research_details.link';

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

        // FIX: Use specific select string
        $data = $model->select($this->selectString) 
                      ->join('research_details', 'researches.id = research_details.research_id', 'left')
                      ->where('researches.status', 'approved')
                      ->orderBy('researches.created_at', 'DESC')
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

        $data = $model->select($this->selectString)
                      ->join('research_details', 'researches.id = research_details.research_id', 'left')
                      ->where('researches.uploaded_by', $user['id'])
                      ->where('researches.status !=', 'archived') 
                      ->orderBy('researches.created_at', 'DESC')
                      ->findAll();

        return $this->respond($data);
    }

    // 3. MY ARCHIVED
    public function myArchived()
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user) return $this->failUnauthorized();

        $model = new ResearchModel();

        // Auto-delete > 60 days
        $cutoffDate = date('Y-m-d H:i:s', strtotime('-60 days'));
        $model->where('uploaded_by', $user['id'])
              ->where('status', 'archived')
              ->where('archived_at IS NOT NULL')
              ->where('archived_at <', $cutoffDate)
              ->delete();

        // Fetch
        $data = $model->select($this->selectString)
                      ->join('research_details', 'researches.id = research_details.research_id', 'left')
                      ->where('researches.uploaded_by', $user['id'])
                      ->where('researches.status', 'archived')
                      ->orderBy('researches.archived_at', 'DESC')
                      ->orderBy('researches.updated_at', 'DESC')
                      ->findAll();

        return $this->respond($data);
    }

    public function archived()
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user || $user['role'] !== 'admin') {
             return $this->failForbidden('Access Denied');
        }

        $model = new ResearchModel();
        
        $data = $model->select($this->selectString)
                      ->join('research_details', 'researches.id = research_details.research_id', 'left')
                      ->where('researches.status', 'archived')
                      ->orderBy('researches.archived_at', 'DESC')
                      ->findAll();
                      
        return $this->respond($data);
    }

    // 4. PENDING LIST (Admin Only) - THIS WAS THE ISSUE
    public function pending()
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user || $user['role'] !== 'admin') return $this->failForbidden('Access Denied');

        $model = new ResearchModel();
        
        // FIX: Use specific select string to ensure 'id' is correct
        $data = $model->select($this->selectString)
                      ->join('research_details', 'researches.id = research_details.research_id', 'left')
                      ->where('researches.status', 'pending')
                      ->orderBy('researches.created_at', 'ASC')
                      ->findAll();
                      
        return $this->respond($data);
    }

    // 5. REJECTED LIST
    public function rejectedList()
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user || $user['role'] !== 'admin') return $this->failForbidden();

        $model = new ResearchModel();

        $cutoffDate = date('Y-m-d H:i:s', strtotime('-30 days'));
        $model->where('status', 'rejected')
              ->where('rejected_at <', $cutoffDate)
              ->delete();

        $data = $model->select($this->selectString)
                      ->join('research_details', 'researches.id = research_details.research_id', 'left')
                      ->where('researches.status', 'rejected')
                      ->orderBy('researches.rejected_at', 'DESC')
                      ->findAll();

        return $this->respond($data);
    }

    // 6. CREATE
    public function create()
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user) return $this->failUnauthorized('Invalid Token');

        $rules = [
            'title'  => 'required|min_length[3]',
            'author' => 'required|min_length[2]',
        ];

        if (!$this->validate($rules)) {
            return $this->fail($this->validator->getErrors());
        }

        $file = $this->request->getFile('pdf_file');
        $fileName = null;
        if ($file && $file->isValid() && !$file->hasMoved()) {
            $fileName = $file->getRandomName();
            $file->move(ROOTPATH . 'public/uploads', $fileName);
        }

        $researchModel = new ResearchModel();
        
        $mainData = [
            'uploaded_by' => $user['id'],
            'title'       => $this->request->getPost('title'),
            'author'      => $this->request->getPost('author'),
            // Map crop_variation
            'crop_variation' => $this->request->getPost('crop_variation'),
            'status'      => 'pending',
            'file_path'   => $fileName,
            'created_at'  => date('Y-m-d H:i:s'),
        ];

        $newResearchId = $researchModel->insert($mainData); 

        $detailsModel = new ResearchDetailsModel();
        
        $detailsData = [
            'research_id'          => $newResearchId,
            'knowledge_type'       => $this->request->getPost('knowledge_type'),
            'publication_date'     => $this->request->getPost('publication_date'),
            'edition'              => $this->request->getPost('edition'),
            'publisher'            => $this->request->getPost('publisher'),
            'physical_description' => $this->request->getPost('physical_description'),
            'isbn_issn'            => $this->request->getPost('isbn_issn'),
            'subjects'             => $this->request->getPost('subjects'),
            'shelf_location'       => $this->request->getPost('shelf_location'),
            'item_condition'       => $this->request->getPost('item_condition'),
            'link'                 => $this->request->getPost('link'),
        ];

        $detailsModel->insert($detailsData);

        return $this->respond(['status' => 'success']);
    }

    // 7. UPDATE
    public function update($id = null)
    {
        $this->handleCors();
        
        if (!$this->request->is('post')) {
            return $this->failMethodNotAllowed('Only POST requests allowed');
        }

        $user = $this->validateUser();
        if (!$user) return $this->failUnauthorized();

        $researchModel = new ResearchModel();
        
        $item = $researchModel->find($id);
        if(!$item || ($item['uploaded_by'] != $user['id'] && $user['role'] !== 'admin')) {
             return $this->failForbidden();
        }

        $mainUpdate = [
            'title'  => $this->request->getPost('title'),
            'author' => $this->request->getPost('author'),
            'crop_variation' => $this->request->getPost('crop_variation'),
        ];
        
        $file = $this->request->getFile('pdf_file');
        if ($file && $file->isValid() && !$file->hasMoved()) {
            $newName = $file->getRandomName();
            $file->move('public/uploads', $newName);
            $mainUpdate['file_path'] = $newName; 
        }

        $researchModel->update($id, $mainUpdate);

        $detailsModel = new ResearchDetailsModel();
        $exists = $detailsModel->where('research_id', $id)->first();
        
        $detailsData = [
            'knowledge_type'       => $this->request->getPost('knowledge_type'),
            'publication_date'     => $this->request->getPost('publication_date'),
            'edition'              => $this->request->getPost('edition'),
            'publisher'            => $this->request->getPost('publisher'),
            'physical_description' => $this->request->getPost('physical_description'),
            'isbn_issn'            => $this->request->getPost('isbn_issn'),
            'subjects'             => $this->request->getPost('subjects'),
            'shelf_location'       => $this->request->getPost('shelf_location'),
            'item_condition'       => $this->request->getPost('item_condition'),
            'link'                 => $this->request->getPost('link'),
        ];

        if ($exists) {
            $detailsModel->where('research_id', $id)->set($detailsData)->update();
        } else {
            $detailsData['research_id'] = $id;
            $detailsModel->insert($detailsData);
        }

        return $this->respond(['status' => 'success']);
    }

    // 8. APPROVE
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

    // 9. REJECT
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

    // 10. ARCHIVE
    public function archive($id = null)
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user) return $this->failUnauthorized();

        $model = new ResearchModel();
        $item = $model->find($id);
        
        if ($item['uploaded_by'] != $user['id'] && $user['role'] !== 'admin') {
            return $this->failForbidden("Cannot archive others' work");
        }

        $model->update($id, [
            'status' => 'archived',
            'archived_at' => date('Y-m-d H:i:s') 
        ]);

        return $this->respond(['status' => 'success']);
    }

    // 11. RESTORE
    public function restore($id = null)
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user) return $this->failUnauthorized();

        $model = new ResearchModel();
        $item = $model->find($id);

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

    // 13. COMMENTS - (No changes needed here)
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

    // STATS
    public function stats()
    {
        $this->handleCors(); // Ensure CORS is handled
        
        $model = new ResearchModel();

        // 1. Count Approved (Total Active in Library)
        // We exclude archived items just in case
        $approved = $model->where('status', 'approved')->countAllResults();

        // 2. Count Pending (Strictly 'pending')
        $pending = $model->where('status', 'pending')->countAllResults();
        
        // 3. (Optional) Count Users if you show that too
        $userModel = new UserModel();
        $users = $userModel->where('role !=', 'admin')->countAllResults();

        return $this->respond([
            'total' => $approved,
            'pending' => $pending,
            'users' => $users
        ]);
    }

    // USER STATS
    public function userStats($userId = null)
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Authorization");

        if (!$userId) return $this->fail('User ID required');

        $model = new ResearchModel();
        $myPublished = $model->where('uploaded_by', $userId)->where('status', 'approved')->countAllResults();
        $myPending = $model->where('uploaded_by', $userId)->where('status', 'pending')->countAllResults();

        return $this->respond(['published' => $myPublished, 'pending'   => $myPending]);
    }
}