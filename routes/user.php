<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

return function ($app) {
    $app->group('/users', function ($group) {
        $group->get('', function (Request $request, Response $response) {
            $pdo = getDbConnection();
            $stmt = $pdo->query("SELECT * FROM users");
            $response->getBody()->write(json_encode($stmt->fetchAll()));
            return $response->withHeader('Content-Type', 'application/json');
        });

        $group->post('', function (Request $request, Response $response) {
            $data = $request->getParsedBody();
            if (!isset($data['username']) || !isset($data['password']) || !isset($data['email']) || !isset($data['user_type'])) {
                $response->getBody()->write(json_encode(['error' => 'Required fields: username, password, email, user_type']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            $data['agency_id'] = isset($data['agency_id']) ? $data['agency_id'] : NULL; // Optional, NULL for superuser
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("INSERT INTO users (agency_id, username, password, email, user_type) VALUES (:agency_id, :username, :password, :email, :user_type)");
            $stmt->execute($data);
            $response->getBody()->write(json_encode(['id' => $pdo->lastInsertId()]));
            return $response->withHeader('Content-Type', 'application/json');
        });

        $group->put('/{id}', function (Request $request, Response $response, $args) {
            $data = $request->getParsedBody();
            if (isset($data['password'])) {
                $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT); // Allow optional password update
            }
            $pdo = getDbConnection();
            $set = [];
            $params = [];
            if (isset($data['agency_id'])) {
                $set[] = "agency_id = :agency_id";
                $params['agency_id'] = $data['agency_id'];
            }
            if (isset($data['username'])) {
                $set[] = "username = :username";
                $params['username'] = $data['username'];
            }
            if (isset($data['password'])) {
                $set[] = "password = :password";
                $params['password'] = $data['password'];
            }
            if (isset($data['email'])) {
                $set[] = "email = :email";
                $params['email'] = $data['email'];
            }
            if (isset($data['user_type'])) {
                $set[] = "user_type = :user_type";
                $params['user_type'] = $data['user_type'];
            }
            if (empty($set)) {
                $response->getBody()->write(json_encode(['error' => 'No fields to update']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $setStr = implode(', ', $set);
            $stmt = $pdo->prepare("UPDATE users SET $setStr WHERE id = :id");
            $params['id'] = $args['id'];
            $stmt->execute($params);
            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        });

        $group->delete('/{id}', function (Request $request, Response $response, $args) {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute(['id' => $args['id']]);
            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        });
    })->add(new AuthMiddleware(true)); // Secure for superuser
};
