<?php

namespace App\Middleware;

use App\Acl\AccessControlList;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AclMiddleware implements MiddlewareInterface
{
    private AccessControlList $acl;

    public function __construct(AccessControlList $acl)
    {
        $this->acl = $acl;
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $role = $this->getUserRole($request);
        $route = $request->getAttribute('route');

        if (!$route) {
            return $handler->handle($request);
        }

        $pattern = $route->getPattern();
        $resource = $this->getResourceFromRoute($pattern);
        $privilege = $this->getPrivilegeFromRouteAndMethod($pattern, $request->getMethod());

        if (!$this->acl->isAllowed($role, $resource, $privilege)) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode(['error' => 'Access denied']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }

    private function getUserRole(Request $request): string
    {
        $role = $request->getQueryParams()['role'] ?? $request->getHeaderLine('X-User-Role') ?: 'guest';
        return in_array($role, ['guest', 'player', 'admin'], true) ? $role : 'guest';
    }

    private function getResourceFromRoute(string $pattern): string
    {
        if (str_contains($pattern, '/players')) {
            return 'player';
        }

        if (str_contains($pattern, '/cards') || str_contains($pattern, '/deck') || str_contains($pattern, '/discard-draw')) {
            return 'card';
        }

        if (str_contains($pattern, '/monsters')) {
            return 'monster';
        }

        return 'game';
    }

    private function getPrivilegeFromRouteAndMethod(string $pattern, string $method): string
    {
        if ($method === 'GET') {
            return 'view';
        }

        if (str_contains($pattern, '/deck/draw')) {
            return 'draw';
        }

        if (str_contains($pattern, '/cards/play')) {
            return 'play';
        }

        if (str_contains($pattern, '/discard-draw')) {
            return 'discard';
        }

        if (str_contains($pattern, '/attack')) {
            return 'attack';
        }

        if (str_contains($pattern, '/turn/end')) {
            return 'play';
        }

        if ($method === 'POST') {
            return 'create';
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            return 'update_own';
        }

        if ($method === 'DELETE') {
            return 'delete';
        }

        return 'view';
    }
}