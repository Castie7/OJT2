<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

// --- AUTH ROUTES ---
$routes->match(['post', 'options'], 'auth/login', 'AuthController::login');
$routes->match(['post', 'options'], 'auth/verify', 'AuthController::verify');
$routes->match(['post', 'options'], 'auth/logout', 'AuthController::logout');
$routes->match(['post', 'options'], 'auth/update-profile', 'AuthController::updateProfile');

// --- API ROUTES (Notifications & Comments) ---
// This handles localhost:8080/api/...
$routes->group('api', function($routes) {
    
    // 1. Notifications
    $routes->match(['get', 'options'], 'notifications', 'NotificationController::index');
    $routes->match(['post', 'options'], 'notifications/read', 'NotificationController::markAsRead');

    // 2. Comments (Fixes the CORS/404 issue)
    $routes->match(['post', 'options'], 'comments', 'ResearchController::addComment');
});

// --- RESEARCH ROUTES ---
$routes->group('research', function($routes) {
    
    // Stats
    $routes->match(['get', 'options'], 'user-stats/(:num)', 'ResearchController::userStats/$1');
    $routes->match(['get', 'options'], 'stats', 'ResearchController::stats');

    // Lists
    $routes->match(['get', 'options'], '/', 'ResearchController::index'); 
    $routes->match(['get', 'options'], 'archived', 'ResearchController::archived'); 
    $routes->match(['get', 'options'], 'my-submissions', 'ResearchController::mySubmissions');
    $routes->match(['get', 'options'], 'my-archived', 'ResearchController::myArchived'); 
    $routes->match(['get', 'options'], 'pending', 'ResearchController::pending');
    $routes->match(['get', 'options'], 'rejected', 'ResearchController::rejectedList'); 

    // Comments List (GET)
    $routes->match(['get', 'options'], 'comments/(:num)', 'ResearchController::getComments/$1');
    
    // Actions
    $routes->match(['post', 'options'], 'create', 'ResearchController::create');
    $routes->match(['post', 'options'], 'update/(:num)', 'ResearchController::update/$1');
    $routes->match(['post', 'options'], 'approve/(:num)', 'ResearchController::approve/$1');
    $routes->match(['post', 'options'], 'reject/(:num)', 'ResearchController::reject/$1');
    $routes->match(['post', 'options'], 'extend-deadline/(:num)', 'ResearchController::extendDeadline/$1');
    $routes->match(['post', 'options'], 'archive/(:num)', 'ResearchController::archive/$1'); 
    $routes->match(['post', 'options'], 'restore/(:num)', 'ResearchController::restore/$1');
});

// Catch-all for Pre-flight checks
$routes->options('(:any)', function() {
    $response = service('response');
    $response->setHeader('Access-Control-Allow-Origin', '*');
    $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, DELETE');
    $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
    $response->setStatusCode(200);
    return $response;
});