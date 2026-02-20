<?php

namespace App\Services;

use App\Models\ResearchModel;
use App\Models\ResearchDetailsModel;
use App\Models\ResearchCommentModel;
use App\Models\NotificationModel;
use App\Models\ResearchIndexJobModel;
use App\Models\UserModel;

class ResearchService extends BaseService
{
    private const DEFAULT_ACCESS_LEVEL = 'public';
    private const MAX_INDEX_TEXT_CHARS = 120000;
    private const MAX_FALLBACK_PDF_BYTES = 8388608; // 8 MB
    private const OCR_MIN_TRIGGER_TEXT_CHARS = 120;

    protected $researchModel;
    protected $detailsModel;
    protected $commentModel;
    protected $notifModel;
    protected $indexJobModel;
    protected $userModel;
    private ?bool $hasAccessLevelColumn = null;
    private ?bool $hasSearchTextColumn = null;
    private ?bool $hasPdftoppmBinary = null;
    private ?bool $hasTesseractBinary = null;
    private ?bool $hasResearchesFullTextIndex = null;
    private ?bool $hasDetailsFullTextIndex = null;

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
        $this->indexJobModel = new ResearchIndexJobModel();
        $this->userModel = new UserModel();
    }

    private function hasAccessLevelColumn(): bool
    {
        if ($this->hasAccessLevelColumn !== null) {
            return $this->hasAccessLevelColumn;
        }

        $this->hasAccessLevelColumn = $this->db->fieldExists('access_level', 'researches');

        return $this->hasAccessLevelColumn;
    }

    private function hasSearchTextColumn(): bool
    {
        if ($this->hasSearchTextColumn !== null) {
            return $this->hasSearchTextColumn;
        }

        $this->hasSearchTextColumn = $this->db->fieldExists('search_text', 'research_details');

        return $this->hasSearchTextColumn;
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $row = $this->db->table('INFORMATION_SCHEMA.STATISTICS')
            ->select('INDEX_NAME')
            ->where('TABLE_SCHEMA', $this->db->getDatabase())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $indexName)
            ->get()
            ->getRowArray();

        return !empty($row);
    }

    private function hasResearchesFullTextIndex(): bool
    {
        if ($this->hasResearchesFullTextIndex !== null) {
            return $this->hasResearchesFullTextIndex;
        }

        $this->hasResearchesFullTextIndex = $this->hasIndex('researches', 'ft_researches_title_author');

        return $this->hasResearchesFullTextIndex;
    }

    private function hasDetailsFullTextIndex(): bool
    {
        if ($this->hasDetailsFullTextIndex !== null) {
            return $this->hasDetailsFullTextIndex;
        }

        $this->hasDetailsFullTextIndex = $this->hasIndex('research_details', 'ft_research_details_search');

        return $this->hasDetailsFullTextIndex;
    }

    private function hasFullTextSupport(): bool
    {
        return $this->hasResearchesFullTextIndex() && $this->hasDetailsFullTextIndex();
    }

    private function normalizeAccessLevel(?string $accessLevel): string
    {
        return strtolower(trim((string) $accessLevel)) === 'private' ? 'private' : self::DEFAULT_ACCESS_LEVEL;
    }

    private function commandExists(string $command): bool
    {
        if (!function_exists('shell_exec')) {
            return false;
        }

        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $checkCommand = $isWindows
            ? "where {$command} 2>NUL"
            : "command -v {$command} 2>/dev/null";

        $output = @shell_exec($checkCommand);

        return is_string($output) && trim($output) !== '';
    }

    private function hasPdftoppmBinary(): bool
    {
        if ($this->hasPdftoppmBinary !== null) {
            return $this->hasPdftoppmBinary;
        }

        $this->hasPdftoppmBinary = $this->commandExists('pdftoppm');

        return $this->hasPdftoppmBinary;
    }

    private function hasTesseractBinary(): bool
    {
        if ($this->hasTesseractBinary !== null) {
            return $this->hasTesseractBinary;
        }

        $this->hasTesseractBinary = $this->commandExists('tesseract');

        return $this->hasTesseractBinary;
    }

    private function getOcrMaxPages(): int
    {
        $value = (int) env('search.ocr.maxPages', 3);
        if ($value < 1) {
            return 1;
        }
        if ($value > 12) {
            return 12;
        }
        return $value;
    }

    private function getOcrDpi(): int
    {
        $value = (int) env('search.ocr.dpi', 180);
        if ($value < 120) {
            return 120;
        }
        if ($value > 300) {
            return 300;
        }
        return $value;
    }

    private function getOcrLanguage(): string
    {
        $language = trim((string) env('search.ocr.lang', 'eng'));
        if ($language === '') {
            return 'eng';
        }

        if (!preg_match('/^[a-zA-Z+_]+$/', $language)) {
            return 'eng';
        }

        return strtolower($language);
    }

    private function tokenizeSearchQuery(string $query): array
    {
        $normalized = mb_strtolower(trim($query));
        if ($normalized === '') {
            return [];
        }

        $tokens = preg_split('/\s+/', $normalized) ?: [];
        $tokens = array_values(array_unique(array_filter($tokens, static fn ($token) => mb_strlen((string) $token) >= 3)));

        return array_slice($tokens, 0, 8);
    }

    private function applySpellingCorrections(string $query): string
    {
        $typoMap = [
            'sweeet' => 'sweet',
            'potatto' => 'potato',
            'pototo' => 'potato',
            'camotte' => 'camote',
            'cassavaa' => 'cassava',
            'yucca' => 'yuca',
            'blite' => 'blight',
            'managment' => 'management',
            'nutriton' => 'nutrition',
            'reserch' => 'research',
            'journel' => 'journal',
            'pubisher' => 'publisher',
            'isnb' => 'isbn',
        ];

        return preg_replace_callback('/\b[[:alnum:]\-]{3,}\b/u', static function ($matches) use ($typoMap) {
            $word = strtolower((string) $matches[0]);
            return $typoMap[$word] ?? $matches[0];
        }, $query) ?? $query;
    }

    private function expandSearchQuery(string $query): string
    {
        $corrected = $this->applySpellingCorrections($query);
        $normalized = mb_strtolower($corrected);

        $synonymMap = [
            'sweet potato' => ['camote', 'ipomoea batatas'],
            'camote' => ['sweet potato'],
            'cassava' => ['yuca', 'manioc', 'manihot esculenta'],
            'yuca' => ['cassava'],
            'disease' => ['blight', 'pathogen', 'infection'],
            'nutrition' => ['nutritional', 'nutrient'],
            'journal' => ['article', 'paper'],
            'thesis' => ['dissertation'],
            'isbn' => ['issn'],
        ];

        $extraTerms = [];
        foreach ($synonymMap as $source => $targets) {
            if (str_contains($normalized, $source)) {
                foreach ($targets as $target) {
                    $extraTerms[] = $target;
                }
            }
        }

        if (empty($extraTerms)) {
            return $corrected;
        }

        $extraTerms = array_values(array_unique($extraTerms));
        return trim($corrected . ' ' . implode(' ', $extraTerms));
    }

    private function hasPromptInjectionPattern(string $value): bool
    {
        $patterns = [
            '/ignore\s+previous\s+instructions/i',
            '/system\s+prompt/i',
            '/jailbreak/i',
            '/developer\s+mode/i',
            '/do\s+not\s+follow/i',
            '/override\s+instructions/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    private function getDetailsFullTextColumns(): string
    {
        $columns = [
            'research_details.subjects',
            'research_details.physical_description',
            'research_details.publisher',
            'research_details.knowledge_type',
            'research_details.isbn_issn',
        ];

        if ($this->hasSearchTextColumn()) {
            $columns[] = 'research_details.search_text';
        }

        return implode(', ', $columns);
    }

    private function buildSmartSearchScore(string $query): string
    {
        $normalized = mb_strtolower(trim($query));
        $escapedExact = $this->db->escape($normalized);
        $escapedLike = $this->db->escape('%' . $this->db->escapeLikeString($normalized) . '%');

        $parts = [
            "CASE WHEN LOWER(researches.title) = {$escapedExact} THEN 140 ELSE 0 END",
            "CASE WHEN LOWER(researches.title) LIKE {$escapedLike} THEN 80 ELSE 0 END",
            "CASE WHEN LOWER(researches.author) LIKE {$escapedLike} THEN 50 ELSE 0 END",
            "CASE WHEN LOWER(research_details.subjects) LIKE {$escapedLike} THEN 40 ELSE 0 END",
            "CASE WHEN LOWER(research_details.knowledge_type) LIKE {$escapedLike} THEN 25 ELSE 0 END",
            "CASE WHEN LOWER(research_details.publisher) LIKE {$escapedLike} THEN 20 ELSE 0 END",
            "CASE WHEN LOWER(research_details.physical_description) LIKE {$escapedLike} THEN 18 ELSE 0 END",
            "CASE WHEN LOWER(research_details.isbn_issn) LIKE {$escapedLike} THEN 15 ELSE 0 END",
        ];

        if ($this->hasFullTextSupport()) {
            $detailsColumns = $this->getDetailsFullTextColumns();
            $parts[] = "COALESCE(MATCH(researches.title, researches.author) AGAINST ({$escapedExact} IN NATURAL LANGUAGE MODE), 0) * 45";
            $parts[] = "COALESCE(MATCH({$detailsColumns}) AGAINST ({$escapedExact} IN NATURAL LANGUAGE MODE), 0) * 30";
        }

        if ($this->hasSearchTextColumn()) {
            $parts[] = "CASE WHEN LOWER(research_details.search_text) LIKE {$escapedLike} THEN 22 ELSE 0 END";
        }

        foreach ($this->tokenizeSearchQuery($query) as $token) {
            $tokenLike = $this->db->escape('%' . $this->db->escapeLikeString($token) . '%');
            $parts[] = "CASE WHEN LOWER(researches.title) LIKE {$tokenLike} THEN 16 ELSE 0 END";
            $parts[] = "CASE WHEN LOWER(researches.author) LIKE {$tokenLike} THEN 12 ELSE 0 END";
            $parts[] = "CASE WHEN LOWER(research_details.subjects) LIKE {$tokenLike} THEN 9 ELSE 0 END";
            $parts[] = "CASE WHEN LOWER(research_details.physical_description) LIKE {$tokenLike} THEN 7 ELSE 0 END";
            if ($this->hasSearchTextColumn()) {
                $parts[] = "CASE WHEN LOWER(research_details.search_text) LIKE {$tokenLike} THEN 6 ELSE 0 END";
            }
        }

        return '(' . implode(' + ', $parts) . ')';
    }

    private function applySmartSearchFilter($builder, string $query): void
    {
        $normalized = trim($query);
        $tokens = $this->tokenizeSearchQuery($query);

        $builder->groupStart()
            ->like('researches.title', $normalized)
            ->orLike('researches.author', $normalized)
            ->orLike('research_details.subjects', $normalized)
            ->orLike('research_details.knowledge_type', $normalized)
            ->orLike('research_details.publisher', $normalized)
            ->orLike('research_details.physical_description', $normalized)
            ->orLike('research_details.isbn_issn', $normalized);

        if ($this->hasFullTextSupport()) {
            $detailsColumns = $this->getDetailsFullTextColumns();
            $escaped = $this->db->escape($normalized);
            $builder->orWhere("MATCH(researches.title, researches.author) AGAINST ({$escaped} IN NATURAL LANGUAGE MODE) > 0", null, false);
            $builder->orWhere("MATCH({$detailsColumns}) AGAINST ({$escaped} IN NATURAL LANGUAGE MODE) > 0", null, false);
        }

        if ($this->hasSearchTextColumn()) {
            $builder->orLike('research_details.search_text', $normalized);
        }

        foreach ($tokens as $token) {
            $builder->orLike('researches.title', $token)
                ->orLike('researches.author', $token)
                ->orLike('research_details.subjects', $token)
                ->orLike('research_details.physical_description', $token);

            if ($this->hasSearchTextColumn()) {
                $builder->orLike('research_details.search_text', $token);
            }
        }

        $builder->groupEnd();
    }

    private function extractQuotedPhrases(string $query): array
    {
        if (!preg_match_all('/"([^"]+)"/u', $query, $matches)) {
            return [];
        }

        $phrases = array_map(static fn ($value) => trim((string) $value), $matches[1] ?? []);
        $phrases = array_values(array_unique(array_filter($phrases, static fn ($value) => mb_strlen((string) $value) >= 3)));

        return array_slice($phrases, 0, 4);
    }

    private function applySpecificSearchFilter($builder, string $query): void
    {
        $normalized = trim($query);
        $tokens = $this->tokenizeSearchQuery($normalized);
        $phrases = $this->extractQuotedPhrases($normalized);

        if (empty($tokens) && empty($phrases)) {
            $this->applySmartSearchFilter($builder, $normalized);
            return;
        }

        if ($this->hasFullTextSupport()) {
            $terms = [];
            foreach ($phrases as $phrase) {
                $cleanPhrase = trim($phrase);
                if ($cleanPhrase !== '') {
                    $terms[] = '+"' . str_replace('"', '', $cleanPhrase) . '"';
                }
            }
            foreach ($tokens as $token) {
                $cleanToken = trim($token);
                if ($cleanToken !== '') {
                    $terms[] = '+' . $cleanToken . '*';
                }
            }

            if (!empty($terms)) {
                $booleanQuery = implode(' ', $terms);
                $escapedBoolean = $this->db->escape($booleanQuery);
                $detailsColumns = $this->getDetailsFullTextColumns();
                $builder->where(
                    "(MATCH(researches.title, researches.author) AGAINST ({$escapedBoolean} IN BOOLEAN MODE) > 0 OR MATCH({$detailsColumns}) AGAINST ({$escapedBoolean} IN BOOLEAN MODE) > 0)",
                    null,
                    false
                );
                return;
            }
        }

        foreach ($phrases as $phrase) {
            $builder->groupStart()
                ->like('researches.title', $phrase)
                ->orLike('researches.author', $phrase)
                ->orLike('research_details.subjects', $phrase)
                ->orLike('research_details.publisher', $phrase)
                ->orLike('research_details.physical_description', $phrase)
                ->orLike('research_details.isbn_issn', $phrase);

            if ($this->hasSearchTextColumn()) {
                $builder->orLike('research_details.search_text', $phrase);
            }

            $builder->groupEnd();
        }

        foreach ($tokens as $token) {
            $builder->groupStart()
                ->like('researches.title', $token)
                ->orLike('researches.author', $token)
                ->orLike('research_details.subjects', $token)
                ->orLike('research_details.knowledge_type', $token)
                ->orLike('research_details.publisher', $token)
                ->orLike('research_details.physical_description', $token)
                ->orLike('research_details.isbn_issn', $token);

            if ($this->hasSearchTextColumn()) {
                $builder->orLike('research_details.search_text', $token);
            }

            $builder->groupEnd();
        }
    }

    private function normalizeIndexText(string $text): string
    {
        if ($this->hasPromptInjectionPattern($text)) {
            $text = preg_replace('/ignore\s+previous\s+instructions/iu', ' ', $text) ?? $text;
            $text = preg_replace('/system\s+prompt/iu', ' ', $text) ?? $text;
            $text = preg_replace('/developer\s+mode/iu', ' ', $text) ?? $text;
            $text = preg_replace('/override\s+instructions/iu', ' ', $text) ?? $text;
            $text = preg_replace('/jailbreak/iu', ' ', $text) ?? $text;
        }

        $text = preg_replace('/[[:cntrl:]]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        if (mb_strlen($text) > self::MAX_INDEX_TEXT_CHARS) {
            $text = mb_substr($text, 0, self::MAX_INDEX_TEXT_CHARS);
        }

        return $text;
    }

    private function decodePdfEscapes(string $value): string
    {
        $value = preg_replace_callback('/\\\\([0-7]{1,3})/', static function ($matches) {
            $code = octdec($matches[1]);
            if ($code < 0 || $code > 255) {
                return ' ';
            }
            return chr($code);
        }, $value) ?? $value;

        $replacements = [
            '\\n' => "\n",
            '\\r' => "\r",
            '\\t' => "\t",
            '\\b' => "\b",
            '\\f' => "\f",
            '\\(' => '(',
            '\\)' => ')',
            '\\\\' => '\\',
        ];

        return strtr($value, $replacements);
    }

    private function tryExtractWithPdftotext(string $absolutePath): ?string
    {
        if (!function_exists('shell_exec')) {
            return null;
        }

        $escapedPath = escapeshellarg($absolutePath);
        $nullDevice = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'NUL' : '/dev/null';
        $command = "pdftotext -q -enc UTF-8 -nopgbrk {$escapedPath} - 2>{$nullDevice}";

        $output = @shell_exec($command);
        if (!is_string($output) || trim($output) === '') {
            return null;
        }

        return $output;
    }

    private function tryExtractWithOcr(string $absolutePath): ?string
    {
        if (!function_exists('shell_exec')) {
            return null;
        }

        if (!$this->hasPdftoppmBinary() || !$this->hasTesseractBinary()) {
            return null;
        }

        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $nullDevice = $isWindows ? 'NUL' : '/dev/null';

        try {
            $tmpBase = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . 'ocr_' . bin2hex(random_bytes(8));
        } catch (\Throwable $e) {
            $tmpBase = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . 'ocr_' . uniqid('', true);
        }

        $maxPages = $this->getOcrMaxPages();
        $dpi = $this->getOcrDpi();
        $language = $this->getOcrLanguage();

        $escapedPdf = escapeshellarg($absolutePath);
        $escapedBase = escapeshellarg($tmpBase);
        $pdftoppmCmd = "pdftoppm -f 1 -l {$maxPages} -r {$dpi} -png {$escapedPdf} {$escapedBase} 2>{$nullDevice}";

        @shell_exec($pdftoppmCmd);

        $images = glob($tmpBase . '-*.png');
        if (!is_array($images) || empty($images)) {
            return null;
        }

        natsort($images);
        $images = array_values($images);

        $texts = [];
        foreach ($images as $index => $image) {
            if ($index >= $maxPages) {
                break;
            }

            if (!is_file($image)) {
                continue;
            }

            $escapedImage = escapeshellarg($image);
            $escapedLang = escapeshellarg($language);
            $ocrCmd = "tesseract {$escapedImage} stdout -l {$escapedLang} --psm 6 2>{$nullDevice}";
            $ocrText = @shell_exec($ocrCmd);

            if (is_string($ocrText) && trim($ocrText) !== '') {
                $texts[] = $ocrText;
            }
        }

        foreach (glob($tmpBase . '-*.png') ?: [] as $image) {
            @unlink($image);
        }

        if (empty($texts)) {
            return null;
        }

        return implode("\n", $texts);
    }

    private function extractTextFromPdfFallback(string $absolutePath): string
    {
        $size = @filesize($absolutePath);
        if ($size === false || $size <= 0) {
            return '';
        }

        if ($size > (self::MAX_FALLBACK_PDF_BYTES * 3)) {
            log_message('warning', '[PDF Search Index] Fallback skipped for large file: ' . basename($absolutePath));
            return '';
        }

        $handle = @fopen($absolutePath, 'rb');
        if (!$handle) {
            return '';
        }

        $buffer = '';
        $remaining = self::MAX_FALLBACK_PDF_BYTES;

        while (!feof($handle) && $remaining > 0) {
            $chunk = fread($handle, min(65536, $remaining));
            if ($chunk === false) {
                break;
            }
            $buffer .= $chunk;
            $remaining -= strlen($chunk);
        }
        fclose($handle);

        if ($buffer === '') {
            return '';
        }

        $texts = [];

        if (preg_match_all('/stream[\r\n]+(.*?)endstream/s', $buffer, $streamMatches)) {
            foreach ($streamMatches[1] as $stream) {
                $decoded = $stream;
                $inflated = @gzuncompress($stream);
                if (is_string($inflated) && $inflated !== '') {
                    $decoded = $inflated;
                } else {
                    $inflated = @gzdecode($stream);
                    if (is_string($inflated) && $inflated !== '') {
                        $decoded = $inflated;
                    }
                }

                if (preg_match_all('/\((.*?)\)\s*T[Jj]/s', $decoded, $textMatches)) {
                    foreach ($textMatches[1] as $candidate) {
                        $decodedText = $this->decodePdfEscapes((string) $candidate);
                        if (trim($decodedText) !== '') {
                            $texts[] = $decodedText;
                        }
                    }
                }
            }
        }

        if (empty($texts) && preg_match_all('/[A-Za-z][A-Za-z0-9,\.\-\(\)\/ ]{5,}/', $buffer, $plainMatches)) {
            $texts = array_slice($plainMatches[0], 0, 500);
        }

        return implode(' ', $texts);
    }

    private function extractPdfText(string $absolutePath): string
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return '';
        }

        $text = $this->tryExtractWithPdftotext($absolutePath);
        if ($text === null || trim($text) === '') {
            $text = $this->extractTextFromPdfFallback($absolutePath);
        }
        $normalized = $this->normalizeIndexText((string) $text);

        if (mb_strlen($normalized) < self::OCR_MIN_TRIGGER_TEXT_CHARS) {
            $ocrText = $this->tryExtractWithOcr($absolutePath);
            if (is_string($ocrText) && trim($ocrText) !== '') {
                $normalized = $this->normalizeIndexText(trim($normalized . ' ' . $ocrText));
            }
        }

        return $normalized;
    }

    private function buildSearchIndexText(array $research, array $details, ?string $pdfPath = null): string
    {
        $pdfText = '';
        if ($pdfPath !== null && $pdfPath !== '') {
            $absolutePath = ROOTPATH . 'public/uploads/' . basename($pdfPath);
            $pdfText = $this->extractPdfText($absolutePath);
        }

        $parts = [
            'title ' . ($research['title'] ?? ''),
            'author ' . ($research['author'] ?? ''),
            'crop ' . ($research['crop_variation'] ?? ''),
            'type ' . ($details['knowledge_type'] ?? ''),
            'publisher ' . ($details['publisher'] ?? ''),
            'isbn ' . ($details['isbn_issn'] ?? ''),
            'subjects ' . ($details['subjects'] ?? ''),
            'description ' . ($details['physical_description'] ?? ''),
            'shelf ' . ($details['shelf_location'] ?? ''),
            'pdf ' . $pdfText,
        ];

        return $this->normalizeIndexText(implode(' ', $parts));
    }

    private function hasIndexJobsTable(): bool
    {
        return $this->db->tableExists('research_index_jobs');
    }

    private function getIndexMaxAttempts(): int
    {
        $value = (int) env('search.index.maxAttempts', 3);
        if ($value < 1) {
            return 1;
        }
        if ($value > 10) {
            return 10;
        }
        return $value;
    }

    public function enqueueIndexJob(int $researchId, string $reason = 'update', int $priority = 100): ?int
    {
        if ($researchId <= 0 || !$this->hasIndexJobsTable()) {
            return null;
        }

        $priority = max(1, min(1000, $priority));
        $now = date('Y-m-d H:i:s');

        $existing = $this->indexJobModel
            ->where('research_id', $researchId)
            ->whereIn('status', ['pending', 'processing'])
            ->orderBy('id', 'DESC')
            ->first();

        if ($existing) {
            $this->indexJobModel->update((int) $existing['id'], [
                'reason' => mb_substr($reason, 0, 100),
                'priority' => min((int) ($existing['priority'] ?? $priority), $priority),
                'next_retry_at' => null,
                'updated_at' => $now,
            ]);
            return (int) $existing['id'];
        }

        $id = $this->indexJobModel->insert([
            'research_id' => $researchId,
            'status' => 'pending',
            'reason' => mb_substr($reason, 0, 100),
            'attempt_count' => 0,
            'max_attempts' => $this->getIndexMaxAttempts(),
            'priority' => $priority,
            'next_retry_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], true);

        return $id ? (int) $id : null;
    }

    private function completeIndexJob(int $jobId): void
    {
        if ($jobId <= 0 || !$this->hasIndexJobsTable()) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->indexJobModel->update($jobId, [
            'status' => 'completed',
            'last_error' => null,
            'next_retry_at' => null,
            'completed_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function queueAndRefreshSearchIndex(int $researchId, string $reason = 'update', int $priority = 100): bool
    {
        if ($researchId <= 0 || !$this->hasSearchTextColumn()) {
            return false;
        }

        $jobId = $this->enqueueIndexJob($researchId, $reason, $priority);

        try {
            $ok = $this->refreshSearchIndex($researchId);
            if ($ok && $jobId !== null) {
                $this->completeIndexJob($jobId);
            }

            return $ok;
        } catch (\Throwable $e) {
            log_message(
                'error',
                '[Search Index] Immediate refresh failed for research #' . $researchId . ': ' . $e->getMessage()
            );

            return false;
        }
    }

    public function processPendingIndexJobs(int $limit = 20): array
    {
        if (!$this->hasIndexJobsTable()) {
            return ['processed' => 0, 'completed' => 0, 'failed' => 0, 'requeued' => 0];
        }

        $limit = max(1, min(200, $limit));
        $now = date('Y-m-d H:i:s');
        $processed = 0;
        $completed = 0;
        $failed = 0;
        $requeued = 0;

        $jobs = $this->db->table('research_index_jobs')
            ->groupStart()
                ->where('status', 'pending')
                ->orGroupStart()
                    ->where('status', 'failed')
                    ->where('attempt_count < max_attempts', null, false)
                ->groupEnd()
            ->groupEnd()
            ->groupStart()
                ->where('next_retry_at', null)
                ->orWhere('next_retry_at <=', $now)
            ->groupEnd()
            ->orderBy('priority', 'ASC')
            ->orderBy('id', 'ASC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        foreach ($jobs as $job) {
            $jobId = (int) ($job['id'] ?? 0);
            $researchId = (int) ($job['research_id'] ?? 0);
            if ($jobId <= 0 || $researchId <= 0) {
                continue;
            }

            $processed++;
            $attemptCount = ((int) ($job['attempt_count'] ?? 0)) + 1;
            $maxAttempts = max(1, (int) ($job['max_attempts'] ?? $this->getIndexMaxAttempts()));

            $this->indexJobModel->update($jobId, [
                'status' => 'processing',
                'attempt_count' => $attemptCount,
                'started_at' => $now,
                'updated_at' => $now,
            ]);

            try {
                $ok = $this->refreshSearchIndex($researchId);
                if ($ok) {
                    $completed++;
                    $this->completeIndexJob($jobId);
                    continue;
                }

                throw new \RuntimeException('Index build returned empty/failed');
            } catch (\Throwable $e) {
                if ($attemptCount >= $maxAttempts) {
                    $failed++;
                    $this->indexJobModel->update($jobId, [
                        'status' => 'failed',
                        'last_error' => mb_substr($e->getMessage(), 0, 1000),
                        'next_retry_at' => null,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                } else {
                    $requeued++;
                    $retryAt = date('Y-m-d H:i:s', strtotime('+' . (2 ** min(6, $attemptCount)) . ' minutes'));
                    $this->indexJobModel->update($jobId, [
                        'status' => 'failed',
                        'last_error' => mb_substr($e->getMessage(), 0, 1000),
                        'next_retry_at' => $retryAt,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }

        return [
            'processed' => $processed,
            'completed' => $completed,
            'failed' => $failed,
            'requeued' => $requeued,
        ];
    }

    public function refreshSearchIndex(int $researchId): bool
    {
        if (!$this->hasSearchTextColumn()) {
            return false;
        }

        $row = $this->db->table('researches')
            ->select('researches.id, researches.title, researches.author, researches.crop_variation, researches.file_path, research_details.knowledge_type, research_details.publisher, research_details.isbn_issn, research_details.subjects, research_details.physical_description, research_details.shelf_location')
            ->join('research_details', 'researches.id = research_details.research_id', 'left')
            ->where('researches.id', $researchId)
            ->get()
            ->getRowArray();

        if (!$row) {
            return false;
        }

        $research = [
            'title' => $row['title'] ?? '',
            'author' => $row['author'] ?? '',
            'crop_variation' => $row['crop_variation'] ?? '',
        ];

        $details = [
            'knowledge_type' => $row['knowledge_type'] ?? '',
            'publisher' => $row['publisher'] ?? '',
            'isbn_issn' => $row['isbn_issn'] ?? '',
            'subjects' => $row['subjects'] ?? '',
            'physical_description' => $row['physical_description'] ?? '',
            'shelf_location' => $row['shelf_location'] ?? '',
        ];

        $searchText = $this->buildSearchIndexText($research, $details, (string) ($row['file_path'] ?? ''));

        $this->db->table('research_details')
            ->where('research_id', $researchId)
            ->set([
                'search_text' => $searchText,
            ])
            ->update();

        return true;
    }

    /**
     * Parse various date formats into YYYY-MM-DD
     * Handles: '2018', 'January-June 2006', '01/02/2014'
     */
    private function parseFlexibleDate($dateStr)
    {
        if (empty($dateStr)) return date('Y-m-d'); // Default to Today

        $dateStr = trim($dateStr);

        // 1. Year Only (e.g. "2018") -> "2018-01-01"
        if (preg_match('/^\d{4}$/', $dateStr)) {
            return $dateStr . '-01-01';
        }

        // 2. Month-Month Year (e.g. "January-June 2006", "January -June 2010")
        // Regex: (Month Name)(Space?)-(Space?)(Month Name) (4 Digit Year)
        if (preg_match('/^([a-zA-Z]+)\s*[-]\s*[a-zA-Z]+\s+(\d{4})$/', $dateStr, $matches)) {
            // matches[1] = January, matches[2] = 2006
            $time = strtotime($matches[1] . ' ' . $matches[2]);
            if ($time) return date('Y-m-d', $time);
        }

        // 3. Slashes/Dashes (01/02/2014)
        // Check if it's already YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            return $dateStr;
        }

        // Try standard parsing
        $time = strtotime($dateStr);
        if ($time) {
            return date('Y-m-d', $time);
        }

        // Fallback: If unreadable, return null instead of corrupting data with today's date
        log_message('warning', "parseFlexibleDate: Could not parse date string '{$dateStr}'. Setting to null.");
        return null; 
    }

    // --- READ METHODS ---

    public function getAllApproved($startDate = null, $endDate = null, bool $includePrivate = false, ?string $searchQuery = null, bool $strictSearch = false, ?int $limit = null)
    {
        $builder = $this->researchModel->select($this->selectString)
            ->join('research_details', 'researches.id = research_details.research_id', 'left')
            ->where('researches.status', 'approved');

        if ($this->hasAccessLevelColumn() && !$includePrivate) {
            $builder->where('researches.access_level', 'public');
        }

        if ($startDate) {
            $builder->where('research_details.publication_date >=', $startDate);
        }

        if ($endDate) {
            $builder->where('research_details.publication_date <=', $endDate);
        }

        $searchQuery = trim((string) $searchQuery);
        if ($searchQuery !== '') {
            $correctedQuery = $this->applySpellingCorrections($searchQuery);
            $effectiveQuery = $strictSearch ? $correctedQuery : $this->expandSearchQuery($correctedQuery);

            $scoreExpression = $this->buildSmartSearchScore($effectiveQuery);
            $builder->select($scoreExpression . ' AS relevance_score', false);
            if ($strictSearch) {
                $this->applySpecificSearchFilter($builder, $effectiveQuery);
            } else {
                $this->applySmartSearchFilter($builder, $effectiveQuery);
            }
            $builder->orderBy('relevance_score', 'DESC');
        }

        if ($limit !== null && $limit > 0) {
            $builder->limit(min(50, $limit));
        }

        $results = $builder->orderBy('researches.created_at', 'DESC')->findAll();

        return $results;
    }

    public function getAll()
    {
        return $this->researchModel->select($this->selectString)
            ->join('research_details', 'researches.id = research_details.research_id', 'left')
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

    public function getResearch(int $id)
    {
        return $this->researchModel->select($this->selectString)
            ->join('research_details', 'researches.id = research_details.research_id', 'left')
            ->where('researches.id', $id)
            ->first();
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

        if ($this->hasAccessLevelColumn()) {
            $mainData['access_level'] = $this->normalizeAccessLevel($data['access_level'] ?? self::DEFAULT_ACCESS_LEVEL);
        }

        $newResearchId = $this->researchModel->insert($mainData);

        // Create Logic
        $knowledgeType = $data['knowledge_type'];
        if (is_array($knowledgeType)) {
            $knowledgeType = implode(', ', $knowledgeType);
        }

        $detailsData = [
            'research_id' => $newResearchId,
            'knowledge_type' => $knowledgeType,
            'publication_date' => !empty($data['publication_date']) ? $data['publication_date'] : date('Y-m-d'),
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

        $this->queueAndRefreshSearchIndex((int) $newResearchId, 'create', 70);

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

        if ($this->hasAccessLevelColumn() && $userRole === 'admin' && array_key_exists('access_level', $data)) {
            $mainUpdate['access_level'] = $this->normalizeAccessLevel((string) $data['access_level']);
        }

        if ($file && $file->isValid() && !$file->hasMoved()) {
            $newName = $file->getRandomName();
            $file->move(ROOTPATH . 'public/uploads', $newName);
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
            'publication_date' => !empty($data['publication_date']) ? $data['publication_date'] : date('Y-m-d'),
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
        if ($this->db->transStatus() === false) {
            throw new \Exception("Research update failed.");
        }

        $this->queueAndRefreshSearchIndex($id, 'update', 90);

        return true;
    }

    public function bulkUpdateAccessLevel(array $ids, string $accessLevel): array
    {
        if (!$this->hasAccessLevelColumn()) {
            throw new \RuntimeException('Visibility feature is not initialized. Run "php spark migrate" in backend.');
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));

        if (empty($ids)) {
            return ['matched' => 0, 'updated' => 0];
        }

        $normalizedLevel = $this->normalizeAccessLevel($accessLevel);
        $matched = (int) $this->db->table('researches')->whereIn('id', $ids)->countAllResults();

        if ($matched === 0) {
            return ['matched' => 0, 'updated' => 0];
        }

        $this->db->table('researches')
            ->whereIn('id', $ids)
            ->set([
                'access_level' => $normalizedLevel,
                'updated_at' => date('Y-m-d H:i:s'),
            ])
            ->update();

        $updated = max(0, (int) $this->db->affectedRows());

        return ['matched' => $matched, 'updated' => $updated];
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

    public function importSingleRow($rawData, $userId)
    {
        // Data Mapping
        $data = [
            'title' => $rawData['Title'] ?? 'Untitled',
            'knowledge_type' => $rawData['Type'] ?? 'Research Paper',
            'author' => $rawData['Author'] ?? $rawData['Authors'] ?? 'Unknown',
            'publication_date' => $this->parseFlexibleDate($rawData['Date'] ?? ''),
            'edition' => $rawData['Edition'] ?? $rawData['Publication'] ?? '',
            'publisher' => $rawData['Publisher'] ?? '',
            'physical_description' => $rawData['Pages'] ?? '',
            'isbn_issn' => $rawData['ISBN/ISSN'] ?? $rawData['ISSN'] ?? $rawData['ISBN'] ?? '',
            'subjects' => $rawData['Subjects'] ?? $rawData['Description'] ?? '',
            'shelf_location' => $rawData['Location'] ?? '',
            'item_condition' => $rawData['Condition'] ?? 'Good',
            'crop_variation' => $rawData['Crop'] ?? ''
        ];

        // 🚨 ADDED VALIDATION: Run data against rules before inserting
        $validation = \Config\Services::validation();
        $validationRules = [
            'title' => 'required|min_length[3]|max_length[255]',
            'author' => 'required|min_length[2]|max_length[255]',
            'knowledge_type' => 'required|max_length[100]',
            'publication_date' => 'permit_empty|valid_date',
            'edition' => 'permit_empty|max_length[50]',
            'publisher' => 'permit_empty|max_length[255]',
            'physical_description' => 'permit_empty|max_length[255]',
            'isbn_issn' => 'permit_empty|max_length[50]|alpha_numeric_punct',
            'subjects' => 'permit_empty|string',
            'shelf_location' => 'permit_empty|max_length[100]',
            'item_condition' => 'permit_empty|max_length[50]',
            'crop_variation' => 'permit_empty|max_length[100]',
        ];
        
        $validation->setRules($validationRules);
        if (!$validation->run($data)) {
            $errors = implode(', ', $validation->getErrors());
            return ['status' => 'skipped', 'message' => 'Validation failed: ' . $errors];
        }

        $isbn = trim($data['isbn_issn']);
        $title = trim($data['title']);
        $edition = trim($data['edition']);

        // Check Duplicate
        $dupError = $this->checkDuplicate($title, $data['author'], $isbn, $edition);

        if ($dupError) {
            return ['status' => 'skipped', 'message' => 'Duplicate entry'];
        }

        $this->db->transStart();

        $mainData = [
            'title' => $title,
            'author' => $data['author'],
            'crop_variation' => $data['crop_variation'],
            'status' => 'approved',
            'uploaded_by' => $userId,
            'created_at' => date('Y-m-d H:i:s')
        ];

        if ($this->hasAccessLevelColumn()) {
            $mainData['access_level'] = self::DEFAULT_ACCESS_LEVEL;
        }

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
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return ['status' => 'error', 'message' => 'Database transaction failed'];
        }

        $this->queueAndRefreshSearchIndex((int) $newId, 'import', 120);

        return ['status' => 'success', 'id' => $newId];
    }

    public function importCsv($fileTempName, int $userId)
    {
        ini_set('auto_detect_line_endings', TRUE);

        $handle = fopen($fileTempName, 'r');
        if ($handle === false) {
             throw new \Exception('Failed to open CSV file.');
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
             fclose($handle);
             throw new \Exception('CSV file is empty or missing headers.');
        }
        $headers = array_map('trim', $headers);

        $count = 0;
        $skipped = 0;

        // Streaming rows to prevent memory exhaustion
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($headers))
                continue;

            $rawData = array_combine($headers, $row);
            $result = $this->importSingleRow($rawData, $userId);

            if ($result['status'] === 'success') {
                $count++;
            }
            else {
                $skipped++;
                log_message('warning', "CSV Import Skipped Row: " . ($result['message'] ?? 'Unknown error'));
            }
        }

        fclose($handle);

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
                $this->queueAndRefreshSearchIndex((int) $item->id, 'pdf_attach', 60);
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
