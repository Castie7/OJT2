<?php
use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

// Auth
$routes->match(['post', 'options'], 'auth/login', 'AuthController::login');
$routes->match(['post', 'options'], 'auth/verify', 'AuthController::verify');
$routes->match(['post', 'options'], 'auth/logout', 'AuthController::logout');

// Research
$routes->group('research', function($routes) {
    $routes->get('/', 'ResearchController::index');
    $routes->get('pending', 'ResearchController::pending');
    $routes->get('archived', 'ResearchController::archived');

    $routes->match(['post', 'options'], 'create', 'ResearchController::create');
    $routes->match(['post', 'options'], 'update/(:num)', 'ResearchController::update/$1');
    $routes->match(['post', 'options'], 'approve/(:num)', 'ResearchController::approve/$1');
    $routes->match(['post', 'options'], 'reject/(:num)', 'ResearchController::reject/$1');
    $routes->match(['post', 'options'], 'archive/(:num)', 'ResearchController::archive/$1');
});

$routes->options('(:any)', 'Home::index');