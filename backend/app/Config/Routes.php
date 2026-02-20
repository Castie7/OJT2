<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// APP ROUTES
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
        $routes->get('export', 'Admin\LogController::export'); // Export CSV
        $routes->get('/', 'Admin\LogController::index'); // List files
        $routes->get('(:segment)', 'Admin\LogController::show/$1'); // View file
    });
});

// --- RESEARCH ROUTES ---
$routes->group('research', function ($routes) {

    // Stats
    $routes->get('user-stats/(:num)', 'ResearchController::userStats/$1');
    $routes->get('stats', 'ResearchController::stats');
    $routes->get('masterlist', 'ResearchController::masterlist', ['filter' => 'auth']);

    // Lists
    $routes->get('/', 'ResearchController::index');
    $routes->get('(:num)', 'ResearchController::show/$1', ['filter' => 'auth']);
    $routes->get('archived', 'ResearchController::archived', ['filter' => 'auth']);
    $routes->get('my-submissions', 'ResearchController::mySubmissions', ['filter' => 'auth']);
    $routes->get('my-archived', 'ResearchController::myArchived', ['filter' => 'auth']);
    $routes->get('pending', 'ResearchController::pending', ['filter' => 'auth']);
    $routes->get('rejected', 'ResearchController::rejectedList', ['filter' => 'auth']);

    // Comments List (Public/Read Only? Usually requires auth to view)
    $routes->get('comments/(:num)', 'ResearchController::getComments/$1', ['filter' => 'auth']);

    // Actions
    $routes->post('/', 'ResearchController::create', ['filter' => 'auth']);
    $routes->put('(:num)', 'ResearchController::update/$1', ['filter' => 'auth']); // Full update
    $routes->post('(:num)', 'ResearchController::update/$1', ['filter' => 'auth']); // Because HTML forms often use POST for with files, allow POST too
    
    // Status/Lifecycle updates (PATCH or POST to bypass browser CORS OPTIONS caching)
    $routes->match(['patch', 'post'], '(:num)/approve', 'ResearchController::approve/$1', ['filter' => 'auth']);
    $routes->match(['patch', 'post'], '(:num)/reject', 'ResearchController::reject/$1', ['filter' => 'auth']);
    $routes->match(['patch', 'post'], '(:num)/archive', 'ResearchController::archive/$1', ['filter' => 'auth']);
    $routes->match(['patch', 'post'], '(:num)/restore', 'ResearchController::restore/$1', ['filter' => 'auth']);
    $routes->match(['patch', 'post'], '(:num)/extend-deadline', 'ResearchController::extendDeadline/$1', ['filter' => 'auth']);
    
    // Bulk/Import
    $routes->post('import-csv', 'ResearchController::importCsv', ['filter' => 'auth']);
    $routes->post('import-single', 'ResearchController::importSingle', ['filter' => 'auth']);
    $routes->post('bulk-upload-pdfs', 'ResearchController::uploadBulkPdfs', ['filter' => 'auth']);
});
