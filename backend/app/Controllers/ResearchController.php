<?php

namespace App\Controllers;

use App\Models\ResearchModel;
use App\Models\ResearchDetailsModel;
use App\Models\UserModel;
use App\Models\NotificationModel;
use CodeIgniter\API\ResponseTrait;

class ResearchController extends BaseController
{
    use ResponseTrait;

    // --- HELPER: columns to select ---
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
    protected function validateUser() {
        $request = service('request');
        $token = $request->getHeaderLine('Authorization');
        
        if(!$token) return false;

        $userModel = new UserModel();
        return $userModel->where('auth_token', $token)->first();
    }

    protected function handleCors() {
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With"); 
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") die();
    }

    // --- HELPER: SMART DUPLICATE CHECKER (UPDATED FOR JOURNALS) ---
    private function checkDuplicate($title, $isbn, $edition, $excludeId = null) {
        $db = \Config\Database::connect();
        $builder = $db->table('researches');
        $builder->join('research_details', 'researches.id = research_details.research_id');

        // ✅ NEW LOGIC: Composite Check
        // We block ONLY if Title + Edition matches.
        // We DO NOT block if just ISBN matches (because Journals share ISSN).
        
        $builder->where('researches.title', $title);
        
        if (!empty($edition)) {
            $builder->where('research_details.edition', $edition);
        }

        if ($excludeId) {
            $builder->where('researches.id !=', $excludeId);
        }

        if ($builder->countAllResults() > 0) {
            return "Duplicate! This Title/Edition combination already exists.";
        }

        return false; // Safe to insert
    }

    // 1. PUBLIC INDEX
    public function index()
    {
        $this->handleCors();
        $model = new ResearchModel();
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
        $cutoffDate = date('Y-m-d H:i:s', strtotime('-60 days'));
        
        $model->where('uploaded_by', $user['id'])
              ->where('status', 'archived')
              ->where('archived_at <', $cutoffDate)
              ->delete();

        $data = $model->select($this->selectString)
                      ->join('research_details', 'researches.id = research_details.research_id', 'left')
                      ->where('researches.uploaded_by', $user['id'])
                      ->where('researches.status', 'archived')
                      ->orderBy('researches.archived_at', 'DESC')
                      ->findAll();

        return $this->respond($data);
    }

    public function archived()
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user || $user['role'] !== 'admin') return $this->failForbidden('Access Denied');

        $model = new ResearchModel();
        $data = $model->select($this->selectString)
                      ->join('research_details', 'researches.id = research_details.research_id', 'left')
                      ->where('researches.status', 'archived')
                      ->orderBy('researches.archived_at', 'DESC')
                      ->findAll();
        return $this->respond($data);
    }

    // 4. PENDING LIST
    public function pending()
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user || $user['role'] !== 'admin') return $this->failForbidden('Access Denied');

        $model = new ResearchModel();
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
        $model->where('status', 'rejected')->where('rejected_at <', $cutoffDate)->delete();

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
            return $this->response->setJSON(['status' => 'error', 'messages' => $this->validator->getErrors()])->setStatusCode(400);
        }

        $isbn = trim($this->request->getPost('isbn_issn'));
        $title = trim($this->request->getPost('title'));
        $edition = trim($this->request->getPost('edition'));

        // ✅ CALL SMART DUPLICATE CHECKER
        $dupError = $this->checkDuplicate($title, $isbn, $edition);
        if ($dupError) {
            return $this->response->setJSON([
                'status' => 'error', 
                'messages' => ['duplicate' => $dupError]
            ])->setStatusCode(400);
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
            'title'       => $title,
            'author'      => $this->request->getPost('author'),
            'crop_variation' => $this->request->getPost('crop_variation'),
            'status'      => 'pending',
            'file_path'   => $fileName,
            'created_at'  => date('Y-m-d H:i:s'),
        ];
        $newResearchId = $researchModel->insert($mainData); 

        $detailsModel = new ResearchDetailsModel();
        $detailsData = [
            'research_id'      => $newResearchId,
            'knowledge_type'   => $this->request->getPost('knowledge_type'),
            'publication_date' => $this->request->getPost('publication_date'),
            'edition'          => $edition,
            'publisher'        => $this->request->getPost('publisher'),
            'physical_description' => $this->request->getPost('physical_description'),
            'isbn_issn'        => $isbn,
            'subjects'         => $this->request->getPost('subjects'),
            'shelf_location'   => $this->request->getPost('shelf_location'),
            'item_condition'   => $this->request->getPost('item_condition'),
            'link'             => $this->request->getPost('link'),
        ];
        $detailsModel->insert($detailsData);

        // =========================================================
        // ✅ FIX: NOTIFY ALL ADMINS (Loop through all admin users)
        // =========================================================
        $userModel = new UserModel();
        $notifModel = new NotificationModel();
        
        // Find everyone with role 'admin'
        $admins = $userModel->where('role', 'admin')->findAll();
        
        foreach ($admins as $admin) {
            $notifModel->insert([
                'user_id'     => $admin['id'], // Recipient (Admin)
                'sender_id'   => $user['id'],  // Sender (Student)
                'research_id' => $newResearchId,
                'message'     => "New Submission: " . $title,
                'is_read'     => 0,
                'created_at'  => date('Y-m-d H:i:s')
            ]);
        }
        // =========================================================

        return $this->respond(['status' => 'success']);
    }

    // 7. UPDATE
    public function update($id = null)
    {
        $this->handleCors();
        if (!$this->request->is('post')) return $this->failMethodNotAllowed();

        $user = $this->validateUser();
        if (!$user) return $this->failUnauthorized();

        $researchModel = new ResearchModel();
        $item = $researchModel->find($id);
        
        if(!$item || ($item['uploaded_by'] != $user['id'] && $user['role'] !== 'admin')) {
             return $this->failForbidden();
        }

        $isbn = trim($this->request->getPost('isbn_issn'));
        $title = trim($this->request->getPost('title'));
        $edition = trim($this->request->getPost('edition'));

        // ✅ CALL SMART DUPLICATE CHECKER (Ignoring Self)
        $dupError = $this->checkDuplicate($title, $isbn, $edition, $id);
        if ($dupError) {
            return $this->response->setJSON([
                'status' => 'error', 
                'messages' => ['duplicate' => $dupError]
            ])->setStatusCode(400);
        }

        $mainUpdate = [
            'title'  => $title,
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
            'knowledge_type'   => $this->request->getPost('knowledge_type'),
            'publication_date' => $this->request->getPost('publication_date'),
            'edition'          => $edition,
            'publisher'        => $this->request->getPost('publisher'),
            'physical_description' => $this->request->getPost('physical_description'),
            'isbn_issn'        => $isbn,
            'subjects'         => $this->request->getPost('subjects'),
            'shelf_location'   => $this->request->getPost('shelf_location'),
            'item_condition'   => $this->request->getPost('item_condition'),
            'link'             => $this->request->getPost('link'),
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
        $model->update($id, ['status' => 'approved', 'approved_at' => date('Y-m-d H:i:s')]);

        $item = $model->find($id);
        if ($item && $item['uploaded_by']) {
            $notifModel = new NotificationModel();
            $notifModel->insert([
                'user_id'     => $item['uploaded_by'],
                'sender_id'   => $user['id'],
                'research_id' => $id,
                'message'     => "🎉 Your research '{$item['title']}' has been APPROVED!",
                'is_read'     => 0,
                'created_at'  => date('Y-m-d H:i:s')
            ]);
        }
        return $this->respond(['status' => 'success']);
    }

    // 9. REJECT
    public function reject($id = null)
    {
        $this->handleCors();
        $user = $this->validateUser();
        if (!$user || $user['role'] !== 'admin') return $this->failForbidden();

        $model = new ResearchModel();
        $model->update($id, ['status' => 'rejected', 'rejected_at' => date('Y-m-d H:i:s')]);

        $item = $model->find($id);
        if ($item && $item['uploaded_by']) {
            $notifModel = new NotificationModel();
            $notifModel->insert([
                'user_id'     => $item['uploaded_by'],
                'sender_id'   => $user['id'],
                'research_id' => $id,
                'message'     => "⚠️ Your research '{$item['title']}' was returned for revision.",
                'is_read'     => 0,
                'created_at'  => date('Y-m-d H:i:s')
            ]);
        }
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
        $model->update($id, ['status' => 'archived', 'archived_at' => date('Y-m-d H:i:s')]);
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
        if ($user['role'] !== 'admin' && $item['uploaded_by'] != $user['id']) return $this->failForbidden();
        
        $model->update($id, ['status' => 'pending', 'rejected_at' => null, 'archived_at' => null]);
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

        $item = $model->find($id);
        if ($item && $item['uploaded_by']) {
            $formattedDate = date('M d, Y', strtotime($newDate));
            $notifModel = new NotificationModel();
            $notifModel->insert([
                'user_id'     => $item['uploaded_by'],
                'sender_id'   => $user['id'],
                'research_id' => $id,
                'message'     => "📅 Deadline Updated: '{$item['title']}' is due on {$formattedDate}.",
                'is_read'     => 0,
                'created_at'  => date('Y-m-d H:i:s')
            ]);
        }
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

    // 14. ADD COMMENT
    public function addComment()
    {
        $this->handleCors();
        $user = $this->validateUser(); 
        
        $commentModel = new \App\Models\ResearchCommentModel();
        $notifModel   = new NotificationModel();
        $researchModel = new ResearchModel();
        $userModel = new UserModel();
        $json = $this->request->getJSON();

        $data = [
            'research_id' => $json->research_id,
            'user_id'     => $json->user_id,
            'user_name'   => $json->user_name,
            'role'        => $json->role,
            'comment'     => $json->comment
        ];

        if ($commentModel->insert($data)) {
            $researchId = $json->research_id;
            $senderId   = $json->user_id;
            $role       = strtolower($json->role); 

            if ($role === 'admin') {
                $research = $researchModel->find($researchId);
                if ($research && isset($research['uploaded_by']) && $research['uploaded_by'] != $senderId) {
                    $notifModel->insert([
                        'user_id'     => $research['uploaded_by'],
                        'sender_id'   => $senderId,
                        'research_id' => $researchId,
                        'message'     => "Admin commented: " . substr($json->comment, 0, 15) . "...",
                        'is_read'     => 0,
                        'created_at'  => date('Y-m-d H:i:s')
                    ]);
                }
            } else {
                // If student comments, notify ALL admins
                $admins = $userModel->where('role', 'admin')->findAll();
                foreach ($admins as $admin) {
                     // Don't notify self if admin is somehow commenting as a student (rare edge case)
                     if ($admin['id'] != $senderId) {
                        $notifModel->insert([
                            'user_id'     => $admin['id'],
                            'sender_id'   => $senderId,
                            'research_id' => $researchId,
                            'message'     => "New comment by {$json->user_name}",
                            'is_read'     => 0,
                            'created_at'  => date('Y-m-d H:i:s')
                        ]);
                     }
                }
            }
            return $this->respondCreated(['status' => 'success']);
        }
        return $this->fail('Failed to save comment');
    }

    // STATS
    public function stats()
    {
        $this->handleCors();
        $model = new ResearchModel();
        $approved = $model->where('status', 'approved')->countAllResults();
        $pending = $model->where('status', 'pending')->countAllResults();
        return $this->respond(['total' => $approved, 'pending' => $pending]);
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
        return $this->respond(['published' => $myPublished, 'pending' => $myPending]);
    }

    // ✅ NEW: CSV IMPORT (Mapped to Short Headers + Auto Line Ending)
    public function importCsv()
    {
        $this->handleCors();

        // ✅ FIX: Force PHP to detect Mac/Windows/Linux line endings
        ini_set('auto_detect_line_endings', TRUE);

        $userId = 1; // Default to Admin

        $file = $this->request->getFile('csv_file');
        if (!$file->isValid() || $file->getExtension() !== 'csv') {
            return $this->response->setJSON(['message' => 'Invalid CSV file'])->setStatusCode(400);
        }

        $csvData = array_map('str_getcsv', file($file->getTempName()));
        $headers = array_map('trim', $csvData[0]); 
        array_shift($csvData); 

        $count = 0;
        $skipped = 0;
        
        $researchModel = new ResearchModel();
        $detailsModel = new ResearchDetailsModel();

        foreach ($csvData as $row) {
            if (count($row) < count($headers)) continue;
            
            // Raw data from CSV
            $rawData = array_combine($headers, $row);

            // ✅ MAP SHORT HEADERS TO DB COLUMNS
            $data = [
                'title'                => $rawData['Title'] ?? 'Untitled',
                'knowledge_type'       => $rawData['Type'] ?? 'Research Paper',
                'author'               => $rawData['Authors'] ?? 'Unknown',
                'publication_date'     => $rawData['Date'] ?? null,
                'edition'              => $rawData['Publication'] ?? '', // 'Publication' maps to edition/issue in your sample
                'publisher'            => $rawData['Publisher'] ?? '',
                'physical_description' => $rawData['Pages'] ?? '',       // 'Pages' maps to physical desc
                'isbn_issn'            => $rawData['ISSN'] ?? '',        // 'ISSN' maps to isbn_issn
                'subjects'             => $rawData['Description'] ?? '', // 'Description' maps to subjects/keywords
                'shelf_location'       => $rawData['Location'] ?? '',
                'item_condition'       => $rawData['Condition'] ?? 'Good',
                'crop_variation'       => $rawData['Crop'] ?? ''         // 'Crop' maps to crop_variation
            ];

            $isbn = trim($data['isbn_issn']);
            $title = trim($data['title']);
            $edition = trim($data['edition']);

            // ✅ CALL SMART DUPLICATE CHECKER
            $dupError = $this->checkDuplicate($title, $isbn, $edition);

            if ($dupError) {
                $skipped++;
                continue;
            }

            // Insert into Researches
            $mainData = [
                'title'          => $title,
                'author'         => $data['author'],
                'crop_variation' => $data['crop_variation'],
                'status'         => 'approved',
                'uploaded_by'    => $userId,
                'created_at'     => date('Y-m-d H:i:s')
            ];

            $newId = $researchModel->insert($mainData);

            if ($newId) {
                $detailsData = [
                    'research_id'          => $newId,
                    'knowledge_type'       => $data['knowledge_type'],
                    'publication_date'     => $data['publication_date'],
                    'edition'              => $data['edition'],
                    'publisher'            => $data['publisher'],
                    'physical_description' => $data['physical_description'],
                    'isbn_issn'            => $data['isbn_issn'],
                    'subjects'             => $data['subjects'],
                    'shelf_location'       => $data['shelf_location'],
                    'item_condition'       => $data['item_condition'],
                    'link'                 => ''
                ];
                $detailsModel->insert($detailsData);
                $count++;
            }
        }

        return $this->response->setJSON([
            'status' => 'success',
            'count' => $count,
            'skipped' => $skipped,
            'message' => "Import successful. Added: $count. Skipped (Duplicates): $skipped."
        ]);
    }
}