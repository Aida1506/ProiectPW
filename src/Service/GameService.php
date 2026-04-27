<?php

namespace App\Service;

use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\MonsterRepository;
use App\Repository\PlayerRepository;

class GameService
{
    private GameRepository $gameRepo;
    private PlayerRepository $playerRepo;
    private CardRepository $cardRepo;
    private MonsterRepository $monsterRepo;

    /**
     * Primeste repository-urile necesare si le pastreaza ca dependinte.
     * Service-ul orchestreaza regulile jocului, iar repository-urile fac persistenta.
     */
    public function __construct(
        GameRepository $gameRepo,
        PlayerRepository $playerRepo,
        CardRepository $cardRepo,
        MonsterRepository $monsterRepo
    ) {
        $this->gameRepo = $gameRepo;
        $this->playerRepo = $playerRepo;
        $this->cardRepo = $cardRepo;
        $this->monsterRepo = $monsterRepo;
    }

    /**
     * Returneaza toate jocurile si ataseaza jucatorii fiecarui joc.
     */
    public function getAllGames(): array
    {
        // Citim jocurile din repository fara jucatori atasati.
        $games = $this->gameRepo->findAll();

        // Atasam jucatorii pentru fiecare joc, deoarece tabela players este separata.
        foreach ($games as &$game) {
            $game = $this->attachPlayers($game);
        }

        // Intoarcem lista completa catre controller/API.
        return $games;
    }

    /**
     * Incarca un joc dupa id si ii ataseaza jucatorii si jucatorul curent.
     */
    public function getGame(string $gameId): ?array
    {
        // Cautam jocul dupa id in tabela games.
        $game = $this->gameRepo->findById($gameId);

        if (!$game) {
            // null este transformat in 404 de ruta.
            return null;
        }

        // Completam obiectul cu jucatorii asociati.
        return $this->attachPlayers($game);
    }

    /**
     * Creeaza o sesiune noua de joc.
     * Construieste deck-ul, amesteca monstrii, genereaza 4 jucatori si imparte cate 5 carti.
     */
    public function createGame(string $name): array
    {
        // uniqid genereaza un id suficient de unic pentru sesiuni locale.
        $gameId = uniqid('game_', true);
        // Deck-ul principal este construit din cartile statice din repository.
        $mainDeck = $this->buildMainDeck();
        // Deck-ul de monstri vine din tabela monsters.
        $monsterDeck = $this->monsterRepo->findAll();

        // Amestecam deck-urile ca fiecare joc sa porneasca diferit.
        shuffle($mainDeck);
        shuffle($monsterDeck);

        // Cream cei patru jucatori standard.
        $players = $this->buildPlayers($gameId);

        foreach ($players as &$player) {
            // Fiecare jucator primeste primele 5 carti din deck.
            $player['hand'] = array_splice($mainDeck, 0, 5);
            // Legam explicit jucatorul de joc.
            $player['gameId'] = $gameId;
        }

        // Structura completa a jocului care va fi salvata.
        $game = [
            'id' => $gameId,
            'name' => $name,
            'players' => $players,
            'currentTurnPlayer' => $players[0],
            'currentTurnPlayerId' => $players[0]['id'],
            'actionPoints' => 3,
            'mainDeck' => $mainDeck,
            'discardPile' => [],
            'monsterDeck' => array_slice($monsterDeck, 3),
            'activeMonsters' => array_slice($monsterDeck, 0, 3),
            'lastMessage' => 'Game created. Player 1 starts. Win with 3 slain monsters or 5 heroes in your party.'
        ];

        // Salvam jocul in tabela games.
        $this->gameRepo->save($game);
        // Salvam toti jucatorii in tabela players.
        $this->playerRepo->saveMultiple($players);

        // Returnam jocul nou catre API/frontend.
        return $game;
    }

    /**
     * Actualizeaza metadatele editabile ale jocului.
     * Accepta doar campurile permise si valideaza limitele pentru actionPoints.
     */
    public function updateGame(string $gameId, array $data): ?array
    {
        // Incarcam jocul complet ca sa pastram campurile care nu sunt trimise in body.
        $game = $this->getGame($gameId);

        if (!$game) {
            // Joc inexistent.
            return null;
        }

        if (array_key_exists('name', $data)) {
            // Curatam spatiile din nume.
            $name = trim((string) $data['name']);
            // Nu acceptam nume gol; pastram valoarea veche.
            $game['name'] = $name !== '' ? $name : $game['name'];
        }

        if (array_key_exists('currentTurnPlayerId', $data)) {
            // Permitem schimbarea turei doar daca playerId-ul exista in joc.
            $playerId = (string) $data['currentTurnPlayerId'];

            if ($this->findPlayerInGame($game, $playerId)) {
                // Actualizam id-ul turei curente.
                $game['currentTurnPlayerId'] = $playerId;
                // Actualizam si obiectul currentTurnPlayer pentru raspuns.
                $game['currentTurnPlayer'] = $this->findPlayerInGame($game, $playerId);
            }
        }

        if (array_key_exists('actionPoints', $data)) {
            // Limitam AP intre 0 si 3, ca datele trimise de client sa nu strice regulile.
            $game['actionPoints'] = max(0, min(3, (int) $data['actionPoints']));
        }

        if (array_key_exists('lastMessage', $data)) {
            // lastMessage poate fi folosit de admin/debug pentru mesajul de stare.
            $game['lastMessage'] = (string) $data['lastMessage'];
        }

        // Salvam modificarile.
        $this->gameRepo->save($game);

        // Reincarcam jocul ca raspunsul sa includa date hidratate proaspete.
        return $this->getGame($gameId);
    }

    /**
     * Sterge un joc si jucatorii lui.
     * Intoarce false daca jocul nu exista, pentru ca ruta sa poata trimite 404.
     */
    public function deleteGame(string $gameId): bool
    {
        if (!$this->gameRepo->findById($gameId)) {
            // Nu stergem nimic daca jocul nu exista.
            return false;
        }

        // Stergem mai intai jucatorii legati de joc.
        $this->playerRepo->deleteByGameId($gameId);
        // Apoi stergem jocul propriu-zis.
        return $this->gameRepo->delete($gameId);
    }

    /**
     * Actualizeaza profilul unui jucator: nume, party leader si clasa leaderului.
     */
    public function updatePlayer(string $playerId, array $data): ?array
    {
        // Cautam jucatorul dupa id.
        $player = $this->playerRepo->findById($playerId);

        if (!$player) {
            // Jucator inexistent.
            return null;
        }

        // Acceptam doar campurile editabile.
        foreach (['name', 'partyLeader', 'partyLeaderClass'] as $field) {
            if (array_key_exists($field, $data)) {
                // Eliminam spatiile inutile.
                $value = trim((string) $data[$field]);
                // Nu suprascriem cu string gol.
                $player[$field] = $value !== '' ? $value : $player[$field];
            }
        }

        // Persistam modificarile.
        $this->playerRepo->save($player);

        // Intoarcem varianta proaspat salvata.
        return $this->playerRepo->findById($playerId);
    }

    /**
     * Elimina o carte din mana jucatorului si o muta in discard pile.
     */
    public function discardCardFromHand(string $gameId, string $playerId, string $cardId): array
    {
        return $this->removePlayerCard($gameId, $playerId, $cardId, 'hand', true);
    }

    /**
     * Elimina o carte din party-ul jucatorului si o muta in discard pile.
     */
    public function removeCardFromParty(string $gameId, string $playerId, string $cardId): array
    {
        return $this->removePlayerCard($gameId, $playerId, $cardId, 'party', true);
    }

    /**
     * Scoate un monstru activ din joc si il inlocuieste cu urmatorul monstru din deck, daca exista.
     */
    public function removeActiveMonster(string $gameId, string $monsterId): array
    {
        // Incarcam jocul care contine zona activeMonsters.
        $game = $this->getGame($gameId);

        if (!$game) {
            return ['success' => false, 'message' => 'Game not found.'];
        }

        // Cautam pozitia monstrului in lista de monstri activi.
        $monsterIndex = $this->findMonsterIndex($game['activeMonsters'], $monsterId);

        if ($monsterIndex === null) {
            return ['success' => false, 'message' => 'Monster not found.'];
        }

        // Retinem monstrul eliminat ca sa il putem intoarce in raspuns.
        $removed = $game['activeMonsters'][$monsterIndex];
        // Scoatem monstrul din lista activa.
        array_splice($game['activeMonsters'], $monsterIndex, 1);

        if (!empty($game['monsterDeck'])) {
            // Daca mai exista monstri in deck, tragem unul nou in zona activa.
            $game['activeMonsters'][] = array_shift($game['monsterDeck']);
        }

        // Salvam mesajul ultimei actiuni.
        $game['lastMessage'] = $removed['name'] . ' was removed from the active monsters.';
        // Persistam noua stare a jocului.
        $this->gameRepo->save($game);

        // Raspunsul include monstrul scos si jocul actualizat.
        return ['success' => true, 'message' => $game['lastMessage'], 'monster' => $removed, 'game' => $this->getGame($gameId)];
    }

    /**
     * Trage o carte pentru jucatorul curent.
     * Verifica tura si punctele de actiune, apoi scade 1 AP si salveaza noua stare.
     */
    public function drawCard(string $gameId, string $playerId): ?array
    {
        // Incarcam jocul complet.
        $game = $this->getGame($gameId);

        if (!$game || !$this->canUseAction($game, $playerId, 1)) {
            // Daca jocul lipseste, nu e tura jucatorului sau nu are AP, actiunea esueaza.
            return null;
        }

        // Avem nevoie de index ca sa modificam direct jucatorul din array.
        $playerIndex = $this->findPlayerIndexInGame($game, $playerId);

        if ($playerIndex === null) {
            // PlayerId invalid pentru acest joc.
            return null;
        }

        // Tragem o carte din deck.
        $card = $this->drawOneCard($game);

        if (!$card) {
            // Daca deck-ul este gol si nu exista discard pentru refill, salvam mesajul.
            $game['lastMessage'] = 'The main deck is empty.';
            $this->gameRepo->save($game);
            return null;
        }

        // Adaugam cartea in mana jucatorului.
        $game['players'][$playerIndex]['hand'][] = $card;
        // Draw costa 1 action point.
        $game['actionPoints'] -= 1;
        // Mesajul este afisat in frontend.
        $game['lastMessage'] = $game['players'][$playerIndex]['name'] . ' drew ' . $card['name'] . '.';
        // Actualizam currentTurnPlayer ca raspunsul sa contina mana modificata.
        $game['currentTurnPlayer'] = $game['players'][$playerIndex];

        // Salvam jucatorul si jocul, deoarece am modificat ambele tabele.
        $this->playerRepo->save($game['players'][$playerIndex]);
        $this->gameRepo->save($game);

        // Endpoint-ul de draw intoarce cartea trasa.
        return $card;
    }

    /**
     * Joaca o carte din mana jucatorului.
     * In functie de tipul cartii, o pune in party, o aplica instant sau o trimite in discard.
     */
    public function playCard(string $gameId, string $playerId, string $cardId): array
    {
        // Incarcam jocul dupa id.
        $game = $this->getGame($gameId);

        if (!$game) {
            return ['success' => false, 'message' => 'Game not found.'];
        }

        if (!$this->canUseAction($game, $playerId, 1)) {
            // Jucatorul trebuie sa fie la tura lui si sa aiba cel putin 1 AP.
            return ['success' => false, 'message' => $game['lastMessage'] ?? 'Action not allowed.'];
        }

        // Gasim jucatorul in lista jocului.
        $playerIndex = $this->findPlayerIndexInGame($game, $playerId);

        if ($playerIndex === null) {
            return ['success' => false, 'message' => 'Player not found.'];
        }

        // Cautam cartea in mana jucatorului.
        $cardIndex = $this->findCardIndex($game['players'][$playerIndex]['hand'], $cardId);

        if ($cardIndex === null) {
            return ['success' => false, 'message' => 'Card not found in hand.'];
        }

        // Extragem cartea din mana si o eliminam din lista.
        $card = $game['players'][$playerIndex]['hand'][$cardIndex];
        array_splice($game['players'][$playerIndex]['hand'], $cardIndex, 1);

        // Mesajul va fi setat diferit in functie de tipul cartii.
        $message = '';

        if ($card['type'] === 'hero') {
            // Limitam numarul de eroi pentru varianta simplificata.
            if ($this->countCardsByType($game['players'][$playerIndex]['party'], 'hero') >= 5) {
                // Daca limita este depasita, punem cartea inapoi in mana.
                $game['players'][$playerIndex]['hand'][] = $card;
                return ['success' => false, 'message' => 'You can keep maximum 5 heroes in this simplified version.'];
            }

            // Eroul se pune in party.
            $game['players'][$playerIndex]['party'][] = $card;
            $message = $game['players'][$playerIndex]['name'] . ' played hero ' . $card['name'] . '.';
        } elseif ($card['type'] === 'item') {
            // Itemul ramane in party si contribuie la bonusul de atac.
            $game['players'][$playerIndex]['party'][] = $card;
            $message = $game['players'][$playerIndex]['name'] . ' equipped ' . $card['name'] . '. It gives +1 when attacking monsters.';
        } elseif ($card['type'] === 'modifier') {
            // Modifierul este pastrat in party in aceasta implementare simplificata.
            $game['players'][$playerIndex]['party'][] = $card;
            // Calculam bonusul textului afisat.
            $bonus = $this->getCardAttackBonus($card);
            $message = $game['players'][$playerIndex]['name'] . ' prepared ' . $card['name'] . '. It gives +' . $bonus . ' when attacking monsters.';
        } elseif ($card['type'] === 'magic') {
            // Magic se aplica instant si apoi ajunge in discard.
            $game['discardPile'][] = $card;
            $message = $this->applyMagicCard($game, $game['players'][$playerIndex], $card);
        } elseif ($card['type'] === 'challenge') {
            // Challenge se aplica instant si apoi ajunge in discard.
            $game['discardPile'][] = $card;
            $message = $this->applyChallengeCard($game, $playerIndex, $card);
        } else {
            // Fallback pentru tipuri noi de carti.
            $game['discardPile'][] = $card;
            $message = $game['players'][$playerIndex]['name'] . ' played ' . $card['name'] . '.';
        }

        // Orice play costa 1 AP.
        $game['actionPoints'] -= 1;
        // Verificam daca actiunea a produs o conditie de castig.
        $game['lastMessage'] = $this->appendWinMessage($game, $game['players'][$playerIndex], $message);
        // Sincronizam jucatorul curent din raspuns.
        $game['currentTurnPlayer'] = $game['players'][$playerIndex];

        // Salvam toti jucatorii deoarece anumite efecte pot modifica si alt jucator.
        foreach ($game['players'] as $player) {
            $this->playerRepo->save($player);
        }

        // Salvam deck/discard/AP/mesaj.
        $this->gameRepo->save($game);

        // Intoarcem si cartea jucata, si jocul actualizat.
        return [
            'success' => true,
            'message' => $game['lastMessage'],
            'card' => $card,
            'game' => $game
        ];
    }

    /**
     * Rezolva atacul asupra unui monstru.
     * Gaseste jocul dupa jucator, calculeaza bonusurile, aplica recompensa sau penalizarea si scade 2 AP.
     */
    public function attackMonster(string $monsterId, string $playerId, int $roll): array
    {
        // Pentru atac primim doar playerId, deci gasim jocul prin jucator.
        $game = $this->getGameByPlayerId($playerId);

        if (!$game) {
            return ['success' => false, 'message' => 'Game not found.'];
        }

        if (!$this->canUseAction($game, $playerId, 2)) {
            // Atacul costa 2 AP si trebuie sa fie tura acelui jucator.
            return ['success' => false, 'message' => $game['lastMessage'] ?? 'Action not allowed.'];
        }

        // Gasim jucatorul si monstrul in listele jocului.
        $playerIndex = $this->findPlayerIndexInGame($game, $playerId);
        $monsterIndex = $this->findMonsterIndex($game['activeMonsters'], $monsterId);

        if ($playerIndex === null) {
            return ['success' => false, 'message' => 'Player not found.'];
        }

        if ($monsterIndex === null) {
            return ['success' => false, 'message' => 'Monster not found.'];
        }

        if ($roll < 2 || $roll > 12) {
            // Pentru 2d6 valorile valide sunt intre 2 si 12.
            return ['success' => false, 'message' => 'Roll the dice before attacking.'];
        }

        // Pregatim datele pentru calcul.
        $monster = $game['activeMonsters'][$monsterIndex];
        $bonus = $this->calculateAttackBonus($game['players'][$playerIndex]);
        $finalRoll = $roll + $bonus;
        $requiredRoll = $this->getRollRequirement($monster);

        // Costul atacului se scade indiferent daca atacul reuseste sau esueaza.
        $game['actionPoints'] -= 2;

        if ($finalRoll >= $requiredRoll) {
            // Monstrul este invins si mutat la slainMonsters.
            $game['players'][$playerIndex]['slainMonsters'][] = $monster;
            array_splice($game['activeMonsters'], $monsterIndex, 1);

            if (!empty($game['monsterDeck'])) {
                // Reumplem zona activa cu urmatorul monstru din deck.
                $game['activeMonsters'][] = array_shift($game['monsterDeck']);
            }

            // Aplicam recompensa monstrului invins.
            $rewardText = $this->applyMonsterReward($game, $game['players'][$playerIndex], $monster);
            $message = $game['players'][$playerIndex]['name'] . ' rolled ' . $roll . ' +' . $bonus . ' = ' . $finalRoll . ' and slayed ' . $monster['name'] . '. ' . $rewardText;
            $game['lastMessage'] = $this->appendWinMessage($game, $game['players'][$playerIndex], $message);
        } else {
            // La esec se aplica penalizarea monstrului.
            $penaltyText = $this->applyMonsterPenalty($game, $game['players'][$playerIndex], $monster);
            $game['lastMessage'] = $game['players'][$playerIndex]['name'] . ' rolled ' . $roll . ' +' . $bonus . ' = ' . $finalRoll . '. Needed ' . $requiredRoll . '. ' . $penaltyText;
        }

        // Sincronizam jucatorul curent dupa modificari.
        $game['currentTurnPlayer'] = $game['players'][$playerIndex];

        // Salvam jucatorul modificat si jocul.
        $this->playerRepo->save($game['players'][$playerIndex]);
        $this->gameRepo->save($game);

        // Raspunsul contine toate valorile calculului pentru afisare/debug.
        return [
            'success' => $finalRoll >= $requiredRoll,
            'message' => $game['lastMessage'],
            'roll' => $roll,
            'bonus' => $bonus,
            'finalRoll' => $finalRoll,
            'requiredRoll' => $requiredRoll,
            'game' => $game
        ];
    }

    /**
     * Muta tura la urmatorul jucator si reseteaza actionPoints la 3.
     */
    public function endTurn(string $gameId): ?array
    {
        // Incarcam jocul pentru care se termina tura.
        $game = $this->getGame($gameId);

        if (!$game) {
            // Joc inexistent.
            return null;
        }

        // Lista jucatorilor este deja atasata de getGame.
        $players = $game['players'];
        // Presupunem primul jucator ca fallback.
        $currentIndex = 0;

        // Cautam indexul jucatorului care are tura acum.
        foreach ($players as $index => $player) {
            if ($player['id'] === $game['currentTurnPlayerId']) {
                $currentIndex = $index;
                break;
            }
        }

        // Urmatorul index se intoarce la 0 dupa ultimul jucator.
        $nextIndex = ($currentIndex + 1) % count($players);

        // Actualizam tura curenta.
        $game['currentTurnPlayerId'] = $players[$nextIndex]['id'];
        $game['currentTurnPlayer'] = $players[$nextIndex];
        // Fiecare tura incepe cu 3 AP.
        $game['actionPoints'] = 3;
        // Mesaj vizibil in UI.
        $game['lastMessage'] = $players[$nextIndex]['name'] . ' turn started.';

        // Persistam noua tura.
        $this->gameRepo->save($game);

        // Returnam jocul actualizat.
        return $game;
    }

    /**
     * Returneaza toti jucatorii existenti in baza de date.
     */
    public function getAllPlayers(): array
    {
        return $this->playerRepo->findAll();
    }

    /**
     * Returneaza lista de monstri disponibili.
     */
    public function getAllMonsters(): array
    {
        return $this->monsterRepo->findAll();
    }

    /**
     * Returneaza lista de carti disponibile.
     */
    public function getAllCards(): array
    {
        return $this->cardRepo->findAll();
    }

    /**
     * Arunca 2 zaruri pentru abilitatea unui erou aflat in party.
     * Compara totalul cu rollRequirement-ul eroului si intoarce rezultatul.
     */
    public function rollDice(string $gameId, string $playerId, string $heroId): array
    {
        // Incarcam jocul in care se afla eroul.
        $game = $this->getGame($gameId);

        if (!$game) {
            return ['success' => false, 'message' => 'Game not found.'];
        }

        if ($game['currentTurnPlayerId'] !== $playerId) {
            // Doar jucatorul curent poate activa abilitatea eroului.
            return ['success' => false, 'message' => 'It is not this player turn.'];
        }

        // Gasim jucatorul in joc.
        $player = $this->findPlayerInGame($game, $playerId);

        if (!$player) {
            return ['success' => false, 'message' => 'Player not found.'];
        }

        // Variabila ramane null pana gasim eroul in party.
        $hero = null;

        // Cautam doar in party, nu in mana.
        foreach ($player['party'] as $partyCard) {
            if ($partyCard['id'] === $heroId && $partyCard['type'] === 'hero') {
                // Am gasit eroul corect.
                $hero = $partyCard;
                break;
            }
        }

        if (!$hero) {
            // Nu poti arunca pentru un erou care nu este jucat.
            return ['success' => false, 'message' => 'Hero not in party.'];
        }

        // Simulam 2d6.
        $die1 = rand(1, 6);
        $die2 = rand(1, 6);
        $total = $die1 + $die2;
        // Cerinta vine din carte sau default 7.
        $required = $this->getRollRequirement($hero);

        // Intoarcem rezultatul complet pentru UI.
        return [
            'success' => $total >= $required,
            'message' => 'Rolled ' . $die1 . ' + ' . $die2 . ' = ' . $total . '. Required ' . $required . '.',
            'roll' => $total,
            'dice' => [$die1, $die2]
        ];
    }

    /**
     * Foloseste un modifier.
     * In implementarea simplificata modifierul este tratat ca o carte jucata normal.
     */
    public function useModifier(string $gameId, string $playerId, string $modifierId, int $modifierValue): array
    {
        return $this->playCard($gameId, $playerId, $modifierId);
    }

    /**
     * Aplica efectul unei carti Challenge.
     * Cauta challenge in mana jucatorului, o muta in discard si forteaza tinta sa arunce cartea ceruta daca o are.
     */
    public function challengeCard(string $gameId, string $challengerId, string $targetPlayerId, string $cardId): array
    {
        // Incarcam jocul in care are loc challenge-ul.
        $game = $this->getGame($gameId);

        if (!$game) {
            return ['success' => false, 'message' => 'Game not found.'];
        }

        // Cautam atat jucatorul care provoaca, cat si tinta.
        $challengerIndex = $this->findPlayerIndexInGame($game, $challengerId);
        $targetIndex = $this->findPlayerIndexInGame($game, $targetPlayerId);

        if ($challengerIndex === null || $targetIndex === null) {
            return ['success' => false, 'message' => 'Player not found.'];
        }

        // Initial nu am gasit nicio carte challenge.
        $challengeIndex = null;

        // Cautam prima carte de tip challenge in mana challengerului.
        foreach ($game['players'][$challengerIndex]['hand'] as $index => $card) {
            if ($card['type'] === 'challenge') {
                $challengeIndex = $index;
                break;
            }
        }

        if ($challengeIndex === null) {
            // Nu se poate face challenge fara carte de challenge.
            return ['success' => false, 'message' => 'No challenge card in hand.'];
        }

        // Scoatem cartea challenge din mana si o mutam in discard.
        $challengeCard = $game['players'][$challengerIndex]['hand'][$challengeIndex];
        array_splice($game['players'][$challengerIndex]['hand'], $challengeIndex, 1);
        $game['discardPile'][] = $challengeCard;

        // Cautam cartea tinta in mana jucatorului target.
        $targetCardIndex = $this->findCardIndex($game['players'][$targetIndex]['hand'], $cardId);

        if ($targetCardIndex !== null) {
            // Daca exista, o scoatem din mana si o mutam in discard.
            $discarded = $game['players'][$targetIndex]['hand'][$targetCardIndex];
            array_splice($game['players'][$targetIndex]['hand'], $targetCardIndex, 1);
            $game['discardPile'][] = $discarded;
            $message = $game['players'][$targetIndex]['name'] . ' discarded ' . $discarded['name'] . '.';
        } else {
            // Daca tinta nu are cartea, doar raportam situatia.
            $message = $game['players'][$targetIndex]['name'] . ' had no matching card to discard.';
        }

        // Salvam mesajul rezultat.
        $game['lastMessage'] = 'Challenge used. ' . $message;

        // Salvam ambii jucatori afectati si jocul.
        $this->playerRepo->save($game['players'][$challengerIndex]);
        $this->playerRepo->save($game['players'][$targetIndex]);
        $this->gameRepo->save($game);

        // Intoarcem succesul actiunii.
        return ['success' => true, 'message' => $game['lastMessage']];
    }

    /**
     * Arunca toata mana jucatorului si trage pana la 5 carti noi.
     * Actiunea costa 3 AP si este salvata in starea jocului.
     */
    public function discardAndDraw(string $gameId, string $playerId): array
    {
        // Incarcam jocul complet.
        $game = $this->getGame($gameId);

        if (!$game) {
            return ['success' => false, 'message' => 'Game not found.'];
        }

        if (!$this->canUseAction($game, $playerId, 3)) {
            // Actiunea costa 3 AP, deci se poate face doar la inceputul turei.
            return ['success' => false, 'message' => $game['lastMessage'] ?? 'Action not allowed.'];
        }

        // Gasim jucatorul in joc.
        $playerIndex = $this->findPlayerIndexInGame($game, $playerId);

        if ($playerIndex === null) {
            return ['success' => false, 'message' => 'Player not found.'];
        }

        // Retinem cate carti avea pentru mesajul final.
        $discardedCount = count($game['players'][$playerIndex]['hand']);

        // Mutam fiecare carte din mana in discard pile.
        foreach ($game['players'][$playerIndex]['hand'] as $card) {
            $game['discardPile'][] = $card;
        }

        // Mana devine goala inainte de draw.
        $game['players'][$playerIndex]['hand'] = [];

        // Lista cartilor trase, utila pentru raspuns.
        $drawnCards = [];

        // Incercam sa tragem pana la 5 carti.
        for ($i = 0; $i < 5; $i++) {
            $card = $this->drawOneCard($game);

            if (!$card) {
                // Daca deck-ul s-a golit, oprim bucla.
                break;
            }

            // Adaugam cartea in mana si in lista de raspuns.
            $game['players'][$playerIndex]['hand'][] = $card;
            $drawnCards[] = $card;
        }

        // Costul actiunii este 3 AP.
        $game['actionPoints'] -= 3;
        // Mesaj pentru UI.
        $game['lastMessage'] = $game['players'][$playerIndex]['name'] . ' discarded ' . $discardedCount . ' cards and drew ' . count($drawnCards) . ' new cards.';
        // Sincronizam currentTurnPlayer.
        $game['currentTurnPlayer'] = $game['players'][$playerIndex];

        // Persistam jucatorul si jocul.
        $this->playerRepo->save($game['players'][$playerIndex]);
        $this->gameRepo->save($game);

        // Raspunsul include cate carti s-au aruncat si ce carti s-au tras.
        return [
            'success' => true,
            'message' => $game['lastMessage'],
            'discarded' => $discardedCount,
            'drawn' => $drawnCards,
            'game' => $game
        ];
    }

    /**
     * Ataseaza jucatorii persistati la obiectul jocului.
     * Completeaza si currentTurnPlayer pe baza currentTurnPlayerId.
     */
    private function attachPlayers(array $game): array
    {
        // Citeste jucatorii din tabela players dupa id-ul jocului.
        $game['players'] = $this->playerRepo->findByGameId($game['id']);
        // Gaseste obiectul complet al jucatorului curent.
        $game['currentTurnPlayer'] = $this->findPlayerInGame($game, $game['currentTurnPlayerId']);
        return $game;
    }

    /**
     * Helper comun pentru eliminarea unei carti dintr-o zona a jucatorului.
     * Zona poate fi hand sau party, iar cartea poate fi mutata optional in discard.
     */
    private function removePlayerCard(string $gameId, string $playerId, string $cardId, string $zone, bool $moveToDiscard): array
    {
        // Incarcam jocul si zonele jucatorilor.
        $game = $this->getGame($gameId);

        if (!$game) {
            return ['success' => false, 'message' => 'Game not found.'];
        }

        // Gasim jucatorul care detine cartea.
        $playerIndex = $this->findPlayerIndexInGame($game, $playerId);

        if ($playerIndex === null) {
            return ['success' => false, 'message' => 'Player not found.'];
        }

        // Cautam cartea in zona ceruta: hand sau party.
        $cardIndex = $this->findCardIndex($game['players'][$playerIndex][$zone], $cardId);

        if ($cardIndex === null) {
            return ['success' => false, 'message' => 'Card not found.'];
        }

        // Retinem cartea eliminata pentru raspuns.
        $card = $game['players'][$playerIndex][$zone][$cardIndex];
        // Scoatem cartea din zona.
        array_splice($game['players'][$playerIndex][$zone], $cardIndex, 1);

        if ($moveToDiscard) {
            // Pentru delete-urile actuale cartea ajunge in discard pile.
            $game['discardPile'][] = $card;
        }

        // Mesajul mentioneaza cartea eliminata.
        $game['lastMessage'] = $game['players'][$playerIndex]['name'] . ' discarded ' . $card['name'] . '.';

        // Salvam modificarile.
        $this->playerRepo->save($game['players'][$playerIndex]);
        $this->gameRepo->save($game);

        // Intoarcem cartea eliminata si jocul reimprospatat.
        return ['success' => true, 'message' => $game['lastMessage'], 'card' => $card, 'game' => $this->getGame($gameId)];
    }

    /**
     * Construieste deck-ul principal folosind mai multe copii ale cartilor de baza.
     */
    private function buildMainDeck(): array
    {
        // Luam toate cartile de baza din repository.
        $baseCards = $this->cardRepo->findAll();
        // Deck-ul final va contine mai multe copii ale fiecarei carti.
        $deck = [];

        // Cream 4 copii din fiecare carte pentru a avea un deck mai mare.
        for ($copy = 1; $copy <= 4; $copy++) {
            foreach ($baseCards as $card) {
                // Copiem array-ul original ca sa nu modificam datele de baza.
                $newCard = $card;
                // Id-ul copiei trebuie sa fie unic in deck.
                $newCard['id'] = $card['id'] . '_copy_' . $copy;
                $deck[] = $newCard;
            }
        }

        return $deck;
    }

    /**
     * Verifica daca jucatorul poate face o actiune.
     * Controleaza atat tura curenta, cat si numarul de puncte de actiune disponibile.
     */
    private function canUseAction(array &$game, string $playerId, int $requiredActionPoints): bool
    {
        if ($game['currentTurnPlayerId'] !== $playerId) {
            // Actiunile normale sunt permise doar jucatorului care are tura.
            $game['lastMessage'] = 'It is not this player turn.';
            // Salvam mesajul ca UI-ul sa il poata afisa.
            $this->gameRepo->save($game);
            return false;
        }

        if ($game['actionPoints'] < $requiredActionPoints) {
            // Daca nu sunt destule puncte de actiune, blocam actiunea.
            $game['lastMessage'] = 'Not enough action points.';
            $this->gameRepo->save($game);
            return false;
        }

        // Toate verificarile au trecut.
        return true;
    }

    /**
     * Trage fizic prima carte din mainDeck.
     * Daca deck-ul este gol, amesteca discardPile inapoi in mainDeck.
     */
    private function drawOneCard(array &$game): ?array
    {
        if (empty($game['mainDeck']) && !empty($game['discardPile'])) {
            // Daca mainDeck este gol, refolosim discardPile ca deck nou.
            $game['mainDeck'] = $game['discardPile'];
            // Discard-ul se goleste dupa refill.
            $game['discardPile'] = [];
            // Amestecam cartile refolosite.
            shuffle($game['mainDeck']);
        }

        if (empty($game['mainDeck'])) {
            // Nu exista carte de tras.
            return null;
        }

        // array_shift scoate prima carte din deck si o returneaza.
        return array_shift($game['mainDeck']);
    }

    /**
     * Aplica efectele cartilor magic cunoscute.
     * Fire Spell schimba un monstru activ, iar Healing Spell trage o carte.
     */
    private function applyMagicCard(array &$game, array &$player, array $card): string
    {
        if ($card['name'] === 'Fire Spell') {
            if (empty($game['activeMonsters'])) {
                // Fara monstri activi, vraja nu are tinta.
                return $player['name'] . ' used Fire Spell, but there was no active monster.';
            }

            // Scoatem primul monstru activ.
            $removedMonster = array_shift($game['activeMonsters']);

            if (!empty($game['monsterDeck'])) {
                // Il inlocuim cu urmatorul monstru din deck.
                $game['activeMonsters'][] = array_shift($game['monsterDeck']);
            } else {
                // Daca deck-ul este gol, punem monstrul inapoi ca sa nu ramana zona vida.
                $game['activeMonsters'][] = $removedMonster;
            }

            return $player['name'] . ' used Fire Spell and replaced ' . $removedMonster['name'] . '.';
        }

        if ($card['name'] === 'Healing Spell') {
            // Healing Spell trage o carte.
            $drawn = $this->drawOneCard($game);

            if ($drawn) {
                // Daca exista carte, o adaugam in mana jucatorului.
                $player['hand'][] = $drawn;
                return $player['name'] . ' used Healing Spell and drew ' . $drawn['name'] . '.';
            }

            // Deck-ul a fost gol.
            return $player['name'] . ' used Healing Spell, but the deck was empty.';
        }

        // Fallback pentru alte carti magic.
        return $player['name'] . ' used ' . $card['name'] . '.';
    }

    /**
     * Aplica varianta simplificata a cartii Challenge cand este jucata direct.
     * Tinta este urmatorul jucator, care pierde prima carte din mana daca are una.
     */
    private function applyChallengeCard(array &$game, int $playerIndex, array $card): string
    {
        $targetIndex = ($playerIndex + 1) % count($game['players']);
        $target = &$game['players'][$targetIndex];

        if (empty($target['hand'])) {
            return $game['players'][$playerIndex]['name'] . ' used ' . $card['name'] . ', but ' . $target['name'] . ' had no cards.';
        }

        $discarded = array_shift($target['hand']);
        $game['discardPile'][] = $discarded;

        return $game['players'][$playerIndex]['name'] . ' used ' . $card['name'] . '. ' . $target['name'] . ' discarded ' . $discarded['name'] . '.';
    }

    /**
     * Aplica recompensa unui monstru invins.
     * Detecteaza textul recompensei si trage una sau doua carti cand este cazul.
     */
    private function applyMonsterReward(array &$game, array &$player, array $monster): string
    {
        // Luam textul recompensei in litere mici ca sa putem cauta cuvinte cheie.
        $reward = strtolower($monster['reward'] ?? '');
        // Implicit nu tragem nicio carte.
        $drawCount = 0;

        if (str_contains($reward, 'two')) {
            // Textul contine two, deci tragem doua carti.
            $drawCount = 2;
        } elseif (str_contains($reward, 'one') || str_contains($reward, 'draw')) {
            // Textul sugereaza o carte.
            $drawCount = 1;
        }

        // Pastram numele cartilor trase pentru mesaj.
        $drawnNames = [];

        for ($i = 0; $i < $drawCount; $i++) {
            // Tragem cate o carte.
            $card = $this->drawOneCard($game);

            if (!$card) {
                // Oprim daca deck-ul nu mai are carti.
                break;
            }

            // Adaugam cartea in mana si in lista de nume.
            $player['hand'][] = $card;
            $drawnNames[] = $card['name'];
        }

        if (empty($drawnNames)) {
            // Daca nu s-a tras nimic, afisam textul original al recompensei.
            return 'Reward: ' . ($monster['reward'] ?? 'none');
        }

        // Mesaj cu cartile trase.
        return 'Reward: drew ' . implode(', ', $drawnNames) . '.';
    }

    /**
     * Aplica penalizarea unui atac ratat.
     * Interpreteaza textul penalizarii si modifica mana sau party-ul jucatorului.
     */
    private function applyMonsterPenalty(array &$game, array &$player, array $monster): string
    {
        // Normalizam textul penalizarii pentru cautari simple.
        $penalty = strtolower($monster['penalty'] ?? '');

        if (str_contains($penalty, 'discard your hand')) {
            // Penalizare severa: toata mana merge in discard.
            $count = count($player['hand']);

            foreach ($player['hand'] as $card) {
                $game['discardPile'][] = $card;
            }

            $player['hand'] = [];
            return 'Penalty: discarded the entire hand (' . $count . ' cards).';
        }

        if (str_contains($penalty, 'discard two')) {
            // Arunca doua carti.
            return $this->discardCardsFromHand($game, $player, 2);
        }

        if (str_contains($penalty, 'discard one')) {
            // Arunca o carte.
            return $this->discardCardsFromHand($game, $player, 1);
        }

        if (str_contains($penalty, 'sacrifice')) {
            // Cautam primul erou din party pentru sacrificiu.
            foreach ($player['party'] as $index => $card) {
                if (($card['type'] ?? '') === 'hero') {
                    // Scoatem eroul si il mutam in discard.
                    $removed = $card;
                    array_splice($player['party'], $index, 1);
                    $game['discardPile'][] = $removed;
                    return 'Penalty: sacrificed ' . $removed['name'] . '.';
                }
            }

            return 'Penalty: no hero to sacrifice.';
        }

        if (str_contains($penalty, 'item')) {
            // Cautam primul item din party pentru distrugere.
            foreach ($player['party'] as $index => $card) {
                if (($card['type'] ?? '') === 'item') {
                    // Scoatem itemul si il mutam in discard.
                    $removed = $card;
                    array_splice($player['party'], $index, 1);
                    $game['discardPile'][] = $removed;
                    return 'Penalty: destroyed item ' . $removed['name'] . '.';
                }
            }

            return 'Penalty: no item to destroy.';
        }

        // Daca textul nu este recunoscut, il afisam asa cum este.
        return 'Penalty: ' . ($monster['penalty'] ?? 'none');
    }

    /**
     * Arunca primele N carti din mana jucatorului in discard pile.
     */
    private function discardCardsFromHand(array &$game, array &$player, int $count): string
    {
        $discarded = [];

        for ($i = 0; $i < $count && !empty($player['hand']); $i++) {
            $card = array_shift($player['hand']);
            $game['discardPile'][] = $card;
            $discarded[] = $card['name'];
        }

        if (empty($discarded)) {
            return 'Penalty: no cards to discard.';
        }

        return 'Penalty: discarded ' . implode(', ', $discarded) . '.';
    }

    /**
     * Calculeaza bonusul vizibil de atac al jucatorului din hero, item si modifier.
     */
    private function calculateAttackBonus(array $player): int
    {
        // Bonusul porneste de la zero.
        $bonus = 0;

        // Analizam toate cartile din party.
        foreach ($player['party'] as $card) {
            if (($card['type'] ?? '') === 'hero') {
                // Fiecare erou ajuta la atac in versiunea simplificata.
                $bonus += 1;
            }

            if (($card['type'] ?? '') === 'item') {
                $bonus += 1;
            }

            if (($card['type'] ?? '') === 'modifier') {
                $bonus += $this->getCardAttackBonus($card);
            }
        }

        return $bonus;
    }

    /**
     * Extrage bonusul de atac al unei carti.
     * Plus Two da +2, celelalte carti relevante dau +1.
     */
    private function getCardAttackBonus(array $card): int
    {
        if (str_contains(strtolower($card['name'] ?? ''), 'plus two')) {
            return 2;
        }

        return 1;
    }

    /**
     * Adauga mesajul de castig daca jucatorul indeplineste conditia de victorie.
     */
    private function appendWinMessage(array $game, array $player, string $message): string
    {
        // Numarul de eroi din party.
        $heroes = $this->countCardsByType($player['party'], 'hero');
        // Numarul de monstri invinsi.
        $slain = count($player['slainMonsters']);

        if ($slain >= 3) {
            // Prima conditie de victorie: 3 monstri invinsi.
            return $message . ' ' . $player['name'] . ' wins with 3 slain monsters!';
        }

        if ($heroes >= 5) {
            // A doua conditie simplificata: 5 eroi in party.
            return $message . ' ' . $player['name'] . ' wins with 5 heroes in the party!';
        }

        // Daca nu exista castigator, pastram mesajul original.
        return $message;
    }

    /**
     * Gaseste jocul din care face parte un jucator.
     */
    private function getGameByPlayerId(string $playerId): ?array
    {
        $player = $this->playerRepo->findById($playerId);

        if (!$player || !$player['gameId']) {
            return null;
        }

        return $this->getGame($player['gameId']);
    }

    /**
     * Cauta si returneaza jucatorul cu id-ul cerut dintr-un joc.
     */
    private function findPlayerInGame(array $game, string $playerId): ?array
    {
        foreach ($game['players'] as $player) {
            if ($player['id'] === $playerId) {
                return $player;
            }
        }

        return null;
    }

    /**
     * Cauta indexul jucatorului in lista de players.
     * Indexul este necesar cand trebuie modificat array-ul original.
     */
    private function findPlayerIndexInGame(array $game, string $playerId): ?int
    {
        foreach ($game['players'] as $index => $player) {
            if ($player['id'] === $playerId) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Cauta indexul unei carti intr-o lista de carti.
     */
    private function findCardIndex(array $cards, string $cardId): ?int
    {
        foreach ($cards as $index => $card) {
            if (($card['id'] ?? '') === $cardId) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Cauta indexul unui monstru intr-o lista de monstri.
     */
    private function findMonsterIndex(array $monsters, string $monsterId): ?int
    {
        foreach ($monsters as $index => $monster) {
            if (($monster['id'] ?? '') === $monsterId) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Numara cate carti de un anumit tip exista intr-o lista.
     */
    private function countCardsByType(array $cards, string $type): int
    {
        $count = 0;

        foreach ($cards as $card) {
            if (($card['type'] ?? '') === $type) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Obtine cerinta de zar a unei carti sau a unui monstru.
     * Accepta atat rollRequirement, cat si roll_requirement pentru compatibilitate cu datele din DB.
     */
    private function getRollRequirement(array $card): int
    {
        if (isset($card['rollRequirement'])) {
            return (int) $card['rollRequirement'];
        }

        if (isset($card['roll_requirement'])) {
            return (int) $card['roll_requirement'];
        }

        return 7;
    }

    /**
     * Creeaza cei patru jucatori impliciti pentru un joc nou.
     * Id-urile includ id-ul jocului ca sa fie unice intre sesiuni.
     */
    private function buildPlayers(string $gameId): array
    {
        return [
            [
                'id' => $gameId . '_p1',
                'name' => 'Player 1',
                'partyLeader' => 'Blade Leader',
                'partyLeaderClass' => 'fighter',
                'hand' => [],
                'party' => [],
                'slainMonsters' => [],
                'gameId' => $gameId,
                'role' => 'player'
            ],
            [
                'id' => $gameId . '_p2',
                'name' => 'Player 2',
                'partyLeader' => 'Shadow Leader',
                'partyLeaderClass' => 'thief',
                'hand' => [],
                'party' => [],
                'slainMonsters' => [],
                'gameId' => $gameId,
                'role' => 'player'
            ],
            [
                'id' => $gameId . '_p3',
                'name' => 'Player 3',
                'partyLeader' => 'Mystic Leader',
                'partyLeaderClass' => 'wizard',
                'hand' => [],
                'party' => [],
                'slainMonsters' => [],
                'gameId' => $gameId,
                'role' => 'player'
            ],
            [
                'id' => $gameId . '_p4',
                'name' => 'Player 4',
                'partyLeader' => 'Forest Leader',
                'partyLeaderClass' => 'ranger',
                'hand' => [],
                'party' => [],
                'slainMonsters' => [],
                'gameId' => $gameId,
                'role' => 'player'
            ]
        ];
    }
}
