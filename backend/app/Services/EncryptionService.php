<?php

namespace App\Services;

use CodeIgniter\Config\Services;
use RuntimeException;

class EncryptionService
{
    private string $privateKeyPath;
    private string $publicKeyPath;

    // We use an 8MB chunk size for reading the unencrypted data
    private const CHUNK_SIZE = 8192 * 1024;

    public function __construct()
    {
        $this->privateKeyPath = WRITEPATH . 'keys/private.pem';
        $this->publicKeyPath = WRITEPATH . 'keys/public.pem';
    }

    public function keysExist(): bool
    {
        return file_exists($this->privateKeyPath) && file_exists($this->publicKeyPath);
    }

    /**
     * Encrypts a source file to a destination file using Envelope Encryption.
     * Uses Sodium's streaming (XChaCha20-Poly1305) for strict memory efficiency
     * and RSA to securely lock the symmetric key.
     */
    public function encryptFile(string $sourcePath, string $destPath): void
    {
        if (!$this->keysExist()) {
            throw new RuntimeException("Encryption keys not found. Run 'php spark keys:generate' first.");
        }

        $publicKey = openssl_pkey_get_public(file_get_contents($this->publicKeyPath));
        if (!$publicKey) {
            throw new RuntimeException("Invalid public key.");
        }

        if (!file_exists($sourcePath)) {
            throw new RuntimeException("Source file does not exist: {$sourcePath}");
        }

        // Generate strong symmetric key for Sodium streaming
        $symKey = sodium_crypto_secretstream_xchacha20poly1305_keygen();

        // Encrypt the symmetric key with the RSA Public Key
        $encryptedSymKey = '';
        if (!openssl_public_encrypt($symKey, $encryptedSymKey, $publicKey)) {
            throw new RuntimeException("Failed to encrypt symmetric key: " . openssl_error_string());
        }

        $sourceFile = fopen($sourcePath, 'rb');
        $destFile = fopen($destPath, 'wb');
        if (!$sourceFile || !$destFile) {
            if ($sourceFile) fclose($sourceFile);
            if ($destFile) fclose($destFile);
            throw new RuntimeException("Failed to open file handles for encryption.");
        }

        try {
            // Write Header: [2 bytes: Encrypted Key Length] [Encrypted Symmetric Key]
            $keyLength = strlen($encryptedSymKey);
            fwrite($destFile, pack('v', $keyLength)); // 16-bit little endian
            fwrite($destFile, $encryptedSymKey);

            // Initialize Sodium Streaming Crypto
            [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($symKey);
            // Write sodium header (24 bytes)
            fwrite($destFile, $header);

            // Stream and encrypt file in chunks
            while (!feof($sourceFile)) {
                $chunk = fread($sourceFile, self::CHUNK_SIZE);
                if ($chunk === false) break;
                if ($chunk === '') continue; // skip empty reads
                
                $isFinal = feof($sourceFile);
                $tag = $isFinal ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : 0;
                
                $encryptedChunk = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag);
                fwrite($destFile, $encryptedChunk);
            }
        } finally {
            fclose($sourceFile);
            fclose($destFile);
            
            // Explicitly destroy sensitive key variables
            sodium_memzero($symKey);
        }
    }

    /**
     * Streams an encrypted file directly to PHP output (HTTP Response body), decrypting inline.
     * Prevents loading massive files entirely into memory.
     */
    public function streamDecryptToOutput(string $encryptedFilePath): void
    {
        if (!$this->keysExist()) {
            throw new RuntimeException("Encryption keys not found.");
        }

        $privateKey = openssl_pkey_get_private(file_get_contents($this->privateKeyPath));
        if (!$privateKey) {
            throw new RuntimeException("Invalid private key.");
        }

        if (!file_exists($encryptedFilePath)) {
            throw new RuntimeException("Encrypted file not found.");
        }

        $file = fopen($encryptedFilePath, 'rb');
        if (!$file) {
            throw new RuntimeException("Failed to open encrypted file.");
        }

        try {
            // Read Encrypted Key Length (2 bytes)
            $lenData = fread($file, 2);
            if (strlen($lenData) !== 2) throw new RuntimeException("Invalid file format.");
            $keyLength = unpack('v', $lenData)[1];

            // Read Encrypted Symmetric Key
            $encryptedSymKey = fread($file, $keyLength);
            
            // Decrypt Symmetric Key using RSA Private Key
            $symKey = '';
            if (!openssl_private_decrypt($encryptedSymKey, $symKey, $privateKey)) {
                throw new RuntimeException("Decryption failed. Unrecognized key or corrupted file.");
            }

            // Read Sodium Header (24 bytes)
            $header = fread($file, 24);
            
            // Initialize Sodium Streaming Crypto for Pull (Decryption)
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $symKey);
            
            // Erase the decrypted symmetric key from memory since state is built
            sodium_memzero($symKey);

            // Output Buffering Flush explicitly (optional depending on CI4 response structure, 
            // but setting up direct echo streaming here is very fast and efficient)
            
            // Sodium chunk size = original byte length + 17 bytes MAC tag
            $readSize = self::CHUNK_SIZE + 17;
            
            while (!feof($file)) {
                $encryptedChunk = fread($file, $readSize);
                if ($encryptedChunk === false || $encryptedChunk === '') break;
                
                [$decryptedChunk, $tag] = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $encryptedChunk);
                
                if ($decryptedChunk === false) {
                    throw new RuntimeException("Corrupted chunk. Decryption failed.");
                }
                
                echo $decryptedChunk;
                // Optional: flush to force chunks out to client
                if (ob_get_level() > 0) ob_flush();
                flush();
                
                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    break;
                }
            }
        } finally {
            fclose($file);
        }
    }
}
