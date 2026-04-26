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

function jsonResponse(Response $response, mixed $data, int $status = 200): Response
{
    $response->getBody()->write(json_encode($data));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($status);
}

$app->get('/', function (Request $request, Response $response) use ($basePath) {
    ob_start();
    require __DIR__ . '/../templates/game.php';
    $html = ob_get_clean();

    $response->getBody()->write($html);
    return $response;
});

$app->get('/games', function (Request $request, Response $response) use ($gameService) {
    return jsonResponse($response, $gameService->getAllGames());
});

$app->post('/games', function (Request $request, Response $response) use ($gameService) {
    $body = $request->getParsedBody();
    $name = $body['name'] ?? 'New Game';

    return jsonResponse($response, $gameService->createGame($name), 201);
});

$app->get('/games/{gameId}', function (Request $request, Response $response, array $args) use ($gameService) {
    $game = $gameService->getGame($args['gameId']);

    if (!$game) {
        return jsonResponse($response, ['message' => 'Game not found'], 404);
    }

    return jsonResponse($response, $game);
});

$app->post('/games/{gameId}/turn/end', function (Request $request, Response $response, array $args) use ($gameService) {
    $game = $gameService->endTurn($args['gameId']);

    if (!$game) {
        return jsonResponse($response, ['message' => 'Game not found'], 404);
    }

    return jsonResponse($response, $game);
});

$app->get('/players', function (Request $request, Response $response) use ($gameService) {
    return jsonResponse($response, $gameService->getAllPlayers());
});

$app->get('/cards', function (Request $request, Response $response) use ($gameService) {
    return jsonResponse($response, $gameService->getAllCards());
});

$app->get('/monsters', function (Request $request, Response $response) use ($gameService) {
    return jsonResponse($response, $gameService->getAllMonsters());
});

$app->post('/deck/draw', function (Request $request, Response $response) use ($gameService) {
    $body = $request->getParsedBody();

    $card = $gameService->drawCard($body['gameId'] ?? '', $body['playerId'] ?? '');

    if (!$card) {
        return jsonResponse($response, ['message' => 'Card could not be drawn'], 400);
    }

    return jsonResponse($response, $card);
});

$app->post('/monsters/{monsterId}/attack', function (Request $request, Response $response, array $args) use ($gameService) {
    $body = $request->getParsedBody();

    $result = $gameService->attackMonster(
        $args['monsterId'],
        $body['playerId'] ?? '',
        (int) ($body['roll'] ?? 0)
    );

    return jsonResponse($response, $result);
});

// New tutorial endpoint
$app->get('/tutorial', function (Request $request, Response $response) use ($tutorialService) {
    return jsonResponse($response, $tutorialService->getTutorialSteps());
});

$app->get('/tutorial/step/{step}', function (Request $request, Response $response, array $args) use ($tutorialService) {
    $step = (int) $args['step'];
    $tutorialStep = $tutorialService->getNextStep($step - 1); // Convert to 0-based

    if (!$tutorialStep) {
        return jsonResponse($response, ['message' => 'Step not found'], 404);
    }

    return jsonResponse($response, $tutorialStep);
});

// New game actions
$app->post('/games/{gameId}/cards/play', function (Request $request, Response $response, array $args) use ($gameService) {
    $body = $request->getParsedBody();

    $result = $gameService->playCard(
        $args['gameId'],
        $body['playerId'] ?? '',
        $body['cardId'] ?? ''
    );

    return jsonResponse($response, $result, $result['success'] ? 200 : 400);
});

$app->post('/games/{gameId}/heroes/{heroId}/roll', function (Request $request, Response $response, array $args) use ($gameService) {
    $body = $request->getParsedBody();

    $result = $gameService->rollDice(
        $args['gameId'],
        $body['playerId'] ?? '',
        $args['heroId']
    );

    return jsonResponse($response, $result, $result['success'] ? 200 : 400);
});

$app->post('/games/{gameId}/modifiers/use', function (Request $request, Response $response, array $args) use ($gameService) {
    $body = $request->getParsedBody();

    $result = $gameService->useModifier(
        $args['gameId'],
        $body['playerId'] ?? '',
        $body['modifierId'] ?? '',
        (int) ($body['modifierValue'] ?? 0)
    );

    return jsonResponse($response, $result, $result['success'] ? 200 : 400);
});

$app->post('/games/{gameId}/challenges', function (Request $request, Response $response, array $args) use ($gameService) {
    $body = $request->getParsedBody();

    $result = $gameService->challengeCard(
        $args['gameId'],
        $body['challengerId'] ?? '',
        $body['targetPlayerId'] ?? '',
        $body['cardId'] ?? ''
    );

    return jsonResponse($response, $result, $result['success'] ? 200 : 400);
});

$app->post('/games/{gameId}/discard-draw', function (Request $request, Response $response, array $args) use ($gameService) {
    $body = $request->getParsedBody();

    $result = $gameService->discardAndDraw(
        $args['gameId'],
        $body['playerId'] ?? ''
    );

    return jsonResponse($response, $result, $result['success'] ? 200 : 400);
});

$app->run();