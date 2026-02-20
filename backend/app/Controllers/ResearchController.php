<?php

namespace App\Controllers;

use App\Services\ResearchService;
use App\Services\AuthService;
use CodeIgniter\API\ResponseTrait;

class ResearchController extends BaseController
{
    use ResponseTrait;

    protected $researchService;
    protected $authService;

    public function __construct()
    {
        $this->researchService = new ResearchService();
        $this->authService = new AuthService();
        helper('activity'); // Load Helper
    }

    // --- SECURITY HELPER ---
    protected function getUser()
    {
        $request = service('request');
        // The user is attached to the request by the AuthFilter
        // Or we can just get it here to be safe if the filter wasn't structured to attach it.
        // Let's rely on the token/session directly here to get the Entity
        $token = $request->getHeaderLine('Authorization');
        return $this->authService->validateUser($token);
    }

    // 1. PUBLIC INDEX
    public function index()
    {
        $startDate = $this->request->getGet('start_date');
        $endDate = $this->request->getGet('end_date');

        $data = $this->researchService->getAllApproved($startDate, $endDate);
        return $this->respond($data);
    }

    // 2. MY SUBMISSIONS
    public function mySubmissions()
    {
        $user = $this->getUser();

        $data = $this->researchService->getMySubmissions($user->id);
        return $this->respond($data);
    }

    // 3. MY ARCHIVED
    public function myArchived()
    {
        $user = $this->getUser();

        $data = $this->researchService->getMyArchived($user->id);
        return $this->respond($data);
    }

    public function archived()
    {
        $user = $this->getUser();
        if ($user->role !== 'admin')
            return $this->failForbidden('Access Denied');

        $data = $this->researchService->getAllArchived();
        return $this->respond($data);
    }

    // 4. PENDING LIST
    public function pending()
    {
        $user = $this->getUser();
        if ($user->role !== 'admin')
            return $this->failForbidden('Access Denied');

        $data = $this->researchService->getPending();
        return $this->respond($data);
    }

    // 5. REJECTED LIST
    public function rejectedList()
    {
        $user = $this->getUser();
        if ($user->role !== 'admin')
            return $this->failForbidden();

        $data = $this->researchService->getRejected();
        return $this->respond($data);
    }

    // --- VALIDATION RULES HELPER ---
    private function getValidationRules()
    {
        return [
            'title' => 'required|min_length[3]|max_length[255]',
            'author' => 'required|min_length[2]|max_length[255]',
            'knowledge_type' => 'required|max_length[100]',
            'publication_date' => 'permit_empty|valid_date',
            'start_date' => 'permit_empty|valid_date',
            'deadline_date' => 'permit_empty|valid_date',
            'edition' => 'permit_empty|max_length[50]',
            'publisher' => 'permit_empty|max_length[255]',
            'physical_description' => 'permit_empty|max_length[255]',
            'isbn_issn' => 'permit_empty|max_length[50]|alpha_numeric_punct',
            'subjects' => 'permit_empty|string',
            'shelf_location' => 'permit_empty|max_length[100]',
            'item_condition' => 'permit_empty|max_length[50]',
            'crop_variation' => 'permit_empty|max_length[100]',
            'link' => 'permit_empty|valid_url_strict',
        ];
    }

    // 6. CREATE
    public function create()
    {
        try {
            $user = $this->getUser();

            // Handle JSON vs Form Data
            // Wrap getJSON to prevent FormatException on file uploads
            $input = $this->request->getPost(); // Try POST first for file uploads
            if (empty($input)) {
                $rawInput = $this->request->getJSON(true); // Only try JSON if POST matches nothing
                if (!empty($rawInput))
                    $input = $rawInput;
            }

            // Validate
            $validation = \Config\Services::validation();
            $validation->setRules($this->getValidationRules());

            if (!$validation->run($input)) {
                return $this->response->setJSON(['status' => 'error', 'messages' => $validation->getErrors()])->setStatusCode(400);
            }

            // Duplicate Check
            $title = trim($input['title'] ?? '');
            $author = trim($input['author'] ?? '');
            $edition = trim($input['edition'] ?? '');
            $isbn = trim($input['isbn_issn'] ?? '');

            $dupError = $this->researchService->checkDuplicate($title, $author, $isbn, $edition);
            if ($dupError) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'messages' => ['duplicate' => $dupError]
                ])->setStatusCode(400);
            }

            // File is optional/handled by service (safe to pass invalid/null)
            $this->researchService->createResearch($user->id, $input, $this->request->getFile('pdf_file'));

            // LOG
            log_activity($user->id, $user->name, $user->role, 'CREATE_RESEARCH', "Created research: " . ($input['title'] ?? 'Untitled'));

            return $this->respond(['status' => 'success']);
        }
        catch (\Throwable $e) {
            log_message('error', '[Research Create] ' . $e->getMessage());
            return $this->failServerError('Server Error: ' . $e->getMessage());
        }
    }

    // 7. UPDATE
    public function update($id = null)
    {
        try {
            // Allow POST (standard) or PUT (often JSON)
            // Check method yourself or trust CI4 routing. Route says POST.

            $user = $this->getUser();

            // Handle JSON vs Form Data
            $input = $this->request->getPost();
            if (empty($input)) {
                $rawInput = $this->request->getJSON(true);
                if (!empty($rawInput))
                    $input = $rawInput;
            }

            // Validate
            $validation = \Config\Services::validation();
            $validation->setRules($this->getValidationRules());

            if (!$validation->run($input)) {
                return $this->response->setJSON(['status' => 'error', 'messages' => $validation->getErrors()])->setStatusCode(400);
            }

            $title = trim($input['title'] ?? '');
            $author = trim($input['author'] ?? '');
            $edition = trim($input['edition'] ?? '');
            $isbn = trim($input['isbn_issn'] ?? '');

            $dupError = $this->researchService->checkDuplicate($title, $author, $isbn, $edition, $id);
            if ($dupError) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'messages' => ['duplicate' => $dupError]
                ])->setStatusCode(400);
            }

            $this->researchService->updateResearch($id, $user->id, $user->role, $input, $this->request->getFile('pdf_file'));

            // LOG
            log_activity($user->id, $user->name, $user->role, 'UPDATE_RESEARCH', "Updated research ID: $id (" . ($input['title'] ?? '') . ")");

            return $this->respond(['status' => 'success']);
        }
        catch (\Throwable $e) {
            log_message('error', '[Research Update] ' . $e->getMessage());
            if ($e->getCode() == 403)
                return $this->failForbidden();
            return $this->failServerError('Server Error: ' . $e->getMessage());
        }
    }

    // 8. APPROVE
    public function approve($id = null)
    {
        $user = $this->getUser();
        if ($user->role !== 'admin')
            return $this->failForbidden();

        $item = $this->researchService->getResearch($id);
        $title = $item ? $item->title : "ID: $id";

        $this->researchService->setStatus($id, 'approved', $user->id, "🎉 Your research '%s' has been APPROVED!");

        log_activity($user->id, $user->name, $user->role, 'APPROVE_RESEARCH', "Approved research: $title");

        return $this->respond(['status' => 'success']);
    }

    // 9. REJECT
    public function reject($id = null)
    {
        $user = $this->getUser();
        if ($user->role !== 'admin')
            return $this->failForbidden();

        $item = $this->researchService->getResearch($id);
        $title = $item ? $item->title : "ID: $id";

        $this->researchService->setStatus($id, 'rejected', $user->id, "⚠️ Your research '%s' was returned for revision.");

        log_activity($user->id, $user->name, $user->role, 'REJECT_RESEARCH', "Rejected research: $title");

        return $this->respond(['status' => 'success']);
    }

    // 10. ARCHIVE
    public function archive($id = null)
    {
        $user = $this->getUser();

        $item = $this->researchService->getResearch($id);
        if (!$item)
            return $this->failNotFound();

        // Prevent repeated actions
        if ($item->status === 'archived') {
            return $this->respond(['status' => 'success', 'message' => 'Already archived']);
        }

        $title = $item->title;

        $this->researchService->setStatus($id, 'archived', $user->id, "Your research '%s' has been archived.");

        log_activity($user->id, $user->name, $user->role, 'ARCHIVE_RESEARCH', "Archived research: $title");

        return $this->respond(['status' => 'success']);
    }

    // 11. RESTORE
    public function restore($id = null)
    {
        $user = $this->getUser();

        $item = $this->researchService->getResearch($id);
        if (!$item)
            return $this->failNotFound();

        // Prevent repeated actions
        if ($item->status !== 'archived') {
            return $this->respond(['status' => 'success', 'message' => 'Item is not archived']);
        }

        $title = $item->title;

        $this->researchService->setStatus($id, 'pending', $user->id, "Research '%s' restored.");

        log_activity($user->id, $user->name, $user->role, 'RESTORE_RESEARCH', "Restored research: $title");

        return $this->respond(['status' => 'success']);
    }

    // 12. EXTEND DEADLINE
    public function extendDeadline($id = null)
    {
        $user = $this->getUser();
        if ($user->role !== 'admin')
            return $this->failForbidden();

        $newDate = $this->request->getPost('new_deadline');
        if (!$newDate)
            return $this->fail('Date is required.');

        $this->researchService->extendDeadline($id, $newDate, $user->id);
        return $this->respond(['status' => 'success']);
    }

    // 13. COMMENTS
    public function getComments($id = null)
    {
        $data = $this->researchService->getComments($id);
        return $this->respond($data);
    }

    // 14. ADD COMMENT
    public function addComment()
    {
        $user = $this->getUser();
        $json = $this->request->getJSON();

        $data = [
            'research_id' => $json->research_id,
            'user_id' => $json->user_id,
            'user_name' => $json->user_name,
            'role' => $json->role,
            'comment' => $json->comment
        ];

        if ($this->researchService->addComment($data)) {
            return $this->respondCreated(['status' => 'success']);
        }
        return $this->fail('Failed to save comment');
    }

    // STATS
    public function stats()
    {
        return $this->respond($this->researchService->getStats());
    }

    // MASTERLIST (Admin only - all entries)
    public function masterlist()
    {
        $user = $this->getUser();
        if ($user->role !== 'admin')
            return $this->failForbidden('Access Denied');

        $data = $this->researchService->getAll();
        return $this->respond($data);
    }

    // USER STATS
    public function userStats($userId = null)
    {
        if (!$userId)
            return $this->fail('User ID required');
        return $this->respond($this->researchService->getUserStats($userId));
    }

    // CSV IMPORT
    public function importCsv()
    {
        $user = $this->getUser();
        if ($user->role !== 'admin') {
            return $this->failForbidden('Access Denied');
        }

        $file = $this->request->getFile('csv_file');

        if (!$file) {
            return $this->response->setJSON(['message' => 'No CSV file uploaded'])->setStatusCode(400);
        }

        if (!$file->isValid() || $file->getExtension() !== 'csv') {
            return $this->response->setJSON(['message' => 'Invalid or empty CSV file'])->setStatusCode(400);
        }

        try {
            $result = $this->researchService->importCsv($file->getTempName(), $user->id);
            return $this->response->setJSON([
                'status' => 'success',
                'count' => $result['count'],
                'skipped' => $result['skipped'],
                'message' => "Import successful. Added: {$result['count']}. Skipped (Duplicates): {$result['skipped']}."
            ]);
        }
        catch (\Throwable $e) {
            log_message('error', '[Research CSV Import] ' . $e->getMessage());
            return $this->failServerError('Server Error: ' . $e->getMessage());
        }
    }

    // SINGLE ROW IMPORT (For Sequential Processing)
    public function importSingle()
    {
        try {
            $user = $this->getUser();

            $input = $this->request->getJSON(true);
            if (empty($input)) {
                return $this->fail('No data provided', 400);
            }

            // Security: Use Logged In User
            $result = $this->researchService->importSingleRow($input, $user->id);

            if ($result['status'] === 'success') {
                log_activity($user->id, $user->name, $user->role, 'IMPORT_SINGLE', "Imported single research: " . ($input['Title'] ?? 'Untitled'));
                return $this->respond(['status' => 'success', 'id' => $result['id']]);
            }
            else {
                return $this->respond(['status' => 'skipped', 'message' => $result['message']]);
            }

        }
        catch (\Throwable $e) {
            log_message('error', '[Import Single] ' . $e->getMessage());
            return $this->failServerError('Server Error: ' . $e->getMessage());
        }
    }

    // BULK PDF UPLOAD
    public function uploadBulkPdfs()
    {
        try {
            $user = $this->getUser();

            $files = $this->request->getFiles();

            // CI4 structure: if input is 'pdf_files[]', getFiles() returns array or object structure.
            // We expect 'pdf_files'
            if (!$files || !isset($files['pdf_files'])) {
                return $this->fail('No files uploaded', 400);
            }

            $pdfFiles = $files['pdf_files'];
            // If single file uploaded, CI4 might allow it not as array? Ensure iterable.
            if (!is_array($pdfFiles)) {
                $pdfFiles = [$pdfFiles];
            }

            if (count($pdfFiles) > 10) {
                return $this->fail('Maximum of 10 files allowed per upload', 400);
            }

            $matched = 0;
            $skipped = 0;
            $details = [];

            foreach ($pdfFiles as $file) {
                if ($file->isValid() && !$file->hasMoved()) {
                    // Filename without extension
                    $originalName = $file->getClientName();
                    $titleCandidate = pathinfo($originalName, PATHINFO_FILENAME);

                    // Call Service to find and attach
                    $resultStatus = $this->researchService->matchAndAttachPdf($titleCandidate, $file);

                    if ($resultStatus === 'linked') {
                        $matched++;
                        $details[] = "Linked: $originalName";
                    }
                    elseif ($resultStatus === 'exists') {
                        $skipped++;
                        $details[] = "Skipped: $originalName (Already has file)";
                    }
                    else {
                        $skipped++;
                        $details[] = "Skipped: $originalName (No match found)";
                    }
                }
            }

            // LOG IT
            $logDetails = "Bulk Upload: Checked " . count($pdfFiles) . " files. Linked: $matched. Skipped: $skipped.";
            log_activity($user->id, $user->name, $user->role, 'BULK_UPLOAD_PDF', $logDetails);

            return $this->response->setJSON([
                'status' => 'success',
                'matched' => $matched,
                'skipped' => $skipped,
                'message' => "Bulk Upload Complete. Linked: $matched. Skipped: $skipped.",
                'details' => $details
            ]);
        }
        catch (\Throwable $e) {
            log_message('error', '[Bulk Upload] ' . $e->getMessage());
            return $this->failServerError('Server Error: ' . $e->getMessage());
        }
    }
}