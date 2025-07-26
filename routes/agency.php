<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

return function ($app) {
    $app->group('/agencies', function ($group) {
        $group->get('', function (Request $request, Response $response) {
            $pdo = getDbConnection();
            $stmt = $pdo->query("SELECT * FROM agencies");
            $response->getBody()->write(json_encode($stmt->fetchAll()));
            return $response->withHeader('Content-Type', 'application/json');
        });

        $group->post('', function (Request $request, Response $response) {
            $data = $request->getParsedBody();
            if (!isset($data['name']) || !isset($data['address']) || !isset($data['contact_email']) || !isset($data['contact_phone'])) {
                $response->getBody()->write(json_encode(['error' => 'Required fields: name, address, contact_email, contact_phone']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("INSERT INTO agencies (name, address, contact_email, contact_phone) VALUES (:name, :address, :contact_email, :contact_phone)");
            $stmt->execute($data);
            $response->getBody()->write(json_encode(['id' => $pdo->lastInsertId()]));
            return $response->withHeader('Content-Type', 'application/json');
        });

        $group->put('/{id}', function (Request $request, Response $response, $args) {
            $data = $request->getParsedBody();
            $pdo = getDbConnection();
            $set = [];
            $params = [];
            if (isset($data['name'])) {
                $set[] = "name = :name";
                $params['name'] = $data['name'];
            }
            if (isset($data['address'])) {
                $set[] = "address = :address";
                $params['address'] = $data['address'];
            }
            if (isset($data['contact_email'])) {
                $set[] = "contact_email = :contact_email";
                $params['contact_email'] = $data['contact_email'];
            }
            if (isset($data['contact_phone'])) {
                $set[] = "contact_phone = :contact_phone";
                $params['contact_phone'] = $data['contact_phone'];
            }
            if (empty($set)) {
                $response->getBody()->write(json_encode(['error' => 'No fields to update']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $setStr = implode(', ', $set);
            $stmt = $pdo->prepare("UPDATE agencies SET $setStr WHERE id = :id");
            $params['id'] = $args['id'];
            $stmt->execute($params);
            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        });

        $group->delete('/{id}', function (Request $request, Response $response, $args) {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("DELETE FROM agencies WHERE id = :id");
            $stmt->execute(['id' => $args['id']]);
            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        });
    })->add(new AuthMiddleware(true)); // Secure for superuser
};
