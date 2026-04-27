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

    /**
     * Primeste configurarea ACL si o pastreaza pentru verificarea fiecarui request.
     */
    public function __construct(AccessControlList $acl)
    {
        // Pastram instanta ACL ca sa o putem folosi la fiecare request.
        $this->acl = $acl;
    }

    /**
     * Intercepteaza fiecare request Slim, identifica ruta si verifica permisiunea.
     * Daca rolul nu are acces, raspunde imediat cu 403 fara sa mai execute handlerul rutei.
     */
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        // Extrage rolul cererii din query string sau header.
        $role = $this->getUserRole($request);
        // Slim pune ruta potrivita pe request dupa middleware-ul de routing.
        $route = $request->getAttribute('route');

        if (!$route) {
            // Daca ruta nu este inca disponibila, lasam request-ul sa continue.
            return $handler->handle($request);
        }

        // Pattern-ul este forma rutei, de exemplu /games/{gameId}.
        $pattern = $route->getPattern();
        // Convertim ruta intr-o resursa logica pentru ACL.
        $resource = $this->getResourceFromRoute($pattern);
        // Convertim metoda HTTP + ruta intr-un privilegiu.
        $privilege = $this->getPrivilegeFromRouteAndMethod($pattern, $request->getMethod());

        if (!$this->acl->isAllowed($role, $resource, $privilege)) {
            // Pentru cereri nepermise, construim manual raspuns JSON 403.
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode(['error' => 'Access denied']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        // Daca ACL permite actiunea, request-ul merge catre handlerul real al rutei.
        return $handler->handle($request);
    }

    /**
     * Citeste rolul utilizatorului din query string sau din header.
     * Daca rolul lipseste ori este invalid, foloseste guest ca valoare sigura.
     */
    private function getUserRole(Request $request): string
    {
        // Prioritate: query parameter role, apoi header X-User-Role, apoi guest.
        $role = $request->getQueryParams()['role'] ?? $request->getHeaderLine('X-User-Role') ?: 'guest';
        // Acceptam doar rolurile cunoscute; orice alta valoare devine guest.
        return in_array($role, ['guest', 'player', 'admin'], true) ? $role : 'guest';
    }

    /**
     * Transforma pattern-ul rutei intr-o resursa ACL.
     * De exemplu rutele cu /deck sau /cards sunt tratate ca resursa card.
     */
    private function getResourceFromRoute(string $pattern): string
    {
        // Rutele legate de mana, party, carti sau deck sunt tratate ca resursa card.
        if (str_contains($pattern, '/hand') || str_contains($pattern, '/party') || str_contains($pattern, '/cards') || str_contains($pattern, '/deck') || str_contains($pattern, '/discard-draw')) {
            return 'card';
        }

        // Rutele cu /players modifica sau citesc jucatori.
        if (str_contains($pattern, '/players')) {
            return 'player';
        }

        // Rutele cu /monsters sunt resursa monster.
        if (str_contains($pattern, '/monsters')) {
            return 'monster';
        }

        // Restul rutelor sunt considerate actiuni pe joc.
        return 'game';
    }

    /**
     * Transforma metoda HTTP si ruta intr-un privilegiu ACL.
     * Astfel POST /deck/draw devine draw, DELETE devine delete, iar GET devine view.
     */
    private function getPrivilegeFromRouteAndMethod(string $pattern, string $method): string
    {
        if ($method === 'GET') {
            // Toate GET-urile sunt doar vizualizare.
            return 'view';
        }

        if (str_contains($pattern, '/deck/draw')) {
            // Draw are privilegiu separat ca sa fie clar in ACL.
            return 'draw';
        }

        if (str_contains($pattern, '/cards/play')) {
            // Jocul unei carti este privilegiul play.
            return 'play';
        }

        if (str_contains($pattern, '/discard-draw')) {
            // Discard-draw este tratat ca actiune de discard.
            return 'discard';
        }

        if (str_contains($pattern, '/hand') || str_contains($pattern, '/party')) {
            // DELETE pe hand/party inseamna eliminarea unei carti.
            return 'discard';
        }

        if (str_contains($pattern, '/attack')) {
            // Atacul de monstru primeste privilegiul attack.
            return 'attack';
        }

        if (str_contains($pattern, '/turn/end')) {
            // Terminarea turei este parte din actiunile de joc.
            return 'play';
        }

        if ($method === 'POST') {
            // POST generic creeaza resurse, de exemplu /games.
            return 'create';
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            // PUT/PATCH sunt editari ale unei resurse existente.
            return 'update_own';
        }

        if ($method === 'DELETE') {
            // DELETE generic cere privilegiu delete, disponibil adminului.
            return 'delete';
        }

        // Fallback conservator pentru metode neasteptate.
        return 'view';
    }
}
