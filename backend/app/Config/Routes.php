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

    // --- NEW: COMMENTS ROUTES (THIS WAS MISSING) ---
    // 1. Get comments for a specific research ID
    $routes->get('comments/(:num)', 'ResearchController::getComments/$1');
    // 2. Post a new comment
    $routes->match(['post', 'options'], 'comment', 'ResearchController::addComment');
    // -----------------------------------------------

    // Actions
    $routes->match(['post', 'options'], 'create', 'ResearchController::create');
    $routes->match(['post', 'options'], 'update/(:num)', 'ResearchController::update/$1');
    $routes->match(['post', 'options'], 'approve/(:num)', 'ResearchController::approve/$1');
    $routes->match(['post', 'options'], 'reject/(:num)', 'ResearchController::reject/$1');
    $routes->match(['post', 'options'], 'archive/(:num)', 'ResearchController::archive/$1');
});

// Catch-all for OPTIONS requests (CORS)
$routes->options('(:any)', 'Home::index');