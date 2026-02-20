<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// --------------------------------------------------------------------
// 1. GLOBAL OPTIONS HANDLER (CORS PREFLIGHT) - MUST BE FIRST
// --------------------------------------------------------------------
// This intercepts the browser's "Check" before sending data.
$routes->options('(:any)', function () {
    $response = service('response');

    // ✅ FIX: Dynamic Origin (Works for any IP)
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    $response->setHeader('Access-Control-Allow-Origin', $origin);

    // ✅ FIX: Allow Cookies/Credentials
    $response->setHeader('Access-Control-Allow-Credentials', 'true');

    $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, DELETE');
    $response->setHeader('Access-Control-Allow-Headers', 'X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method, Authorization, X-CSRF-TOKEN');

    return $response->setStatusCode(200);
});

// --------------------------------------------------------------------
// 2. APP ROUTES
// --------------------------------------------------------------------

$routes->get('/', 'Home::index');

// --- AUTH ROUTES ---
$routes->post('auth/login', 'AuthController::login');
$routes->get('auth/verify', 'AuthController::verify');


$routes->post('auth/logout', 'AuthController::logout');
$routes->post('auth/update-profile', 'AuthController::updateProfile');
$routes->post('auth/register', 'AuthController::register');

// --- ADMIN ROUTES ---
$routes->group('admin', ['filter' => 'auth'], function ($routes) {
    $routes->get('users', 'AdminController::index'); // Fetch User List
    $routes->post('reset-password', 'AdminController::resetPassword'); // Reset User Password
});

// --- API ROUTES ---
$routes->group('api', function ($routes) {
    $routes->get('notifications', 'NotificationController::index', ['filter' => 'auth']);
    $routes->post('notifications/read', 'NotificationController::markAsRead', ['filter' => 'auth']);
    $routes->post('comments', 'ResearchController::addComment', ['filter' => 'auth']);

    // --- ADMIN LOGS ---
    $routes->group('logs', ['filter' => 'auth'], function ($routes) {
            $routes->get('export', 'Admin\LogController::export'); // ✅ Export CSV
            $routes->get('/', 'Admin\LogController::index'); // List files
            $routes->get('(:segment)', 'Admin\LogController::show/$1'); // View file
        }
        );
    });

// --- RESEARCH ROUTES ---
$routes->group('research', function ($routes) {

    // Stats
    $routes->get('user-stats/(:num)', 'ResearchController::userStats/$1');
    $routes->get('stats', 'ResearchController::stats');
    $routes->get('masterlist', 'ResearchController::masterlist', ['filter' => 'auth']);

    // Lists
    $routes->get('/', 'ResearchController::index');
    $routes->get('archived', 'ResearchController::archived', ['filter' => 'auth']);
    $routes->get('my-submissions', 'ResearchController::mySubmissions', ['filter' => 'auth']);
    $routes->get('my-archived', 'ResearchController::myArchived', ['filter' => 'auth']);
    $routes->get('pending', 'ResearchController::pending', ['filter' => 'auth']);
    $routes->get('rejected', 'ResearchController::rejectedList', ['filter' => 'auth']);

    // Comments List (Public/Read Only? Usually requires auth to view)
    $routes->get('comments/(:num)', 'ResearchController::getComments/$1');

    // Actions
    $routes->post('create', 'ResearchController::create', ['filter' => 'auth']);
    $routes->post('update/(:num)', 'ResearchController::update/$1', ['filter' => 'auth']);
    $routes->post('approve/(:num)', 'ResearchController::approve/$1', ['filter' => 'auth']);
    $routes->post('reject/(:num)', 'ResearchController::reject/$1', ['filter' => 'auth']);
    $routes->post('extend-deadline/(:num)', 'ResearchController::extendDeadline/$1', ['filter' => 'auth']);
    $routes->post('archive/(:num)', 'ResearchController::archive/$1', ['filter' => 'auth']);
    $routes->post('restore/(:num)', 'ResearchController::restore/$1', ['filter' => 'auth']);
    $routes->post('import-csv', 'ResearchController::importCsv', ['filter' => 'auth']);
    $routes->post('import-single', 'ResearchController::importSingle', ['filter' => 'auth']);
    $routes->post('bulk-upload-pdfs', 'ResearchController::uploadBulkPdfs', ['filter' => 'auth']);
});