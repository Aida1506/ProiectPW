<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Here to Slay Board</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="api-base" content="<?= htmlspecialchars($basePath ?: '') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath ?: '') ?>/assets/css/game.css">
</head>
<body>
    <main class="app-shell">
        <section class="top-panel">
            <div>
                <p class="eyebrow">Digital Board Game</p>
                <h1>Here to Slay</h1>
            </div>

            <div class="game-controls">
                <button id="newGameBtn">New Game</button>
                <button id="refreshBtn">Refresh</button>
            </div>
        </section>

        <section class="status-bar">
            <div>
                <span>Current turn</span>
                <strong id="currentPlayer">-</strong>
            </div>
            <div>
                <span>Action points</span>
                <strong id="actionPoints">3</strong>
            </div>
            <div>
                <span>Main deck</span>
                <strong id="mainDeckCount">0</strong>
            </div>
            <div>
                <span>Monster deck</span>
                <strong id="monsterDeckCount">0</strong>
            </div>
        </section>

        <section class="board-wrapper">
            <div class="board">
                <div class="board-glow"></div>

                <section id="playerTop" class="player-zone player-zone-top"></section>
                <section id="playerLeft" class="player-zone player-zone-left"></section>
                <section id="playerRight" class="player-zone player-zone-right"></section>
                <section id="playerBottom" class="player-zone player-zone-bottom"></section>

                <section class="center-zone">
                    <div class="monster-area">
                        <h2>Active Monsters</h2>
                        <div id="activeMonsters" class="monster-row"></div>
                    </div>

                    <div class="deck-area">
                        <div class="deck-card main-deck">
                            <span>Main Deck</span>
                            <strong id="mainDeckMiniCount">0</strong>
                        </div>

                        <div class="dice-box">
                            <div id="dieOne" class="die">?</div>
                            <div id="dieTwo" class="die">?</div>
                            <button id="rollBtn">Roll Dice</button>
                        </div>

                        <div class="deck-card discard-deck">
                            <span>Discard</span>
                            <strong id="discardCount">0</strong>
                        </div>
                    </div>
                </section>
            </div>
        </section>

        <section class="action-panel">
            <button id="drawBtn">Draw Card - 1 AP</button>
            <button id="discardDrawBtn">Discard Hand + Draw 5 - 3 AP</button>
            <button id="attackBtn">Attack Selected Monster - 2 AP</button>
            <button id="endTurnBtn">End Turn</button>
        </section>

        <section class="hand-panel">
            <div class="hand-header">
                <div>
                    <p class="eyebrow">Active player's hand</p>
                    <h2 id="handOwner">Player 1</h2>
                </div>
                <p id="messageBox">Create or load a game.</p>
            </div>

            <div id="playerHand" class="hand-row"></div>
        </section>
    </main>

    <script src="<?= htmlspecialchars($basePath ?: '') ?>/assets/js/game.js"></script>
</body>
</html>