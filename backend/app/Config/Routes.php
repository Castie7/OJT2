<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

// --- AUTH ROUTES ---
// ❌ REMOVED 'options'. Only use 'post' or 'get'.
$routes->post('auth/login', 'AuthController::login');
$routes->post('auth/verify', 'AuthController::verify');
$routes->post('auth/logout', 'AuthController::logout');
$routes->post('auth/update-profile', 'AuthController::updateProfile');

// --- API ROUTES ---
$routes->group('api', function($routes) {
    // ❌ REMOVED 'options' here too
    $routes->get('notifications', 'NotificationController::index');
    $routes->post('notifications/read', 'NotificationController::markAsRead');
    $routes->post('comments', 'ResearchController::addComment');
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
});

// --------------------------------------------------------------------
// ✅ THE CATCH-ALL (This MUST be the only place handling OPTIONS)
// --------------------------------------------------------------------
$routes->options('(:any)', function() {
    $response = service('response');
    $response->setHeader('Access-Control-Allow-Origin', '*');
    $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, DELETE');
    $response->setHeader('Access-Control-Allow-Headers', 'X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method, Authorization');
    
    return $response->setStatusCode(200);
});