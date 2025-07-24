<?php

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware implements MiddlewareInterface
{
    private $requireSuperuser;

    public function __construct(bool $requireSuperuser = false)
    {
        $this->requireSuperuser = $requireSuperuser;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if (!$authHeader || !preg_match('/Bearer (.+)/', $authHeader, $matches)) {
            return (new \Slim\Psr7\Response())->withStatus(401)->withJson(['error' => 'Unauthorized']);
        }

        try {
            $token = $matches[1];
            $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
            $request = $request->withAttribute('user', $decoded);  // Attach user data to request

            if ($this->requireSuperuser && $decoded->user_type !== 'superuser') {
                return (new \Slim\Psr7\Response())->withStatus(403)->withJson(['error' => 'Forbidden: Superuser only']);
            }
        } catch (\Exception $e) {
            return (new \Slim\Psr7\Response())->withStatus(401)->withJson(['error' => 'Invalid token: ' . $e->getMessage()]);
        }

        return $handler->handle($request);
    }
}
