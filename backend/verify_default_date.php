<?php
// Load CodeIgniter
require 'app/Config/Paths.php';
$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '\\/ ') . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Services\ResearchService;
use App\Models\ResearchModel;
use App\Models\ResearchDetailsModel;

$service = new ResearchService();
$model = new ResearchModel();
$detailsModel = new ResearchDetailsModel();

// Test Data
$userId = 1; // Assuming ID 1 exists
$testTitle = "Default Date Test " . time();
$data = [
    'title' => $testTitle,
    'author' => 'Test Bot',
    'crop_variation' => 'Potato',
    'knowledge_type' => 'Research Paper',
    'publication_date' => '', // EMPTY DATE
    'edition' => '',
    'publisher' => '',
    'physical_description' => '',
    'isbn_issn' => '',
    'subjects' => '',
    'shelf_location' => '',
    'item_condition' => 'Good',
    'link' => ''
];

echo "Creating research with empty publication_date...\n";
try {
    $id = $service->createResearch($userId, $data, null);
    echo "Created ID: $id\n";

    // Verify
    $details = $detailsModel->where('research_id', $id)->first();
    $pubDate = $details->publication_date;
    
    echo "Publication Date in DB: " . $pubDate . "\n";
    $today = date('Y-m-d');

    if ($pubDate === $today) {
        echo "PASS: Date defaulted to today ($today).\n";
    } else {
        echo "FAIL: Date is $pubDate, expected $today.\n";
    }

    // Cleanup
    $model->delete($id);
    $detailsModel->where('research_id', $id)->delete();
    echo "Test record deleted.\n";

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
