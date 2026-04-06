<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\StorageService;
use CodeIgniter\API\ResponseTrait;

class StorageController extends BaseController
{
    use ResponseTrait;

    protected StorageService $storageService;
    protected AuthService $authService;

    public function __construct()
    {
        $this->storageService = new StorageService();
        $this->authService = new AuthService();
        helper('activity');
    }

    public function index()
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->failUnauthorized('Unauthorized Access. Please login.');
        }

        $path = (string) ($this->request->getGet('path') ?? '/');
        $summary = $this->storageService->getStorageSummary((int) $user->id, $path);

        return $this->respond([
            'status' => 'success',
            'data' => $summary,
        ]);
    }

    public function recycleBin()
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->failUnauthorized('Unauthorized Access. Please login.');
        }

        $summary = $this->storageService->getRecycleBinSummary((int) $user->id);

        return $this->respond([
            'status' => 'success',
            'data' => $summary,
        ]);
    }

    public function upload()
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->failUnauthorized('Unauthorized Access. Please login.');
        }

        try {
            $folderPath = (string) ($this->request->getPost('folder_path') ?? '/');
            $result = $this->storageService->uploadUserFile(
                (int) $user->id,
                $this->request->getFile('file'),
                $folderPath
            );

            log_activity(
                (int) $user->id,
                (string) $user->name,
                (string) $user->role,
                'STORAGE_UPLOAD',
                'Uploaded workspace file: ' . ($result['file']['original_name'] ?? 'unknown')
            );

            return $this->respondCreated([
                'status' => 'success',
                'message' => 'File uploaded successfully.',
                'file' => $result['file'],
                'storage' => $result['storage'],
            ]);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), $this->resolveHttpCode($e));
        } catch (\Throwable $e) {
            log_message('error', '[Storage Upload] ' . $e->getMessage());
            return $this->failServerError('Failed to upload file.');
        }
    }

    public function createFolder()
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->failUnauthorized('Unauthorized Access. Please login.');
        }

        try {
            $body = $this->request->getJSON(true);
            $folderName = trim((string) ($body['name'] ?? $this->request->getPost('name') ?? ''));
            $folderPath = (string) ($body['path'] ?? $this->request->getPost('path') ?? '/');

            $result = $this->storageService->createFolder(
                (int) $user->id,
                $folderName,
                $folderPath
            );

            log_activity(
                (int) $user->id,
                (string) $user->name,
                (string) $user->role,
                'STORAGE_FOLDER_CREATE',
                'Created folder: ' . ($result['folder']['original_name'] ?? 'unknown')
            );

            return $this->respondCreated([
                'status' => 'success',
                'message' => 'Folder created successfully.',
                'folder' => $result['folder'],
                'storage' => $result['storage'],
            ]);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), $this->resolveHttpCode($e));
        } catch (\Throwable $e) {
            log_message('error', '[Storage Folder Create] ' . $e->getMessage());
            return $this->failServerError('Failed to create folder.');
        }
    }

    public function deleteFile($id = null)
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->failUnauthorized('Unauthorized Access. Please login.');
        }

        $fileId = (int) $id;

        try {
            $body = $this->request->getJSON(true);
            $currentPath = (string) ($body['path'] ?? $this->request->getPost('path') ?? '/');
            $result = $this->storageService->deleteUserFile((int) $user->id, $fileId, $currentPath);

            log_activity(
                (int) $user->id,
                (string) $user->name,
                (string) $user->role,
                'STORAGE_TRASH',
                'Moved workspace item to recycle bin. ID: ' . $fileId
            );

            return $this->respond([
                'status' => 'success',
                'message' => 'Item moved to recycle bin.',
                'deleted_item_id' => $result['deleted_item_id'],
                'storage' => $result['storage'],
            ]);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), $this->resolveHttpCode($e));
        } catch (\Throwable $e) {
            log_message('error', '[Storage Delete] ' . $e->getMessage());
            return $this->failServerError('Failed to delete file.');
        }
    }

    public function moveItem($id = null)
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->failUnauthorized('Unauthorized Access. Please login.');
        }

        $itemId = (int) $id;

        try {
            $body = $this->request->getJSON(true);
            $targetPath = (string) ($body['target_path'] ?? $this->request->getPost('target_path') ?? '/');
            $currentPath = (string) ($body['path'] ?? $this->request->getPost('path') ?? '/');

            $result = $this->storageService->moveItem((int) $user->id, $itemId, $targetPath, $currentPath);

            log_activity(
                (int) $user->id,
                (string) $user->name,
                (string) $user->role,
                'STORAGE_MOVE',
                'Moved workspace item ID ' . $itemId . ' to ' . $targetPath
            );

            return $this->respond([
                'status' => 'success',
                'message' => 'Item moved successfully.',
                'moved_item_id' => $result['moved_item_id'],
                'storage' => $result['storage'],
            ]);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), $this->resolveHttpCode($e));
        } catch (\Throwable $e) {
            log_message('error', '[Storage Move] ' . $e->getMessage());
            return $this->failServerError('Failed to move item.');
        }
    }

    public function copyItem($id = null)
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->failUnauthorized('Unauthorized Access. Please login.');
        }

        $itemId = (int) $id;

        try {
            $body = $this->request->getJSON(true);
            $targetPath = (string) ($body['target_path'] ?? $this->request->getPost('target_path') ?? '/');
            $currentPath = (string) ($body['path'] ?? $this->request->getPost('path') ?? '/');

            $result = $this->storageService->copyItem((int) $user->id, $itemId, $targetPath, $currentPath);

            log_activity(
                (int) $user->id,
                (string) $user->name,
                (string) $user->role,
                'STORAGE_COPY',
                'Copied workspace item ID ' . $itemId . ' to ' . $targetPath
            );

            return $this->respond([
                'status' => 'success',
                'message' => 'Item copied successfully.',
                'copied_item_id' => $result['copied_item_id'],
                'storage' => $result['storage'],
            ]);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), $this->resolveHttpCode($e));
        } catch (\Throwable $e) {
            log_message('error', '[Storage Copy] ' . $e->getMessage());
            return $this->failServerError('Failed to copy item.');
        }
    }

    public function restoreItem($id = null)
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->failUnauthorized('Unauthorized Access. Please login.');
        }

        $itemId = (int) $id;

        try {
            $body = $this->request->getJSON(true);
            $currentPath = (string) ($body['path'] ?? $this->request->getPost('path') ?? '/');
            $result = $this->storageService->restoreDeletedItem((int) $user->id, $itemId, $currentPath);

            log_activity(
                (int) $user->id,
                (string) $user->name,
                (string) $user->role,
                'STORAGE_RESTORE',
                'Restored workspace item ID: ' . $itemId
            );

            return $this->respond([
                'status' => 'success',
                'message' => 'Item restored successfully.',
                'restored_item_id' => $result['restored_item_id'],
                'storage' => $result['storage'],
                'recycle_bin' => $result['recycle_bin'],
            ]);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), $this->resolveHttpCode($e));
        } catch (\Throwable $e) {
            log_message('error', '[Storage Restore] ' . $e->getMessage());
            return $this->failServerError('Failed to restore item.');
        }
    }

    public function deletePermanently($id = null)
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->failUnauthorized('Unauthorized Access. Please login.');
        }

        $itemId = (int) $id;

        try {
            $body = $this->request->getJSON(true);
            $currentPath = (string) ($body['path'] ?? $this->request->getPost('path') ?? '/');
            $result = $this->storageService->deleteItemPermanently((int) $user->id, $itemId, $currentPath);

            log_activity(
                (int) $user->id,
                (string) $user->name,
                (string) $user->role,
                'STORAGE_PERMANENT_DELETE',
                'Permanently deleted workspace item ID: ' . $itemId
            );

            return $this->respond([
                'status' => 'success',
                'message' => 'Item permanently deleted.',
                'deleted_item_id' => $result['deleted_item_id'],
                'storage' => $result['storage'],
                'recycle_bin' => $result['recycle_bin'],
            ]);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), $this->resolveHttpCode($e));
        } catch (\Throwable $e) {
            log_message('error', '[Storage Permanent Delete] ' . $e->getMessage());
            return $this->failServerError('Failed to permanently delete item.');
        }
    }

    public function download($id = null)
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->failUnauthorized('Unauthorized Access. Please login.');
        }

        $fileId = (int) $id;

        try {
            $payload = $this->storageService->getDownloadPayload((int) $user->id, $fileId);

            return $this->response
                ->download($payload['path'], null)
                ->setFileName((string) $payload['original_name']);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), $this->resolveHttpCode($e));
        } catch (\Throwable $e) {
            log_message('error', '[Storage Download] ' . $e->getMessage());
            return $this->failServerError('Failed to download file.');
        }
    }

    public function open($id = null)
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->failUnauthorized('Unauthorized Access. Please login.');
        }

        $fileId = (int) $id;

        try {
            $payload = $this->storageService->getDownloadPayload((int) $user->id, $fileId);
            $filePath = (string) $payload['path'];
            $originalName = (string) $payload['original_name'];

            if (!is_file($filePath)) {
                return $this->failNotFound('File not found on disk.');
            }

            $mime = mime_content_type($filePath) ?: 'application/octet-stream';
            $size = filesize($filePath);

            return $this->response
                ->setHeader('Content-Type', $mime)
                ->setHeader('Content-Disposition', 'inline; filename="' . addcslashes($originalName, '"\\') . '"')
                ->setHeader('Content-Length', (string) $size)
                ->setHeader('Cache-Control', 'private, max-age=3600')
                ->setBody(file_get_contents($filePath));
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), $this->resolveHttpCode($e));
        } catch (\Throwable $e) {
            log_message('error', '[Storage Open] ' . $e->getMessage());
            return $this->failServerError('Failed to open file.');
        }
    }

    private function getUser()
    {
        $token = $this->request->getHeaderLine('Authorization');
        return $this->authService->validateUser($token);
    }

    private function resolveHttpCode(\RuntimeException $e): int
    {
        $code = (int) $e->getCode();
        if ($code >= 400 && $code <= 599) {
            return $code;
        }

        return 400;
    }
}
