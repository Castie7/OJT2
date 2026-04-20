<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\ResearchModel;
use App\Models\UserStorageFileModel;
use App\Services\EncryptionService;

class MigrateEncryption extends BaseCommand
{
    protected $group       = 'Encryption';
    protected $name        = 'encryption:migrate';
    protected $description = 'Encrypts all existing plain-text files in public/uploads and writable/uploads/storage.';

    public function run(array $params)
    {
        CLI::write("Starting Encryption Migration...", 'yellow');
        $encService = new EncryptionService();
        $this->migrateResearchFiles($encService);
        $this->migrateStorageFiles($encService);
        CLI::write("Migration complete!", 'green');
    }

    private function migrateResearchFiles(EncryptionService $enc)
    {
        CLI::write("Migrating Research Files (public/uploads -> writable/uploads/research)...", 'yellow');
        
        $model = new ResearchModel();
        $items = $model->where('file_path !=', null)->where('file_path !=', '')->findAll();
        
        $sourceDir = ROOTPATH . 'public/uploads';
        $targetDir = WRITEPATH . 'uploads/research';
        
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        
        $success = 0; $skipped = 0; $failed = 0;
        foreach ($items as $item) {
            $fileName = basename($item->file_path);
            $sourcePath = $sourceDir . DIRECTORY_SEPARATOR . $fileName;
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $fileName;
            
            if (!is_file($sourcePath)) {
                $skipped++;
                continue;
            }
            
            try {
                $enc->encryptFile($sourcePath, $targetPath);
                unlink($sourcePath); // Delete plain text file
                $success++;
                CLI::write("   [OK] Encrypted & moved: $fileName", 'green');
            } catch (\Throwable $e) {
                $failed++;
                CLI::error("   [FAILED] $fileName: " . $e->getMessage());
            }
        }
        
        CLI::write("Research Migration Done. Success: $success, Skipped: $skipped, Failed: $failed", 'cyan');
    }

    private function migrateStorageFiles(EncryptionService $enc)
    {
        CLI::write("Migrating Workspace Storage Files (encrypt in-place at writable/uploads)...", 'yellow');
        
        $model = new UserStorageFileModel();
        $items = $model->where('item_type', 'file')->findAll();
        
        $baseDir = WRITEPATH . 'uploads/storage';
        $success = 0; $skipped = 0; $failed = 0;
        
        foreach ($items as $item) {
            $userId = preg_replace('/[^0-9]/', '', (string)$item['user_id']); // Ensure clean
            $fileName = basename($item['stored_name']);
            $sourcePath = $baseDir . DIRECTORY_SEPARATOR . $userId . DIRECTORY_SEPARATOR . $fileName;
            
            if (!is_file($sourcePath)) {
                $skipped++;
                continue;
            }
            
            // Check if already completely encrypted.
            // Fast check: length modulo pattern or just re-encrypting? We can't easily tell.
            // Oh wait! If we run this twice, it'll encrypt an encrypted file!
            // Let's do a simple check: read first 256 bytes. If it contains PDF header, it's NOT encrypted.
            // Wait, storage files can be ANY type (jpg, pdf, zip). We can't rely on PDF header.
            // Is there a way to check if it's already Envelope Encrypted by our system?
            // Our encryption prepends a base64 string of RSA payload then "::". 
            // The RSA payload base64 only contains A-Za-z0-9+/=
            
            $header = file_get_contents($sourcePath, false, null, 0, 800);
            if (strpos($header, '::') !== false) {
                $parts = explode('::', $header, 2);
                if (strlen($parts[0]) > 200 && base64_decode($parts[0], true) !== false) {
                    $skipped++;
                    CLI::write("   [SKIP] Appears to be already encrypted: $fileName", 'dark_gray');
                    continue;
                }
            }
            
            $tempPath = $sourcePath . '.tmp';
            
            try {
                $enc->encryptFile($sourcePath, $tempPath);
                unlink($sourcePath); // Delete plain text file
                rename($tempPath, $sourcePath); // Replace with encrypted file
                $success++;
                CLI::write("   [OK] Encrypted: $fileName", 'green');
            } catch (\Throwable $e) {
                if (is_file($tempPath)) unlink($tempPath);
                $failed++;
                CLI::error("   [FAILED] $fileName: " . $e->getMessage());
            }
        }
        
        CLI::write("Storage Migration Done. Success: $success, Skipped: $skipped, Failed: $failed", 'cyan');
    }
}
