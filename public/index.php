<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'C:\xampp\htdocs\proiectpw\error.log');

use App\Database\Database;
use App\Database\DatabaseConfig;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Repository\CardRepository;
use App\Repository\MonsterRepository;
use App\Service\GameService;
use App\Service\TutorialService;
use App\Middleware\AclMiddleware;
use App\Acl\AccessControlList;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Initialize database
$config = DatabaseConfig::getConfig();
$db = new Database($config);

// Initialize repositories
$gameRepo = new GameRepository($db);
$playerRepo = new PlayerRepository($db);
$cardRepo = new CardRepository($db);
$monsterRepo = new MonsterRepository($db);

// Initialize services
$gameService = new GameService($gameRepo, $playerRepo, $cardRepo, $monsterRepo);
$tutorialService = new TutorialService();

// Initialize ACL
$acl = new AccessControlList();
$aclMiddleware = new AclMiddleware($acl);

$app = AppFactory::create();

$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

if ($basePath !== '' && $basePath !== '/') {
    $app->setBasePath($basePath);
}

$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(true, true, true);

// Add ACL middleware to all routes
$app->add($aclMiddleware);

/**
 * Scrie un raspuns JSON standardizat.
 * Toate endpoint-urile API folosesc aceasta functie ca sa trimita content-type si status HTTP corect.
 */
function jsonResponse(Response $response, mixed $data, int $status = 200): Response
{
    // Convertim datele PHP in text JSON si le scriem in body-ul raspunsului.
    $response->getBody()->write(json_encode($data));
    // Setam content-type JSON si statusul HTTP cerut de ruta.
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($status);
}

// Afiseaza interfata web principala a jocului.
$app->get('/', function (Request $request, Response $response) use ($basePath) {
    // Pornim bufferul ca sa capturam HTML-ul generat de template.
    ob_start();
    // Includem fisierul template al interfetei.
    require __DIR__ . '/../templates/game.php';
    // Scoatem HTML-ul din buffer intr-o variabila.
    $html = ob_get_clean();

    // Trimitem HTML-ul catre browser.
    $response->getBody()->write($html);
    return $response;
});

// Returneaza toate jocurile existente.
$app->get('/games', function (Request $request, Response $response) use ($gameService) {
    return jsonResponse($response, $gameService->getAllGames());
});

// Creeaza un joc nou pornind de la numele primit in body.
$app->post('/games', function (Request $request, Response $response) use ($gameService) {
    // BodyParsingMiddleware transforma JSON-ul requestului in array PHP.
    $body = $request->getParsedBody();
    // Daca nu exista name in body, folosim un nume implicit.
    $name = $body['name'] ?? 'New Game';

    // Cream jocul si raspundem cu 201 Created.
    return jsonResponse($response, $gameService->createGame($name), 201);
});

// Returneaza detaliile unui joc specific.
$app->get('/games/{gameId}', function (Request $request, Response $response, array $args) use ($gameService) {
    // gameId este extras de Slim din URL.
    $game = $gameService->getGame($args['gameId']);

    if (!$game) {
        // Jocul nu exista, deci raspundem 404.
        return jsonResponse($response, ['message' => 'Game not found'], 404);
    }

    // Joc gasit, trimitem starea completa.
    return jsonResponse($response, $game);
});

// Actualizeaza campurile editabile ale jocului prin PUT.
$app->put('/games/{gameId}', function (Request $request, Response $response, array $args) use ($gameService) {
    // Trimitem catre service id-ul din URL si body-ul JSON.
    $game = $gameService->updateGame($args['gameId'], $request->getParsedBody() ?? []);

    if (!$game) {
        return jsonResponse($response, ['message' => 'Game not found'], 404);
    }

    // Raspunsul este jocul actualizat.
    return jsonResponse($response, $game);
});

// Actualizeaza partial jocul prin PATCH.
$app->patch('/games/{gameId}', function (Request $request, Response $response, array $args) use ($gameService) {
    // PATCH foloseste aceeasi metoda, dar body-ul poate contine doar o parte din campuri.
    $game = $gameService->updateGame($args['gameId'], $request->getParsedBody() ?? []);

    if (!$game) {
        return jsonResponse($response, ['message' => 'Game not found'], 404);
    }

    return jsonResponse($response, $game);
});

// Sterge un joc si jucatorii asociati.
$app->delete('/games/{gameId}', function (Request $request, Response $response, array $args) use ($gameService) {
    // deleteGame sterge jocul si jucatorii lui.
    if (!$gameService->deleteGame($args['gameId'])) {
        return jsonResponse($response, ['message' => 'Game not found'], 404);
    }

    // Confirmam stergerea cu mesaj simplu.
    return jsonResponse($response, ['message' => 'Game deleted']);
});

// Incheie tura curenta si trece la urmatorul jucator.
$app->post('/games/{gameId}/turn/end', function (Request $request, Response $response, array $args) use ($gameService) {
    // Service-ul muta tura la urmatorul jucator.
    $game = $gameService->endTurn($args['gameId']);

    if (!$game) {
        return jsonResponse($response, ['message' => 'Game not found'], 404);
    }

    return jsonResponse($response, $game);
});

// Returneaza toti jucatorii.
$app->get('/players', function (Request $request, Response $response) use ($gameService) {
    return jsonResponse($response, $gameService->getAllPlayers());
});

// Actualizeaza datele unui jucator prin PUT.
$app->put('/players/{playerId}', function (Request $request, Response $response, array $args) use ($gameService) {
    // Actualizam jucatorul identificat in URL.
    $player = $gameService->updatePlayer($args['playerId'], $request->getParsedBody() ?? []);

    if (!$player) {
        return jsonResponse($response, ['message' => 'Player not found'], 404);
    }

    return jsonResponse($response, $player);
});

// Actualizeaza partial datele unui jucator prin PATCH.
$app->patch('/players/{playerId}', function (Request $request, Response $response, array $args) use ($gameService) {
    // PATCH permite modificarea doar a campurilor trimise.
    $player = $gameService->updatePlayer($args['playerId'], $request->getParsedBody() ?? []);

    if (!$player) {
        return jsonResponse($response, ['message' => 'Player not found'], 404);
    }

    return jsonResponse($response, $player);
});

// Returneaza toate cartile disponibile.
$app->get('/cards', function (Request $request, Response $response) use ($gameService) {
    return jsonResponse($response, $gameService->getAllCards());
});

// Returneaza toti monstrii disponibili.
$app->get('/monsters', function (Request $request, Response $response) use ($gameService) {
    return jsonResponse($response, $gameService->getAllMonsters());
});

// Trage o carte pentru jucatorul curent.
$app->post('/deck/draw', function (Request $request, Response $response) use ($gameService) {
    // Body-ul trebuie sa contina gameId si playerId.
    $body = $request->getParsedBody();

    // drawCard modifica mana jucatorului si deck-ul jocului.
    $card = $gameService->drawCard($body['gameId'] ?? '', $body['playerId'] ?? '');

    if (!$card) {
        // Actiunea poate esua daca nu e tura jucatorului sau nu are AP.
        return jsonResponse($response, ['message' => 'Card could not be drawn'], 400);
    }

    // Returnam cartea trasa.
    return jsonResponse($response, $card);
});

// Rezolva atacul asupra unui monstru.
$app->post('/monsters/{monsterId}/attack', function (Request $request, Response $response, array $args) use ($gameService) {
    // Body-ul contine playerId si roll.
    $body = $request->getParsedBody();

    // monsterId vine din URL, restul din body.
    $result = $gameService->attackMonster(
        $args['monsterId'],
        $body['playerId'] ?? '',
        (int) ($body['roll'] ?? 0)
    );

    return jsonResponse($response, $result);
});

// Returneaza tot tutorialul.
$app->get('/tutorial', function (Request $request, Response $response) use ($tutorialService) {
    return jsonResponse($response, $tutorialService->getTutorialSteps());
});

// Returneaza un pas de tutorial dupa numar.
$app->get('/tutorial/step/{step}', function (Request $request, Response $response, array $args) use ($tutorialService) {
    // Parametrul din URL este convertit la int.
    $step = (int) $args['step'];
    // Service-ul foloseste index zero-based, de aceea scadem 1.
    $tutorialStep = $tutorialService->getNextStep($step - 1); // Convert to 0-based

    if (!$tutorialStep) {
        return jsonResponse($response, ['message' => 'Step not found'], 404);
    }

    return jsonResponse($response, $tutorialStep);
});

// Joaca o carte din mana jucatorului.
$app->post('/games/{gameId}/cards/play', function (Request $request, Response $response, array $args) use ($gameService) {
    // Body-ul contine playerId si cardId.
    $body = $request->getParsedBody();

    // Service-ul scoate cartea din mana si aplica efectul ei.
    $result = $gameService->playCard(
        $args['gameId'],
        $body['playerId'] ?? '',
        $body['cardId'] ?? ''
    );

    return jsonResponse($response, $result, $result['success'] ? 200 : 400);
});

// Arunca zarurile pentru abilitatea unui erou.
$app->post('/games/{gameId}/heroes/{heroId}/roll', function (Request $request, Response $response, array $args) use ($gameService) {
    // Body-ul contine playerId; heroId vine din URL.
    $body = $request->getParsedBody();

    $result = $gameService->rollDice(
        $args['gameId'],
        $body['playerId'] ?? '',
        $args['heroId']
    );

    return jsonResponse($response, $result, $result['success'] ? 200 : 400);
});

// Foloseste o carte modifier.
$app->post('/games/{gameId}/modifiers/use', function (Request $request, Response $response, array $args) use ($gameService) {
    // Body-ul contine datele modifierului folosit.
    $body = $request->getParsedBody();

    $result = $gameService->useModifier(
        $args['gameId'],
        $body['playerId'] ?? '',
        $body['modifierId'] ?? '',
        (int) ($body['modifierValue'] ?? 0)
    );

    return jsonResponse($response, $result, $result['success'] ? 200 : 400);
});

// Foloseste o carte challenge impotriva unui alt jucator.
$app->post('/games/{gameId}/challenges', function (Request $request, Response $response, array $args) use ($gameService) {
    // Body-ul spune cine provoaca, cine este tinta si ce carte este vizata.
    $body = $request->getParsedBody();

    $result = $gameService->challengeCard(
        $args['gameId'],
        $body['challengerId'] ?? '',
        $body['targetPlayerId'] ?? '',
        $body['cardId'] ?? ''
    );

    return jsonResponse($response, $result, $result['success'] ? 200 : 400);
});

// Arunca mana curenta si trage pana la cinci carti noi.
$app->post('/games/{gameId}/discard-draw', function (Request $request, Response $response, array $args) use ($gameService) {
    // Body-ul contine jucatorul care face actiunea.
    $body = $request->getParsedBody();

    $result = $gameService->discardAndDraw(
        $args['gameId'],
        $body['playerId'] ?? ''
    );

    return jsonResponse($response, $result, $result['success'] ? 200 : 400);
});

// Sterge o carte din mana jucatorului si o muta in discard.
$app->delete('/games/{gameId}/players/{playerId}/hand/{cardId}', function (Request $request, Response $response, array $args) use ($gameService) {
    // Toate id-urile vin direct din ruta.
    $result = $gameService->discardCardFromHand($args['gameId'], $args['playerId'], $args['cardId']);

    return jsonResponse($response, $result, $result['success'] ? 200 : 400);
});

// Sterge o carte din party-ul jucatorului.
$app->delete('/games/{gameId}/players/{playerId}/party/{cardId}', function (Request $request, Response $response, array $args) use ($gameService) {
    // Eliminam cartea indicata din party.
    $result = $gameService->removeCardFromParty($args['gameId'], $args['playerId'], $args['cardId']);

    return jsonResponse($response, $result, $result['success'] ? 200 : 400);
});

// Sterge un monstru activ si il inlocuieste daca mai exista monstri in deck.
$app->delete('/games/{gameId}/active-monsters/{monsterId}', function (Request $request, Response $response, array $args) use ($gameService) {
    // Eliminam monstrul activ indicat si reumplem zona daca exista deck.
    $result = $gameService->removeActiveMonster($args['gameId'], $args['monsterId']);

    return jsonResponse($response, $result, $result['success'] ? 200 : 400);
});

$app->run();
