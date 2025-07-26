<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Exception\HttpNotFoundException;
use Firebase\JWT\JWT;
use Slim\Middleware\BodyParsingMiddleware;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';
require __DIR__ . '/db.php';
require __DIR__ . '/CorsMiddleware.php';
require __DIR__ . '/AuthMiddleware.php';

$app = AppFactory::create();
$app->setBasePath('/api'); // Add this to make routes relative to /api/

// Add BodyParsingMiddleware for JSON bodies
$app->add(new BodyParsingMiddleware());

// Middleware stack
$app->add(new CorsMiddleware());
$app->addErrorMiddleware(ENV === 'dev', true, true);

// Login route
$app->post('/login', function (Request $request, Response $response) {
    $data = $request->getParsedBody() ?? [];
    if (!isset($data['username']) || !isset($data['password'])) {
        $response->getBody()->write(json_encode(['error' => 'Username and password required']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute(['username' => $data['username']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($data['password'], $user['password'])) {
        $response->getBody()->write(json_encode(['error' => 'Invalid credentials']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }

    $payload = [
        'id' => $user['id'],
        'username' => $user['username'],
        'user_type' => $user['user_type'],
        'agency_id' => $user['agency_id'],
        'exp' => time() + 3600
    ];
    $token = JWT::encode($payload, JWT_SECRET, 'HS256');

    $response->getBody()->write(json_encode(['token' => $token]));
    return $response->withHeader('Content-Type', 'application/json');
});

// Load agency routes from separate file
$agencyRoutes = require __DIR__ . '/routes/agency.php';
$agencyRoutes($app);

// Load user routes from separate file (existing)
$userRoutes = require __DIR__ . '/routes/user.php';
$userRoutes($app);

// Catch-all for 404 (must be last)
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function (Request $request, Response $response) {
    throw new HttpNotFoundException($request);
});

$app->run();
