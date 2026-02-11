<?php

namespace App\Services;

use App\Models\ResearchModel;
use App\Models\ResearchDetailsModel;
use App\Models\ResearchCommentModel;
use App\Models\NotificationModel;
use App\Models\UserModel;

class ResearchService extends BaseService
{
    protected $researchModel;
    protected $detailsModel;
    protected $commentModel;
    protected $notifModel;
    protected $userModel;

    // Helper select string
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

    public function __construct()
    {
        parent::__construct();
        $this->researchModel = new ResearchModel();
        $this->detailsModel = new ResearchDetailsModel();
        $this->commentModel = new ResearchCommentModel();
        $this->notifModel = new NotificationModel();
        $this->userModel = new UserModel();
    }

    // --- READ METHODS ---

    public function getAllApproved()
    {
        return $this->researchModel->select($this->selectString)
            ->join('research_details', 'researches.id = research_details.research_id', 'left')
            ->where('researches.status', 'approved')
            ->orderBy('researches.created_at', 'DESC')
            ->findAll();
    }

    public function getAll()
    {
        return $this->researchModel->select($this->selectString)
            ->join('research_details', 'researches.id = research_details.research_id', 'left')
            ->where('researches.status !=', 'archived')
            ->orderBy('researches.created_at', 'DESC')
            ->findAll();
    }

    public function getMySubmissions(int $userId)
    {
        return $this->researchModel->select($this->selectString)
            ->join('research_details', 'researches.id = research_details.research_id', 'left')
            ->where('researches.uploaded_by', $userId)
            ->where('researches.status !=', 'archived')
            ->orderBy('researches.created_at', 'DESC')
            ->findAll();
    }

    public function getMyArchived(int $userId)
    {
        // Auto-delete old archived
        $cutoffDate = date('Y-m-d H:i:s', strtotime('-60 days'));
        $this->researchModel->where('uploaded_by', $userId)
            ->where('status', 'archived')
            ->where('archived_at <', $cutoffDate)
            ->delete();

        return $this->researchModel->select($this->selectString)
            ->join('research_details', 'researches.id = research_details.research_id', 'left')
            ->where('researches.uploaded_by', $userId)
            ->where('researches.status', 'archived')
            ->orderBy('researches.archived_at', 'DESC')
            ->findAll();
    }

    public function getAllArchived()
    {
        return $this->researchModel->select($this->selectString)
            ->join('research_details', 'researches.id = research_details.research_id', 'left')
            ->where('researches.status', 'archived')
            ->orderBy('researches.archived_at', 'DESC')
            ->findAll();
    }

    public function getPending()
    {
        return $this->researchModel->select($this->selectString)
            ->join('research_details', 'researches.id = research_details.research_id', 'left')
            ->where('researches.status', 'pending')
            ->orderBy('researches.created_at', 'ASC')
            ->findAll();
    }

    public function getRejected()
    {
        // Auto-delete old rejected
        $cutoffDate = date('Y-m-d H:i:s', strtotime('-30 days'));
        $this->researchModel->where('status', 'rejected')->where('rejected_at <', $cutoffDate)->delete();

        return $this->researchModel->select($this->selectString)
            ->join('research_details', 'researches.id = research_details.research_id', 'left')
            ->where('researches.status', 'rejected')
            ->orderBy('researches.rejected_at', 'DESC')
            ->findAll();
    }

    public function getStats()
    {
        $approved = $this->researchModel->where('status', 'approved')->countAllResults();
        $pending = $this->researchModel->where('status', 'pending')->countAllResults();
        return ['total' => $approved, 'pending' => $pending];
    }

    public function getUserStats(int $userId)
    {
        $myPublished = $this->researchModel->where('uploaded_by', $userId)->where('status', 'approved')->countAllResults();
        $myPending = $this->researchModel->where('uploaded_by', $userId)->where('status', 'pending')->countAllResults();
        return ['published' => $myPublished, 'pending' => $myPending];
    }

    public function getComments($researchId)
    {
        return $this->commentModel->where('research_id', $researchId)->orderBy('created_at', 'ASC')->findAll();
    }

    // --- WRITE METHODS ---

    public function checkDuplicate($title, $author, $isbn, $edition, $excludeId = null)
    {
        $builder = $this->db->table('researches');
        $builder->join('research_details', 'researches.id = research_details.research_id');

        // 1. Strict Title Check
        $builder->where('researches.title', $title);

        // 2. Strict Author Check (to allow same title by different authors)
        $builder->where('researches.author', $author);

        // 3. Strict Edition Check
        // If edition provided, match it. If empty, match ONLY empty/null editions.
        if (!empty($edition)) {
            $builder->where('research_details.edition', $edition);
        }
        else {
            $builder->groupStart()
                ->where('research_details.edition', '')
                ->orWhere('research_details.edition', null)
                ->groupEnd();
        }

        if ($excludeId) {
            $builder->where('researches.id !=', $excludeId);
        }

        if ($builder->countAllResults() > 0) {
            return "Duplicate! This Title/Author/Edition combination already exists.";
        }



        return false;
    }

    public function createResearch(int $userId, array $data, $file)
    {
        $this->db->transStart();

        $fileName = null;
        if ($file && $file->isValid() && !$file->hasMoved()) {
            $fileName = $file->getRandomName();
            $file->move(ROOTPATH . 'public/uploads', $fileName);
        }

        $mainData = [
            'uploaded_by' => $userId,
            'title' => $data['title'],
            'author' => $data['author'],
            'crop_variation' => $data['crop_variation'],
            'status' => 'pending',
            'file_path' => $fileName,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $newResearchId = $this->researchModel->insert($mainData);

        // Create Logic
        $knowledgeType = $data['knowledge_type'];
        if (is_array($knowledgeType)) {
            $knowledgeType = implode(', ', $knowledgeType);
        }

        $detailsData = [
            'research_id' => $newResearchId,
            'knowledge_type' => $knowledgeType,
            'publication_date' => $data['publication_date'],
            'edition' => $data['edition'],
            'publisher' => $data['publisher'],
            'physical_description' => $data['physical_description'],
            'isbn_issn' => $data['isbn_issn'],
            'subjects' => $data['subjects'],
            'shelf_location' => $data['shelf_location'],
            'item_condition' => $data['item_condition'],
            'link' => $data['link'],
        ];
        $this->detailsModel->insert($detailsData);

        // Notify Admins
        $admins = $this->userModel->where('role', 'admin')->findAll();

        foreach ($admins as $admin) {
            $this->notifModel->insert([
                'user_id' => $admin->id, // Entity access
                'sender_id' => $userId,
                'research_id' => $newResearchId,
                'message' => "New Submission: " . $data['title'],
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new \Exception("Research creation failed.");
        }

        return $newResearchId;
    }

    public function updateResearch(int $id, int $userId, string $userRole, array $data, $file)
    {
        $item = $this->researchModel->find($id);

        if (!$item || ($item->uploaded_by != $userId && $userRole !== 'admin')) {
            throw new \Exception("Generic Forbidden", 403);
        }

        $this->db->transStart();

        $mainUpdate = [
            'title' => $data['title'],
            'author' => $data['author'],
            'crop_variation' => $data['crop_variation'],
        ];

        if ($file && $file->isValid() && !$file->hasMoved()) {
            $newName = $file->getRandomName();
            $file->move('public/uploads', $newName);
            $mainUpdate['file_path'] = $newName;
        }
        $this->researchModel->update($id, $mainUpdate);

        $exists = $this->detailsModel->where('research_id', $id)->first();

        $knowledgeType = $data['knowledge_type'];
        if (is_array($knowledgeType)) {
            $knowledgeType = implode(', ', $knowledgeType);
        }

        $detailsData = [
            'knowledge_type' => $knowledgeType,
            'publication_date' => $data['publication_date'],
            'edition' => $data['edition'],
            'publisher' => $data['publisher'],
            'physical_description' => $data['physical_description'],
            'isbn_issn' => $data['isbn_issn'],
            'subjects' => $data['subjects'],
            'shelf_location' => $data['shelf_location'],
            'item_condition' => $data['item_condition'],
            'link' => $data['link'],
        ];

        if ($exists) {
            $this->detailsModel->where('research_id', $id)->set($detailsData)->update();
        }
        else {
            $detailsData['research_id'] = $id;
            $this->detailsModel->insert($detailsData);
        }

        $this->db->transComplete();
        return true;
    }

    public function setStatus(int $id, string $status, int $adminId, string $messageTemplate)
    {
        // For Approve/Reject/Archive
        $data = ['status' => $status];
        if ($status === 'approved')
            $data['approved_at'] = date('Y-m-d H:i:s');
        if ($status === 'rejected')
            $data['rejected_at'] = date('Y-m-d H:i:s');
        if ($status === 'archived')
            $data['archived_at'] = date('Y-m-d H:i:s');

        // For Restore
        if ($status === 'pending') {
            $data['rejected_at'] = null;
            $data['archived_at'] = null;
        }

        $this->db->transStart();
        $this->researchModel->update($id, $data);

        $item = $this->researchModel->find($id);
        if ($item && $item->uploaded_by) {
            $this->notifModel->insert([
                'user_id' => $item->uploaded_by,
                'sender_id' => $adminId,
                'research_id' => $id,
                'message' => sprintf($messageTemplate, $item->title),
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        $this->db->transComplete();
    }

    public function extendDeadline($id, $newDate, $adminId)
    {
        $this->db->transStart();
        $this->researchModel->update($id, ['deadline_date' => $newDate]);

        $item = $this->researchModel->find($id);
        if ($item && $item->uploaded_by) {
            $formattedDate = date('M d, Y', strtotime($newDate));
            $this->notifModel->insert([
                'user_id' => $item->uploaded_by,
                'sender_id' => $adminId,
                'research_id' => $id,
                'message' => "📅 Deadline Updated: '{$item->title}' is due on {$formattedDate}.",
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        $this->db->transComplete();
    }

    public function addComment($data)
    {
        if ($this->commentModel->insert($data)) {
            $researchId = $data['research_id'];
            $senderId = $data['user_id'];
            $role = strtolower($data['role']);
            $commentText = $data['comment'];

            if ($role === 'admin') {
                $research = $this->researchModel->find($researchId);
                if ($research && isset($research->uploaded_by) && $research->uploaded_by != $senderId) {
                    $this->notifModel->insert([
                        'user_id' => $research->uploaded_by,
                        'sender_id' => $senderId,
                        'research_id' => $researchId,
                        'message' => "Admin commented: " . substr($commentText, 0, 15) . "...",
                        'is_read' => 0,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
            else {
                $admins = $this->userModel->where('role', 'admin')->findAll();
                foreach ($admins as $admin) {
                    if ($admin->id != $senderId) {
                        $this->notifModel->insert([
                            'user_id' => $admin->id,
                            'sender_id' => $senderId,
                            'research_id' => $researchId,
                            'message' => "New comment by {$data['user_name']}",
                            'is_read' => 0,
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }
            }
            return true;
        }
        return false;
    }

    public function importCsv($fileTempName)
    {
        ini_set('auto_detect_line_endings', TRUE);
        $userId = 1; // Default System User?

        $csvData = array_map('str_getcsv', file($fileTempName));
        $headers = array_map('trim', $csvData[0]);
        array_shift($csvData);

        $count = 0;
        $skipped = 0;

        $this->db->transStart();

        foreach ($csvData as $row) {
            if (count($row) < count($headers))
                continue;

            $rawData = array_combine($headers, $row);

            $data = [
                'title' => $rawData['Title'] ?? 'Untitled',
                'knowledge_type' => $rawData['Type'] ?? 'Research Paper',
                'author' => $rawData['Author'] ?? $rawData['Authors'] ?? 'Unknown',
                'publication_date' => $rawData['Date'] ?? null,
                'edition' => $rawData['Edition'] ?? $rawData['Publication'] ?? '',
                'publisher' => $rawData['Publisher'] ?? '',
                'physical_description' => $rawData['Pages'] ?? '',
                'isbn_issn' => $rawData['ISBN/ISSN'] ?? $rawData['ISSN'] ?? $rawData['ISBN'] ?? '',
                'subjects' => $rawData['Subjects'] ?? $rawData['Description'] ?? '',
                'shelf_location' => $rawData['Location'] ?? '',
                'item_condition' => $rawData['Condition'] ?? 'Good',
                'crop_variation' => $rawData['Crop'] ?? ''
            ];

            $isbn = trim($data['isbn_issn']);
            $title = trim($data['title']);
            $edition = trim($data['edition']);

            $dupError = $this->checkDuplicate($title, $data['author'], $isbn, $edition);

            if ($dupError) {
                $skipped++;
                continue;
            }

            $mainData = [
                'title' => $title,
                'author' => $data['author'],
                'crop_variation' => $data['crop_variation'],
                'status' => 'approved',
                'uploaded_by' => $userId,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $newId = $this->researchModel->insert($mainData);

            if ($newId) {
                $detailsData = [
                    'research_id' => $newId,
                    'knowledge_type' => $data['knowledge_type'],
                    'publication_date' => $data['publication_date'],
                    'edition' => $data['edition'],
                    'publisher' => $data['publisher'],
                    'physical_description' => $data['physical_description'],
                    'isbn_issn' => $data['isbn_issn'],
                    'subjects' => $data['subjects'],
                    'shelf_location' => $data['shelf_location'],
                    'item_condition' => $data['item_condition'],
                    'link' => ''
                ];
                $this->detailsModel->insert($detailsData);
                $count++;
            }
        }

        $this->db->transComplete();

        return ['count' => $count, 'skipped' => $skipped];
    }
    public function matchAndAttachPdf($titleCandidate, $file)
    {
        // Case-insensitive match.
        // Option 1: Exact match with varying case
        $item = $this->researchModel->like('title', $titleCandidate, 'none')->first();

        if ($item) {
            // CHECK IF EXISTS
            if (!empty($item->file_path)) {
                log_message('error', "Skipped: File already exists for {$item->title}");
                return 'exists';
            }

            $newName = $file->getRandomName();
            $targetPath = ROOTPATH . 'public/uploads';

            log_message('error', "Attempting to move file to: $targetPath with name: $newName");

            if ($file->move($targetPath, $newName)) {
                $this->researchModel->update($item->id, ['file_path' => $newName]);
                log_message('error', "File moved successfully.");
                return 'linked';
            }
            else {
                log_message('error', "File move failed: " . $file->getErrorString());
                return 'error_move';
            }
        }
        else {
            log_message('error', "No match found for title: $titleCandidate");
            return 'no_match';
        }
    }
}
