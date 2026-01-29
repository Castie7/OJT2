<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

// Auth Routes
$routes->match(['post', 'options'], 'auth/login', 'AuthController::login');
$routes->match(['post', 'options'], 'auth/verify', 'AuthController::verify');
$routes->match(['post', 'options'], 'auth/logout', 'AuthController::logout');

// Research Routes
$routes->group('research', function($routes) {
    // Get Lists
    $routes->get('/', 'ResearchController::index');
    $routes->get('pending', 'ResearchController::pending');
    $routes->get('archived', 'ResearchController::archived');
    // Add this new line:
    $routes->get('my-archived', 'ResearchController::myArchived');
    
    // --- THIS WAS MISSING ---
    $routes->get('my-submissions', 'ResearchController::mySubmissions');
    // ------------------------

    // Comments
    $routes->get('comments/(:num)', 'ResearchController::getComments/$1');
    $routes->match(['post', 'options'], 'comment', 'ResearchController::addComment');

    // Actions
    $routes->match(['post', 'options'], 'create', 'ResearchController::create');
    $routes->match(['post', 'options'], 'update/(:num)', 'ResearchController::update/$1');
    $routes->match(['post', 'options'], 'approve/(:num)', 'ResearchController::approve/$1');
    $routes->match(['post', 'options'], 'reject/(:num)', 'ResearchController::reject/$1');
    // Note: The controller method is called 'toggleArchive', but your route pointed to 'archive'.
    // I updated this to match the controller method name 'toggleArchive' to be safe, 
    // OR ensure your controller has a method literally named 'archive'.
    // Based on previous code, it was 'toggleArchive'.
    $routes->match(['post', 'options'], 'archive/(:num)', 'ResearchController::toggleArchive/$1'); 
});

// Catch-all for OPTIONS requests (CORS)
$routes->options('(:any)', 'Home::index');