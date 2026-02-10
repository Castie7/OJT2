<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// --------------------------------------------------------------------
// 1. GLOBAL OPTIONS HANDLER (CORS PREFLIGHT) - MUST BE FIRST
// --------------------------------------------------------------------
// This intercepts the browser's "Check" before sending data.
$routes->options('(:any)', function() {
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

// ✅ CHANGED: Set to 'get' because App.vue fetches this on load
$routes->get('auth/verify', 'AuthController::verify'); 

$routes->post('auth/logout', 'AuthController::logout');
$routes->post('auth/update-profile', 'AuthController::updateProfile');
$routes->post('auth/register', 'AuthController::register');

// --- ADMIN ROUTES ---
$routes->group('admin', function($routes) {
    $routes->get('users', 'AdminController::index');            // Fetch User List
    $routes->post('reset-password', 'AdminController::resetPassword'); // Reset User Password
});

// --- API ROUTES ---
$routes->group('api', function($routes) {
    $routes->get('notifications', 'NotificationController::index');
    $routes->post('notifications/read', 'NotificationController::markAsRead');
    $routes->post('comments', 'ResearchController::addComment');
    
    // --- ADMIN LOGS ---
    $routes->group('logs', function($routes) {
        $routes->get('export', 'Admin\LogController::export'); // ✅ Export CSV
        $routes->get('/', 'Admin\LogController::index');       // List files
        $routes->get('(:segment)', 'Admin\LogController::show/$1'); // View file
    });
});

// --- RESEARCH ROUTES ---
$routes->group('research', function($routes) {
    
    // Stats
    $routes->get('user-stats/(:num)', 'ResearchController::userStats/$1');
    $routes->get('stats', 'ResearchController::stats');
    
    // Lists
    $routes->get('/', 'ResearchController::index'); 
    $routes->get('archived', 'ResearchController::archived'); 
    $routes->get('my-submissions', 'ResearchController::mySubmissions');
    $routes->get('my-archived', 'ResearchController::myArchived'); 
    $routes->get('pending', 'ResearchController::pending');
    $routes->get('rejected', 'ResearchController::rejectedList'); 

    // Comments List
    $routes->get('comments/(:num)', 'ResearchController::getComments/$1');
    
    // Actions
    $routes->post('create', 'ResearchController::create');
    $routes->post('update/(:num)', 'ResearchController::update/$1');
    $routes->post('approve/(:num)', 'ResearchController::approve/$1');
    $routes->post('reject/(:num)', 'ResearchController::reject/$1');
    $routes->post('extend-deadline/(:num)', 'ResearchController::extendDeadline/$1');
    $routes->post('archive/(:num)', 'ResearchController::archive/$1'); 
    $routes->post('restore/(:num)', 'ResearchController::restore/$1');
    $routes->post('import-csv', 'ResearchController::importCsv'); 
});