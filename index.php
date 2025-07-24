<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Exception\HttpNotFoundException; // Add this for custom handling if needed

require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();

// Add error middleware: displayErrorDetails=false (production), logErrors=true, logErrorDetails=true
$app->addErrorMiddleware(false, true, true);

// Optional: Custom 404 handler for better API responses (e.g., JSON for your Angular/Ionic frontend)
$app->setBasePath('/api'); // Add this if routes need to be relative to /api (test without first)

$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write('Hello World from Slim! Ready for Deployment Agency App backend.');
    return $response;
});

// Add a catch-all for 404 if you want custom output (e.g., JSON error for applicants API)
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function (Request $request, Response $response) {
    throw new HttpNotFoundException($request, 'Route not found. Check API docs for Deployment Agency endpoints.');
});

$app->run();
