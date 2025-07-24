<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Exception\HttpNotFoundException;
use Firebase\JWT\JWT;

// Existing requires...
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';  // For JWT_SECRET
require __DIR__ . '/db.php';
require __DIR__ . '/CorsMiddleware.php';
require __DIR__ . '/AuthMiddleware.php';

$app = AppFactory::create();

// Middleware stack
$app->add(new CorsMiddleware());
$app->addErrorMiddleware(ENV === 'dev', true, true);

// Login route (POST /api/login)
$app->post('/login', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute(['username' => $data['username']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($data['password'], $user['password'])) {
        return $response->withStatus(401)->withJson(['error' => 'Invalid credentials']);
    }

    // Generate JWT
    $payload = [
        'id' => $user['id'],
        'username' => $user['username'],
        'user_type' => $user['user_type'],
        'agency_id' => $user['agency_id'],
        'exp' => time() + 3600  // 1 hour expiration
    ];
    $token = JWT::encode($payload, JWT_SECRET, 'HS256');

    return $response->withJson(['token' => $token]);
});

// Superuser-only CRUD for Agencies
$app->group('/agencies', function ($group) {
    // GET all agencies
    $group->get('', function (Request $request, Response $response) {
        $pdo = getDbConnection();
        $stmt = $pdo->query("SELECT * FROM agencies");
        return $response->withJson($stmt->fetchAll());
    });

    // POST create agency
    $group->post('', function (Request $request, Response $response) {
        $data = $request->getParsedBody();
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("INSERT INTO agencies (name, address, contact_email, contact_phone) VALUES (:name, :address, :contact_email, :contact_phone)");
        $stmt->execute($data);
        return $response->withJson(['id' => $pdo->lastInsertId()]);
    });

    // PUT update agency
    $group->put('/{id}', function (Request $request, Response $response, $args) {
        $data = $request->getParsedBody();
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("UPDATE agencies SET name=:name, address=:address, contact_email=:contact_email, contact_phone=:contact_phone WHERE id=:id");
        $stmt->execute(array_merge($data, ['id' => $args['id']]));
        return $response->withJson(['success' => true]);
    });

    // DELETE agency
    $group->delete('/{id}', function (Request $request, Response $response, $args) {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("DELETE FROM agencies WHERE id=:id");
        $stmt->execute(['id' => $args['id']]);
        return $response->withJson(['success' => true]);
    });
})->add(new AuthMiddleware(true));  // Require superuser

// Superuser-only CRUD for Users (can manage all users)
$app->group('/users', function ($group) {
    // GET all users
    $group->get('', function (Request $request, Response $response) {
        $pdo = getDbConnection();
        $stmt = $pdo->query("SELECT * FROM users");
        return $response->withJson($stmt->fetchAll());
    });

    // POST create user
    $group->post('', function (Request $request, Response $response) {
        $data = $request->getParsedBody();
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("INSERT INTO users (agency_id, username, password, email, user_type) VALUES (:agency_id, :username, :password, :email, :user_type)");
        $stmt->execute($data);
        return $response->withJson(['id' => $pdo->lastInsertId()]);
    });

    // PUT update user (excluding password for simplicity; add separate endpoint if needed)
    $group->put('/{id}', function (Request $request, Response $response, $args) {
        $data = $request->getParsedBody();
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("UPDATE users SET agency_id=:agency_id, username=:username, email=:email, user_type=:user_type WHERE id=:id");
        $stmt->execute(array_merge($data, ['id' => $args['id']]));
        return $response->withJson(['success' => true]);
    });

    // DELETE user
    $group->delete('/{id}', function (Request $request, Response $response, $args) {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("DELETE FROM users WHERE id=:id");
        $stmt->execute(['id' => $args['id']]);
        return $response->withJson(['success' => true]);
    });
})->add(new AuthMiddleware(true));  // Require superuser

// Existing catch-all for 404...
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function (Request $request, Response $response) {
    throw new HttpNotFoundException($request);
});

$app->run();
