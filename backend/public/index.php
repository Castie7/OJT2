<?php

// ---------------------------------------------------------------------
// ✅ DYNAMIC CORS FIX (Allows Login Tokens & Cookies)
// ---------------------------------------------------------------------

// 1. Allow the specific origin that is calling us (Laptop, Phone, etc.)
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400'); // Cache for 1 day
}

// 2. Handle the "Preflight" check (OPTIONS method)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");         
    
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    else 
        // Fallback headers just in case
        header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method, Authorization");

    // 3. Send 200 OK and Stop.
    header("HTTP/1.1 200 OK");
    exit(0); 
}

// ---------------------------------------------------------------------
// END CORS BLOCK - Normal CodeIgniter starts below
// ---------------------------------------------------------------------

use CodeIgniter\Boot;
use Config\Paths;

// CHECK PHP VERSION
$minPhpVersion = '8.1'; 
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    $message = sprintf(
        'Your PHP version must be %s or higher to run CodeIgniter. Current version: %s',
        $minPhpVersion,
        PHP_VERSION,
    );
    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo $message;
    exit(1);
}

// SET THE CURRENT DIRECTORY
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

// BOOTSTRAP THE APPLICATION
require FCPATH . '../app/Config/Paths.php';
$paths = new Paths();
require $paths->systemDirectory . '/Boot.php';

exit(Boot::bootWeb($paths));