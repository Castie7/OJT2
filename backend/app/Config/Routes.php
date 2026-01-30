<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

// --- AUTH ROUTES ---
$routes->match(['post', 'options'], 'auth/login', 'AuthController::login');
$routes->match(['post', 'options'], 'auth/verify', 'AuthController::verify');
$routes->match(['post', 'options'], 'auth/logout', 'AuthController::logout');

// --- RESEARCH ROUTES ---
$routes->group('research', function($routes) {
    
    // 1. PUBLIC LISTS
    $routes->get('/', 'ResearchController::index'); // Public Library
    $routes->get('archived', 'ResearchController::archived'); // Public Archive (if admins want)
    

    // 2. MY WORKSPACE (User Specific)
    $routes->get('my-submissions', 'ResearchController::mySubmissions');
    $routes->get('my-archived', 'ResearchController::myArchived'); // <--- Fixed missing route

    // 3. ADMIN LISTS
    $routes->get('pending', 'ResearchController::pending');
    
    // FIXED TYPO: ResearchControlaler -> ResearchController
    $routes->get('rejected', 'ResearchController::rejectedList'); 

    // 4. COMMENTS
    $routes->get('comments/(:num)', 'ResearchController::getComments/$1');
    $routes->match(['post', 'options'], 'comment', 'ResearchController::addComment');

    // 5. ACTIONS (Create/Update)
    $routes->match(['post', 'options'], 'create', 'ResearchController::create');
    $routes->match(['post', 'options'], 'update/(:num)', 'ResearchController::update/$1');
    
    // 6. ACTIONS (Workflow)
    $routes->match(['post', 'options'], 'approve/(:num)', 'ResearchController::approve/$1');
    $routes->match(['post', 'options'], 'reject/(:num)', 'ResearchController::reject/$1');
    $routes->match(['post', 'options'], 'extend-deadline/(:num)', 'ResearchController::extendDeadline/$1');

    // 7. ARCHIVE / RESTORE
    // We standardized the controller method to 'archive' in the previous step
    $routes->match(['post', 'options'], 'archive/(:num)', 'ResearchController::archive/$1'); 
    $routes->match(['post', 'options'], 'restore/(:num)', 'ResearchController::restore/$1');
});

// Catch-all for OPTIONS requests (CORS Pre-flight)
$routes->options('(:any)', 'Home::index');