<?php

namespace App\Services;

use App\Models\UserStorageFileModel;
use CodeIgniter\HTTP\Files\UploadedFile;

class StorageService extends BaseService
{
    private const USER_QUOTA_BYTES = 1073741824; // 1 GB
    private const TRASH_RETENTION_DAYS = 30;
    private const STORAGE_ROOT_RELATIVE = 'writable/uploads/storage';
    private const MAX_MIME_LENGTH = 120;
    private const MAX_FOLDER_PATH_LENGTH = 500;
    private const ITEM_TYPE_FILE = 'file';
    private const ITEM_TYPE_FOLDER = 'folder';

    protected UserStorageFileModel $storageModel;

    public function __construct()
    {
        parent::__construct();
        $this->storageModel = new UserStorageFileModel();
    }

    public function getStorageSummary(int $userId, string $folderPath = '/'): array
    {
        $this->assertValidUserId($userId);
        $this->purgeExpiredDeletedItems($userId);
        $currentPath = $this->sanitizeFolderPath($folderPath);

        $folders = $this->storageModel
            ->where('user_id', $userId)
            ->where('deleted_at', null)
            ->where('item_type', self::ITEM_TYPE_FOLDER)
            ->where('folder_path', $currentPath)
            ->orderBy('original_name', 'ASC')
            ->findAll();

        $files = $this->storageModel
            ->where('user_id', $userId)
            ->where('deleted_at', null)
            ->where('item_type', self::ITEM_TYPE_FILE)
            ->where('folder_path', $currentPath)
            ->orderBy('created_at', 'DESC')
            ->findAll();

        $usedBytes = $this->getUsedBytes($userId);

        return [
            'quota_bytes' => self::USER_QUOTA_BYTES,
            'used_bytes' => $usedBytes,
            'remaining_bytes' => max(0, self::USER_QUOTA_BYTES - $usedBytes),
            'usage_percent' => $this->calculateUsagePercent($usedBytes, self::USER_QUOTA_BYTES),
            'current_path' => $currentPath,
            'parent_path' => $this->getParentPath($currentPath),
            'folders' => array_map([$this, 'normalizeFolderRecord'], $folders),
            'files' => array_map([$this, 'normalizeFileRecord'], $files),
        ];
    }

    public function getRecycleBinSummary(int $userId): array
    {
        $this->assertValidUserId($userId);
        $this->purgeExpiredDeletedItems($userId);

        $deletedItems = $this->storageModel
            ->where('user_id', $userId)
            ->where('deleted_at IS NOT NULL', null, false)
            ->orderBy('deleted_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();

        return [
            'retention_days' => self::TRASH_RETENTION_DAYS,
            'item_count' => count($deletedItems),
            'items' => array_map([$this, 'normalizeRecycleRecord'], $deletedItems),
        ];
    }

    public function uploadUserFile(int $userId, ?UploadedFile $file, string $folderPath = '/'): array
    {
        $this->assertValidUserId($userId);
        $this->purgeExpiredDeletedItems($userId);
        $this->validateUpload($file);

        $currentPath = $this->sanitizeFolderPath($folderPath);

        $sizeBytes = (int) $file->getSize();
        $usedBytes = $this->getUsedBytes($userId);
        $remainingBytes = max(0, self::USER_QUOTA_BYTES - $usedBytes);

        if ($sizeBytes > $remainingBytes) {
            throw new \RuntimeException(
                'Storage quota exceeded. Remaining space: ' . $this->formatBytes($remainingBytes) . '.',
                413
            );
        }

        $originalName = $this->sanitizeOriginalName((string) $file->getClientName());
        $this->ensureItemNameIsAvailable($userId, $currentPath, $originalName);

        $storedName = $this->generateStoredFileName($originalName);
        $mimeType = $this->sanitizeMimeType((string) $file->getClientMimeType());

        $absoluteUserDir = $this->getUserDirectoryPath($userId);
        $this->ensureDirectoryExists($absoluteUserDir);

        if (!$file->move($absoluteUserDir, $storedName)) {
            throw new \RuntimeException('Failed to store uploaded file.', 500);
        }

        $this->storageModel->insert([
            'user_id' => $userId,
            'item_type' => self::ITEM_TYPE_FILE,
            'folder_path' => $currentPath,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
        ]);

        $insertedId = (int) $this->storageModel->getInsertID();
        $record = $this->storageModel->where('id', $insertedId)->where('user_id', $userId)->first();
        if (!is_array($record)) {
            throw new \RuntimeException('Upload succeeded but metadata could not be loaded.', 500);
        }

        return [
            'file' => $this->normalizeFileRecord($record),
            'storage' => $this->getStorageSummary($userId, $currentPath),
        ];
    }

    public function createFolder(int $userId, string $folderName, string $folderPath = '/'): array
    {
        $this->assertValidUserId($userId);
        $this->purgeExpiredDeletedItems($userId);

        $currentPath = $this->sanitizeFolderPath($folderPath);
        $safeFolderName = $this->sanitizeFolderName($folderName);
        $this->ensureItemNameIsAvailable($userId, $currentPath, $safeFolderName);

        $this->storageModel->insert([
            'user_id' => $userId,
            'item_type' => self::ITEM_TYPE_FOLDER,
            'folder_path' => $currentPath,
            'original_name' => $safeFolderName,
            'stored_name' => '',
            'mime_type' => 'inode/directory',
            'size_bytes' => 0,
        ]);

        $insertedId = (int) $this->storageModel->getInsertID();
        $record = $this->storageModel->where('id', $insertedId)->where('user_id', $userId)->first();
        if (!is_array($record)) {
            throw new \RuntimeException('Folder created but metadata could not be loaded.', 500);
        }

        return [
            'folder' => $this->normalizeFolderRecord($record),
            'storage' => $this->getStorageSummary($userId, $currentPath),
        ];
    }

    public function deleteUserFile(int $userId, int $itemId, string $currentPath = '/'): array
    {
        $this->assertValidUserId($userId);
        $this->purgeExpiredDeletedItems($userId);
        if ($itemId <= 0) {
            throw new \RuntimeException('Invalid item ID.', 400);
        }

        $record = $this->storageModel
            ->where('id', $itemId)
            ->where('user_id', $userId)
            ->where('deleted_at', null)
            ->first();

        if (!is_array($record)) {
            throw new \RuntimeException('Item not found.', 404);
        }

        $itemType = (string) ($record['item_type'] ?? self::ITEM_TYPE_FILE);
        if ($itemType === self::ITEM_TYPE_FOLDER) {
            $this->markFolderAsDeletedRecursively($userId, $record);
        } else {
            $this->storageModel->update($itemId, [
                'deleted_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return [
            'deleted_item_id' => $itemId,
            'storage' => $this->getStorageSummary($userId, $currentPath),
        ];
    }

    public function moveItem(int $userId, int $itemId, string $targetFolderPath, string $currentPath = '/'): array
    {
        $this->assertValidUserId($userId);
        $this->purgeExpiredDeletedItems($userId);
        if ($itemId <= 0) {
            throw new \RuntimeException('Invalid item ID.', 400);
        }

        $targetPath = $this->sanitizeFolderPath($targetFolderPath);
        $this->ensureFolderPathExists($userId, $targetPath);

        $record = $this->storageModel
            ->where('id', $itemId)
            ->where('user_id', $userId)
            ->where('deleted_at', null)
            ->first();

        if (!is_array($record)) {
            throw new \RuntimeException('Item not found.', 404);
        }

        $itemType = (string) ($record['item_type'] ?? self::ITEM_TYPE_FILE);
        $itemName = trim((string) ($record['original_name'] ?? ''));
        if ($itemName === '') {
            throw new \RuntimeException('Item has an invalid name and cannot be moved.', 400);
        }

        $sourcePath = $this->sanitizeFolderPath((string) ($record['folder_path'] ?? '/'));
        if ($sourcePath === $targetPath) {
            return [
                'moved_item_id' => $itemId,
                'storage' => $this->getStorageSummary($userId, $currentPath),
            ];
        }

        $this->ensureItemNameIsAvailable($userId, $targetPath, $itemName, $itemId);

        if ($itemType === self::ITEM_TYPE_FOLDER) {
            $oldFullPath = $this->buildChildPath($sourcePath, $itemName);
            if ($targetPath === $oldFullPath || str_starts_with($targetPath, $oldFullPath . '/')) {
                throw new \RuntimeException('A folder cannot be moved into itself or one of its subfolders.', 400);
            }

            $newFullPath = $this->buildChildPath($targetPath, $itemName);

            $this->storageModel->update($itemId, [
                'folder_path' => $targetPath,
            ]);

            $descendants = $this->storageModel
                ->where('user_id', $userId)
                ->groupStart()
                    ->where('folder_path', $oldFullPath)
                    ->orLike('folder_path', $oldFullPath . '/', 'after')
                ->groupEnd()
                ->findAll();

            foreach ($descendants as $descendant) {
                $descendantId = (int) ($descendant['id'] ?? 0);
                if ($descendantId <= 0) {
                    continue;
                }

                $descendantPath = $this->sanitizeFolderPath((string) ($descendant['folder_path'] ?? '/'));
                if ($descendantPath === $oldFullPath) {
                    $updatedPath = $newFullPath;
                } elseif (str_starts_with($descendantPath, $oldFullPath . '/')) {
                    $suffix = substr($descendantPath, strlen($oldFullPath));
                    $updatedPath = $newFullPath . $suffix;
                } else {
                    continue;
                }

                $this->storageModel->update($descendantId, [
                    'folder_path' => $updatedPath,
                ]);
            }
        } else {
            $this->storageModel->update($itemId, [
                'folder_path' => $targetPath,
            ]);
        }

        return [
            'moved_item_id' => $itemId,
            'storage' => $this->getStorageSummary($userId, $currentPath),
        ];
    }

    public function copyItem(int $userId, int $itemId, string $targetFolderPath, string $currentPath = '/'): array
    {
        $this->assertValidUserId($userId);
        $this->purgeExpiredDeletedItems($userId);
        if ($itemId <= 0) {
            throw new \RuntimeException('Invalid item ID.', 400);
        }

        $targetPath = $this->sanitizeFolderPath($targetFolderPath);
        $this->ensureFolderPathExists($userId, $targetPath);

        $record = $this->storageModel
            ->where('id', $itemId)
            ->where('user_id', $userId)
            ->where('deleted_at', null)
            ->first();

        if (!is_array($record)) {
            throw new \RuntimeException('Item not found.', 404);
        }

        $itemType = (string) ($record['item_type'] ?? self::ITEM_TYPE_FILE);
        $requiredBytes = 0;
        if ($itemType === self::ITEM_TYPE_FOLDER) {
            $sourceFolderPath = $this->sanitizeFolderPath((string) ($record['folder_path'] ?? '/'));
            $sourceFolderName = $this->sanitizeFolderName((string) ($record['original_name'] ?? ''));
            $sourceFullPath = $this->buildChildPath($sourceFolderPath, $sourceFolderName);
            $requiredBytes = $this->getSubtreeFileSizeBytes($userId, $sourceFullPath);
        } else {
            $requiredBytes = max(0, (int) ($record['size_bytes'] ?? 0));
        }

        $usedBytes = $this->getUsedBytes($userId);
        $remainingBytes = max(0, self::USER_QUOTA_BYTES - $usedBytes);
        if ($requiredBytes > $remainingBytes) {
            throw new \RuntimeException(
                'Storage quota exceeded. Remaining space: ' . $this->formatBytes($remainingBytes) . '.',
                413
            );
        }

        if ($itemType === self::ITEM_TYPE_FOLDER) {
            $copiedItemId = $this->copyFolderTree($userId, $record, $targetPath);
        } else {
            $copiedItemId = $this->copySingleFile($userId, $record, $targetPath);
        }

        return [
            'copied_item_id' => $copiedItemId,
            'storage' => $this->getStorageSummary($userId, $currentPath),
        ];
    }

    public function restoreDeletedItem(int $userId, int $itemId, string $currentPath = '/'): array
    {
        $this->assertValidUserId($userId);
        $this->purgeExpiredDeletedItems($userId);

        if ($itemId <= 0) {
            throw new \RuntimeException('Invalid item ID.', 400);
        }

        $record = $this->storageModel
            ->where('id', $itemId)
            ->where('user_id', $userId)
            ->where('deleted_at IS NOT NULL', null, false)
            ->first();

        if (!is_array($record)) {
            throw new \RuntimeException('Deleted item not found.', 404);
        }

        $itemType = (string) ($record['item_type'] ?? self::ITEM_TYPE_FILE);
        $folderPath = $this->sanitizeFolderPath((string) ($record['folder_path'] ?? '/'));
        $name = trim((string) ($record['original_name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('Invalid item name.', 400);
        }

        $this->ensureFolderPathExists($userId, $folderPath, false);
        $this->ensureItemNameIsAvailable($userId, $folderPath, $name, $itemId);

        if ($itemType === self::ITEM_TYPE_FOLDER) {
            $deletedAt = (string) ($record['deleted_at'] ?? '');
            $fullPath = $this->buildChildPath($folderPath, $name);

            $recordsToRestore = $this->storageModel
                ->where('user_id', $userId)
                ->where('deleted_at', $deletedAt)
                ->groupStart()
                    ->where('id', $itemId)
                    ->orWhere('folder_path', $fullPath)
                    ->orLike('folder_path', $fullPath . '/', 'after')
                ->groupEnd()
                ->findAll();

            $ids = array_values(array_filter(array_map(
                static fn (array $item): int => (int) ($item['id'] ?? 0),
                $recordsToRestore
            ), static fn (int $id): bool => $id > 0));

            if ($ids !== []) {
                $this->storageModel->builder()
                    ->whereIn('id', $ids)
                    ->update(['deleted_at' => null]);
            }
        } else {
            $this->storageModel->update($itemId, ['deleted_at' => null]);
        }

        return [
            'restored_item_id' => $itemId,
            'storage' => $this->getStorageSummary($userId, $currentPath),
            'recycle_bin' => $this->getRecycleBinSummary($userId),
        ];
    }

    public function deleteItemPermanently(int $userId, int $itemId, string $currentPath = '/'): array
    {
        $this->assertValidUserId($userId);
        $this->purgeExpiredDeletedItems($userId);

        if ($itemId <= 0) {
            throw new \RuntimeException('Invalid item ID.', 400);
        }

        $record = $this->storageModel
            ->where('id', $itemId)
            ->where('user_id', $userId)
            ->where('deleted_at IS NOT NULL', null, false)
            ->first();

        if (!is_array($record)) {
            throw new \RuntimeException('Deleted item not found.', 404);
        }

        $itemType = (string) ($record['item_type'] ?? self::ITEM_TYPE_FILE);
        if ($itemType === self::ITEM_TYPE_FOLDER) {
            $this->deleteFolderPermanentlyFromTrash($userId, $record);
        } else {
            $this->deletePhysicalFileRecord($userId, $record);
            $this->storageModel->delete($itemId, true);
        }

        return [
            'deleted_item_id' => $itemId,
            'storage' => $this->getStorageSummary($userId, $currentPath),
            'recycle_bin' => $this->getRecycleBinSummary($userId),
        ];
    }

    public function getDownloadPayload(int $userId, int $fileId): array
    {
        $this->assertValidUserId($userId);
        $this->purgeExpiredDeletedItems($userId);
        if ($fileId <= 0) {
            throw new \RuntimeException('Invalid file ID.', 400);
        }

        $record = $this->storageModel
            ->where('id', $fileId)
            ->where('user_id', $userId)
            ->where('deleted_at', null)
            ->first();

        if (!is_array($record)) {
            throw new \RuntimeException('File not found.', 404);
        }

        if ((string) ($record['item_type'] ?? self::ITEM_TYPE_FILE) !== self::ITEM_TYPE_FILE) {
            throw new \RuntimeException('Folders cannot be downloaded.', 400);
        }

        $absolutePath = $this->getAbsoluteStoredFilePath($userId, (string) $record['stored_name']);
        if (!is_file($absolutePath)) {
            throw new \RuntimeException('Stored file is missing from disk.', 404);
        }

        return [
            'path' => $absolutePath,
            'original_name' => (string) $record['original_name'],
            'mime_type' => (string) ($record['mime_type'] ?? 'application/octet-stream'),
            'size_bytes' => (int) ($record['size_bytes'] ?? 0),
            'id' => (int) $record['id'],
        ];
    }

    private function validateUpload(?UploadedFile $file): void
    {
        if ($file === null) {
            throw new \RuntimeException('No file uploaded.', 400);
        }

        if (!$file->isValid()) {
            if ($file->getError() === UPLOAD_ERR_NO_FILE) {
                throw new \RuntimeException('No file uploaded.', 400);
            }

            throw new \RuntimeException('Upload failed: ' . $file->getErrorString(), 400);
        }

        if ($file->hasMoved()) {
            throw new \RuntimeException('Uploaded file has already been processed.', 400);
        }

        if ((int) $file->getSize() <= 0) {
            throw new \RuntimeException('Uploaded file is empty.', 400);
        }
    }

    private function assertValidUserId(int $userId): void
    {
        if ($userId <= 0) {
            throw new \RuntimeException('Invalid user ID.', 400);
        }
    }

    private function normalizeFileRecord(array $record): array
    {
        return [
            'id' => (int) ($record['id'] ?? 0),
            'item_type' => self::ITEM_TYPE_FILE,
            'folder_path' => (string) ($record['folder_path'] ?? '/'),
            'original_name' => (string) ($record['original_name'] ?? ''),
            'mime_type' => (string) ($record['mime_type'] ?? 'application/octet-stream'),
            'size_bytes' => (int) ($record['size_bytes'] ?? 0),
            'created_at' => (string) ($record['created_at'] ?? ''),
            'updated_at' => (string) ($record['updated_at'] ?? ''),
        ];
    }

    private function normalizeFolderRecord(array $record): array
    {
        $folderPath = (string) ($record['folder_path'] ?? '/');
        $name = (string) ($record['original_name'] ?? '');

        return [
            'id' => (int) ($record['id'] ?? 0),
            'item_type' => self::ITEM_TYPE_FOLDER,
            'folder_path' => $folderPath,
            'full_path' => $this->buildChildPath($folderPath, $name),
            'original_name' => $name,
            'mime_type' => 'inode/directory',
            'size_bytes' => 0,
            'created_at' => (string) ($record['created_at'] ?? ''),
            'updated_at' => (string) ($record['updated_at'] ?? ''),
        ];
    }

    private function normalizeRecycleRecord(array $record): array
    {
        $deletedAt = (string) ($record['deleted_at'] ?? '');
        $expiresAt = $this->calculateExpiresAt($deletedAt);
        $daysRemaining = $this->calculateDaysRemaining($expiresAt);

        $itemType = (string) ($record['item_type'] ?? self::ITEM_TYPE_FILE);
        $folderPath = (string) ($record['folder_path'] ?? '/');
        $name = (string) ($record['original_name'] ?? '');

        return [
            'id' => (int) ($record['id'] ?? 0),
            'item_type' => $itemType === self::ITEM_TYPE_FOLDER ? self::ITEM_TYPE_FOLDER : self::ITEM_TYPE_FILE,
            'folder_path' => $folderPath,
            'full_path' => $itemType === self::ITEM_TYPE_FOLDER
                ? $this->buildChildPath($folderPath, $name)
                : null,
            'original_name' => $name,
            'mime_type' => (string) ($record['mime_type'] ?? 'application/octet-stream'),
            'size_bytes' => (int) ($record['size_bytes'] ?? 0),
            'created_at' => (string) ($record['created_at'] ?? ''),
            'updated_at' => (string) ($record['updated_at'] ?? ''),
            'deleted_at' => $deletedAt,
            'expires_at' => $expiresAt,
            'days_remaining' => $daysRemaining,
        ];
    }

    private function getUsedBytes(int $userId): int
    {
        $row = $this->storageModel
            ->selectSum('size_bytes', 'used_bytes')
            ->where('user_id', $userId)
            ->where('item_type', self::ITEM_TYPE_FILE)
            ->first();

        if (!is_array($row)) {
            return 0;
        }

        return max(0, (int) ($row['used_bytes'] ?? 0));
    }

    private function calculateUsagePercent(int $usedBytes, int $quotaBytes): float
    {
        if ($quotaBytes <= 0) {
            return 0.0;
        }

        $percent = ($usedBytes / $quotaBytes) * 100;
        $rounded = round($percent, 2);

        return min(100.0, max(0.0, $rounded));
    }

    private function getUserDirectoryPath(int $userId): string
    {
        return rtrim(ROOTPATH, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, self::STORAGE_ROOT_RELATIVE)
            . DIRECTORY_SEPARATOR
            . $userId;
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Could not create storage directory.', 500);
        }
    }

    private function getAbsoluteStoredFilePath(int $userId, string $storedName): string
    {
        $safeStoredName = basename(trim($storedName));
        return $this->getUserDirectoryPath($userId) . DIRECTORY_SEPARATOR . $safeStoredName;
    }

    private function copySingleFile(
        int $userId,
        array $sourceRecord,
        string $targetFolderPath,
        ?string $forcedName = null
    ): int {
        $sourceStoredName = (string) ($sourceRecord['stored_name'] ?? '');
        $sourceAbsolutePath = $this->getAbsoluteStoredFilePath($userId, $sourceStoredName);
        if (!is_file($sourceAbsolutePath)) {
            throw new \RuntimeException('Stored source file is missing from disk.', 404);
        }

        $sourceName = $this->sanitizeOriginalName((string) ($sourceRecord['original_name'] ?? 'file'));
        $targetName = $forcedName !== null
            ? $this->sanitizeOriginalName($forcedName)
            : $this->generateAvailableItemName($userId, $targetFolderPath, $sourceName);

        $targetStoredName = $this->generateStoredFileName($targetName);
        $targetAbsolutePath = $this->getAbsoluteStoredFilePath($userId, $targetStoredName);
        $this->ensureDirectoryExists(dirname($targetAbsolutePath));

        if (!@copy($sourceAbsolutePath, $targetAbsolutePath)) {
            throw new \RuntimeException('Failed to duplicate file on disk.', 500);
        }

        $this->storageModel->insert([
            'user_id' => $userId,
            'item_type' => self::ITEM_TYPE_FILE,
            'folder_path' => $targetFolderPath,
            'original_name' => $targetName,
            'stored_name' => $targetStoredName,
            'mime_type' => $this->sanitizeMimeType((string) ($sourceRecord['mime_type'] ?? 'application/octet-stream')),
            'size_bytes' => max(0, (int) ($sourceRecord['size_bytes'] ?? 0)),
            'deleted_at' => null,
        ]);

        return (int) $this->storageModel->getInsertID();
    }

    private function copyFolderTree(int $userId, array $sourceFolderRecord, string $targetFolderPath): int
    {
        $sourceFolderPath = $this->sanitizeFolderPath((string) ($sourceFolderRecord['folder_path'] ?? '/'));
        $sourceFolderName = $this->sanitizeFolderName((string) ($sourceFolderRecord['original_name'] ?? ''));
        $sourceFullPath = $this->buildChildPath($sourceFolderPath, $sourceFolderName);

        $targetRootName = $this->generateAvailableItemName($userId, $targetFolderPath, $sourceFolderName);
        $targetRootFullPath = $this->buildChildPath($targetFolderPath, $targetRootName);

        $this->storageModel->insert([
            'user_id' => $userId,
            'item_type' => self::ITEM_TYPE_FOLDER,
            'folder_path' => $targetFolderPath,
            'original_name' => $targetRootName,
            'stored_name' => '',
            'mime_type' => 'inode/directory',
            'size_bytes' => 0,
            'deleted_at' => null,
        ]);

        $newRootId = (int) $this->storageModel->getInsertID();

        $folderRecords = $this->storageModel
            ->where('user_id', $userId)
            ->where('deleted_at', null)
            ->where('item_type', self::ITEM_TYPE_FOLDER)
            ->groupStart()
                ->where('folder_path', $sourceFullPath)
                ->orLike('folder_path', $sourceFullPath . '/', 'after')
            ->groupEnd()
            ->findAll();

        foreach ($folderRecords as $folderRecord) {
            $sourceParentPath = $this->sanitizeFolderPath((string) ($folderRecord['folder_path'] ?? '/'));
            $targetParentPath = $this->replaceFolderPrefix($sourceParentPath, $sourceFullPath, $targetRootFullPath);
            $childName = $this->sanitizeFolderName((string) ($folderRecord['original_name'] ?? ''));

            $this->storageModel->insert([
                'user_id' => $userId,
                'item_type' => self::ITEM_TYPE_FOLDER,
                'folder_path' => $targetParentPath,
                'original_name' => $childName,
                'stored_name' => '',
                'mime_type' => 'inode/directory',
                'size_bytes' => 0,
                'deleted_at' => null,
            ]);
        }

        $fileRecords = $this->storageModel
            ->where('user_id', $userId)
            ->where('deleted_at', null)
            ->where('item_type', self::ITEM_TYPE_FILE)
            ->groupStart()
                ->where('folder_path', $sourceFullPath)
                ->orLike('folder_path', $sourceFullPath . '/', 'after')
            ->groupEnd()
            ->findAll();

        foreach ($fileRecords as $fileRecord) {
            $sourceFileFolderPath = $this->sanitizeFolderPath((string) ($fileRecord['folder_path'] ?? '/'));
            $targetFileFolderPath = $this->replaceFolderPrefix($sourceFileFolderPath, $sourceFullPath, $targetRootFullPath);
            $this->copySingleFile(
                $userId,
                $fileRecord,
                $targetFileFolderPath,
                (string) ($fileRecord['original_name'] ?? 'file')
            );
        }

        return $newRootId;
    }

    private function replaceFolderPrefix(string $path, string $oldPrefix, string $newPrefix): string
    {
        $safePath = $this->sanitizeFolderPath($path);
        $safeOldPrefix = $this->sanitizeFolderPath($oldPrefix);
        $safeNewPrefix = $this->sanitizeFolderPath($newPrefix);

        if ($safePath === $safeOldPrefix) {
            return $safeNewPrefix;
        }

        if (str_starts_with($safePath, $safeOldPrefix . '/')) {
            return $safeNewPrefix . substr($safePath, strlen($safeOldPrefix));
        }

        return $safePath;
    }

    private function getSubtreeFileSizeBytes(int $userId, string $folderFullPath): int
    {
        $builder = $this->storageModel->builder();
        $row = $builder
            ->selectSum('size_bytes', 'required_bytes')
            ->where('user_id', $userId)
            ->where('deleted_at', null)
            ->where('item_type', self::ITEM_TYPE_FILE)
            ->groupStart()
                ->where('folder_path', $folderFullPath)
                ->orLike('folder_path', $folderFullPath . '/', 'after')
            ->groupEnd()
            ->get()
            ->getRowArray();

        if (!is_array($row)) {
            return 0;
        }

        return max(0, (int) ($row['required_bytes'] ?? 0));
    }

    private function generateAvailableItemName(int $userId, string $folderPath, string $name): string
    {
        $cleanName = trim($name);
        if ($cleanName === '') {
            $cleanName = 'Item';
        }

        if (!$this->itemNameExists($userId, $folderPath, $cleanName)) {
            return $cleanName;
        }

        $extension = (string) pathinfo($cleanName, PATHINFO_EXTENSION);
        $baseName = (string) pathinfo($cleanName, PATHINFO_FILENAME);

        if ($baseName === '') {
            $baseName = $cleanName;
            $extension = '';
        }

        $suffixIndex = 1;
        while ($suffixIndex < 500) {
            $suffixLabel = $suffixIndex === 1 ? ' (copy)' : ' (copy ' . $suffixIndex . ')';
            $candidate = $baseName . $suffixLabel;
            if ($extension !== '') {
                $candidate .= '.' . $extension;
            }

            if (!$this->itemNameExists($userId, $folderPath, $candidate)) {
                return $candidate;
            }

            $suffixIndex++;
        }

        throw new \RuntimeException('Could not generate a unique name for copied item.', 409);
    }

    private function itemNameExists(int $userId, string $folderPath, string $name): bool
    {
        $existing = $this->storageModel
            ->where('user_id', $userId)
            ->where('deleted_at', null)
            ->where('folder_path', $folderPath)
            ->where('original_name', $name)
            ->first();

        return is_array($existing);
    }

    private function sanitizeOriginalName(string $name): string
    {
        $clean = trim(str_replace(["\0", "\r", "\n"], '', $name));
        $clean = basename($clean);

        if ($clean === '') {
            $clean = 'file';
        }

        $clean = preg_replace('/[^\p{L}\p{N}\s._-]+/u', '_', $clean) ?? 'file';
        $clean = preg_replace('/\s+/u', ' ', $clean) ?? $clean;
        $clean = trim($clean);

        if ($clean === '') {
            $clean = 'file';
        }

        if (mb_strlen($clean) > 180) {
            $ext = pathinfo($clean, PATHINFO_EXTENSION);
            $base = pathinfo($clean, PATHINFO_FILENAME);
            $base = mb_substr($base, 0, 160);
            $clean = $ext !== '' ? ($base . '.' . mb_substr($ext, 0, 16)) : $base;
        }

        return $clean;
    }

    private function sanitizeFolderName(string $name): string
    {
        $clean = trim(str_replace(["\0", "\r", "\n", '/', '\\'], '', $name));
        $clean = preg_replace('/[^\p{L}\p{N}\s._-]+/u', '_', $clean) ?? '';
        $clean = preg_replace('/\s+/u', ' ', $clean) ?? $clean;
        $clean = trim($clean, " .");

        if ($clean === '' || $clean === '.' || $clean === '..') {
            throw new \RuntimeException('Invalid folder name.', 400);
        }

        if (mb_strlen($clean) > 120) {
            $clean = mb_substr($clean, 0, 120);
        }

        return $clean;
    }

    private function sanitizeMimeType(string $mimeType): string
    {
        $clean = trim($mimeType);
        if ($clean === '') {
            return 'application/octet-stream';
        }

        if (mb_strlen($clean) > self::MAX_MIME_LENGTH) {
            $clean = mb_substr($clean, 0, self::MAX_MIME_LENGTH);
        }

        return $clean;
    }

    private function sanitizeFolderPath(string $folderPath): string
    {
        $path = trim(str_replace('\\', '/', $folderPath));
        if ($path === '' || $path === '.') {
            return '/';
        }

        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        $segments = array_values(array_filter(explode('/', $path), static fn ($segment) => $segment !== ''));
        $safeSegments = [];

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \RuntimeException('Invalid folder path.', 400);
            }

            $segment = preg_replace('/[^\p{L}\p{N}\s._-]+/u', '_', $segment) ?? '';
            $segment = preg_replace('/\s+/u', ' ', $segment) ?? $segment;
            $segment = trim($segment, ' .');

            if ($segment === '') {
                throw new \RuntimeException('Invalid folder path.', 400);
            }

            if (mb_strlen($segment) > 120) {
                $segment = mb_substr($segment, 0, 120);
            }

            $safeSegments[] = $segment;
        }

        $normalized = '/' . implode('/', $safeSegments);
        if ($normalized === '/') {
            return '/';
        }

        if (mb_strlen($normalized) > self::MAX_FOLDER_PATH_LENGTH) {
            throw new \RuntimeException('Folder path is too long.', 400);
        }

        return $normalized;
    }

    private function getParentPath(string $folderPath): ?string
    {
        if ($folderPath === '/') {
            return null;
        }

        $segments = explode('/', trim($folderPath, '/'));
        array_pop($segments);

        if ($segments === []) {
            return '/';
        }

        return '/' . implode('/', $segments);
    }

    private function buildChildPath(string $folderPath, string $name): string
    {
        $basePath = $this->sanitizeFolderPath($folderPath);
        $safeName = $this->sanitizeFolderName($name);

        if ($basePath === '/') {
            return '/' . $safeName;
        }

        return $basePath . '/' . $safeName;
    }

    private function ensureItemNameIsAvailable(int $userId, string $folderPath, string $name, ?int $excludeItemId = null): void
    {
        $query = $this->storageModel
            ->where('user_id', $userId)
            ->where('deleted_at', null)
            ->where('folder_path', $folderPath)
            ->where('original_name', $name);

        if ($excludeItemId !== null && $excludeItemId > 0) {
            $query->where('id !=', $excludeItemId);
        }

        $existing = $query->first();

        if (is_array($existing)) {
            throw new \RuntimeException('An item with the same name already exists in this folder.', 409);
        }
    }

    private function ensureFolderPathExists(int $userId, string $folderPath, bool $includeDeleted = false): void
    {
        $safePath = $this->sanitizeFolderPath($folderPath);
        if ($safePath === '/') {
            return;
        }

        $segments = explode('/', trim($safePath, '/'));
        $currentParent = '/';

        foreach ($segments as $segment) {
            $query = $this->storageModel
                ->where('user_id', $userId)
                ->where('item_type', self::ITEM_TYPE_FOLDER)
                ->where('folder_path', $currentParent)
                ->where('original_name', $segment);

            if (!$includeDeleted) {
                $query->where('deleted_at', null);
            }

            $folder = $query->first();

            if (!is_array($folder)) {
                throw new \RuntimeException('Target folder was not found.', 404);
            }

            $currentParent = $this->buildChildPath($currentParent, $segment);
        }
    }

    private function deletePhysicalFileRecord(int $userId, array $record): void
    {
        if ((string) ($record['item_type'] ?? self::ITEM_TYPE_FILE) !== self::ITEM_TYPE_FILE) {
            return;
        }

        $absolutePath = $this->getAbsoluteStoredFilePath($userId, (string) ($record['stored_name'] ?? ''));
        if (is_file($absolutePath) && !@unlink($absolutePath)) {
            throw new \RuntimeException('Could not delete file from disk.', 500);
        }
    }

    private function markFolderAsDeletedRecursively(int $userId, array $folderRecord): void
    {
        $folderPath = (string) ($folderRecord['folder_path'] ?? '/');
        $folderName = (string) ($folderRecord['original_name'] ?? '');
        $fullPath = $this->buildChildPath($folderPath, $folderName);
        $deletedAt = date('Y-m-d H:i:s');

        $records = $this->storageModel
            ->where('user_id', $userId)
            ->where('deleted_at', null)
            ->groupStart()
                ->where('id', (int) ($folderRecord['id'] ?? 0))
                ->orWhere('folder_path', $fullPath)
                ->orLike('folder_path', $fullPath . '/', 'after')
            ->groupEnd()
            ->findAll();

        if ($records === []) {
            return;
        }

        $ids = [];
        foreach ($records as $record) {
            $id = (int) ($record['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            return;
        }

        $this->storageModel->builder()
            ->whereIn('id', $ids)
            ->update(['deleted_at' => $deletedAt]);
    }

    private function deleteFolderPermanentlyFromTrash(int $userId, array $folderRecord): void
    {
        $folderPath = (string) ($folderRecord['folder_path'] ?? '/');
        $folderName = (string) ($folderRecord['original_name'] ?? '');
        $fullPath = $this->buildChildPath($folderPath, $folderName);

        $records = $this->storageModel
            ->where('user_id', $userId)
            ->where('deleted_at IS NOT NULL', null, false)
            ->groupStart()
                ->where('id', (int) ($folderRecord['id'] ?? 0))
                ->orWhere('folder_path', $fullPath)
                ->orLike('folder_path', $fullPath . '/', 'after')
            ->groupEnd()
            ->findAll();

        if ($records === []) {
            return;
        }

        $ids = [];
        foreach ($records as $record) {
            $id = (int) ($record['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }

            if ((string) ($record['item_type'] ?? self::ITEM_TYPE_FILE) === self::ITEM_TYPE_FILE) {
                $this->deletePhysicalFileRecord($userId, $record);
            }
        }

        if ($ids === []) {
            return;
        }

        $this->storageModel->builder()->whereIn('id', $ids)->delete();
    }

    private function purgeExpiredDeletedItems(int $userId): void
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . self::TRASH_RETENTION_DAYS . ' days'));

        $expiredRecords = $this->storageModel
            ->where('user_id', $userId)
            ->where('deleted_at IS NOT NULL', null, false)
            ->where('deleted_at <=', $cutoff)
            ->findAll();

        if ($expiredRecords === []) {
            return;
        }

        $ids = [];
        foreach ($expiredRecords as $record) {
            $id = (int) ($record['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }

            if ((string) ($record['item_type'] ?? self::ITEM_TYPE_FILE) === self::ITEM_TYPE_FILE) {
                $this->deletePhysicalFileRecord($userId, $record);
            }
        }

        if ($ids === []) {
            return;
        }

        $this->storageModel->builder()->whereIn('id', $ids)->delete();
    }

    private function calculateExpiresAt(string $deletedAt): string
    {
        $timestamp = strtotime($deletedAt);
        if ($timestamp === false) {
            return '';
        }

        return date('Y-m-d H:i:s', strtotime('+' . self::TRASH_RETENTION_DAYS . ' days', $timestamp));
    }

    private function calculateDaysRemaining(string $expiresAt): int
    {
        $expiry = strtotime($expiresAt);
        if ($expiry === false) {
            return 0;
        }

        $remainingSeconds = $expiry - time();
        if ($remainingSeconds <= 0) {
            return 0;
        }

        return (int) ceil($remainingSeconds / 86400);
    }

    private function generateStoredFileName(string $originalName): string
    {
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $safeExtension = preg_replace('/[^a-z0-9]+/i', '', $extension) ?? '';
        if ($safeExtension !== '') {
            $safeExtension = substr($safeExtension, 0, 12);
        }

        try {
            $random = bin2hex(random_bytes(20));
        } catch (\Throwable) {
            $random = bin2hex(pack('d', microtime(true))) . mt_rand(1000, 9999);
        }

        return $safeExtension !== '' ? ($random . '.' . $safeExtension) : $random;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $size = $bytes / 1024;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return number_format($size, 2) . ' ' . $units[$unitIndex];
    }
}
