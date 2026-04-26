const API_BASE = document.querySelector('meta[name="api-base"]').content;

let currentGame = null;
let selectedMonsterId = null;
let lastRoll = 0;

const els = {
    newGameBtn: document.getElementById('newGameBtn'),
    refreshBtn: document.getElementById('refreshBtn'),
    currentPlayer: document.getElementById('currentPlayer'),
    actionPoints: document.getElementById('actionPoints'),
    mainDeckCount: document.getElementById('mainDeckCount'),
    mainDeckMiniCount: document.getElementById('mainDeckMiniCount'),
    monsterDeckCount: document.getElementById('monsterDeckCount'),
    discardCount: document.getElementById('discardCount'),
    activeMonsters: document.getElementById('activeMonsters'),
    playerTop: document.getElementById('playerTop'),
    playerLeft: document.getElementById('playerLeft'),
    playerRight: document.getElementById('playerRight'),
    playerBottom: document.getElementById('playerBottom'),
    playerHand: document.getElementById('playerHand'),
    handOwner: document.getElementById('handOwner'),
    messageBox: document.getElementById('messageBox'),
    drawBtn: document.getElementById('drawBtn'),
    discardDrawBtn: document.getElementById('discardDrawBtn'),
    attackBtn: document.getElementById('attackBtn'),
    endTurnBtn: document.getElementById('endTurnBtn'),
    rollBtn: document.getElementById('rollBtn'),
    dieOne: document.getElementById('dieOne'),
    dieTwo: document.getElementById('dieTwo')
};

async function request(path, options = {}) {
    const response = await fetch(`${API_BASE}${path}`, {
        headers: {
            'Content-Type': 'application/json',
            'X-User-Role': 'player'
        },
        ...options
    });

    let data = null;

    try {
        data = await response.json();
    } catch (error) {
        data = { success: false, message: 'Server response could not be read.' };
    }

    if (!response.ok) {
        const message = data.message || data.error || 'Request failed.';
        els.messageBox.textContent = message;
        throw new Error(message);
    }

    return data;
}

async function init() {
    const savedGameId = localStorage.getItem('hereToSlayGameId');

    if (savedGameId) {
        try {
            currentGame = await request(`/games/${savedGameId}`);
        } catch (error) {
            currentGame = null;
            localStorage.removeItem('hereToSlayGameId');
        }
    }

    if (!currentGame || !currentGame.id) {
        const games = await request('/games');
        currentGame = games.length > 0 ? games[0] : await createGame();
    }

    if (currentGame && currentGame.id) {
        localStorage.setItem('hereToSlayGameId', currentGame.id);
    }

    renderGame();
}

async function createGame() {
    const game = await request('/games', {
        method: 'POST',
        body: JSON.stringify({
            name: 'Table Game'
        })
    });

    localStorage.setItem('hereToSlayGameId', game.id);
    return game;
}

async function reloadGame() {
    if (!currentGame || !currentGame.id) {
        await init();
        return;
    }

    currentGame = await request(`/games/${currentGame.id}`);
    renderGame();
}

function renderGame() {
    if (!currentGame) {
        return;
    }

    const currentPlayer = getCurrentPlayer();
    const hasWinner = gameHasWinner();

    els.currentPlayer.textContent = currentPlayer ? currentPlayer.name : '-';
    els.actionPoints.textContent = currentGame.actionPoints;
    els.mainDeckCount.textContent = currentGame.mainDeck.length;
    els.mainDeckMiniCount.textContent = currentGame.mainDeck.length;
    els.monsterDeckCount.textContent = currentGame.monsterDeck.length;
    els.discardCount.textContent = currentGame.discardPile.length;
    els.messageBox.textContent = currentGame.lastMessage || 'Game loaded.';

    renderPlayers();
    renderMonsters();
    renderHand(currentPlayer);

    els.drawBtn.disabled = hasWinner || !currentPlayer || currentGame.actionPoints < 1;
    els.discardDrawBtn.disabled = hasWinner || !currentPlayer || currentGame.actionPoints < 3;
    els.attackBtn.disabled = hasWinner || !currentPlayer || !selectedMonsterId || currentGame.actionPoints < 2 || lastRoll === 0;
    els.rollBtn.disabled = hasWinner || !currentPlayer;
    els.endTurnBtn.disabled = hasWinner || !currentPlayer;
}

function renderPlayers() {
    const players = currentGame.players || [];

    els.playerBottom.innerHTML = players[0] ? playerZone(players[0], false) : '';
    els.playerLeft.innerHTML = players[1] ? playerZone(players[1], false) : '';
    els.playerTop.innerHTML = players[2] ? playerZone(players[2], false) : '';
    els.playerRight.innerHTML = players[3] ? playerZone(players[3], false) : '';
}

function playerZone(player, isMain) {
    const active = player.id === currentGame.currentTurnPlayerId ? 'Active' : 'Waiting';
    const partySlots = buildPartySlots(player, 4);

    return `
        <div class="player-title">
            <span>${escapeHtml(player.name)}</span>
            <span>${active}</span>
        </div>
        <div class="player-mini-data">
            <span class="badge">${escapeHtml(player.partyLeader)}</span>
            <span class="badge">${escapeHtml(player.partyLeaderClass)}</span>
            <span class="badge">Hand ${player.hand.length}</span>
            <span class="badge">Party ${player.party.length}</span>
            <span class="badge">Slain ${player.slainMonsters.length}</span>
        </div>
        <div class="party-grid">${partySlots}</div>
    `;
}

function buildPartySlots(player, count) {
    const cards = [];

    cards.push(`
        <div class="party-slot card hero ${escapeHtml(player.partyLeaderClass)}">
            <h3>${escapeHtml(player.partyLeader)}</h3>
            <p>Party Leader</p>
            <small>${escapeHtml(player.partyLeaderClass)}</small>
        </div>
    `);

    for (const card of player.party) {
        cards.push(`
            <div class="party-slot card ${escapeHtml(card.type || '')} ${escapeHtml(card.class || '')}">
                <h3>${escapeHtml(card.name)}</h3>
                <p>${escapeHtml(card.description || '')}</p>
                <small>${escapeHtml(card.type || '')}</small>
            </div>
        `);
    }

    for (const monster of player.slainMonsters) {
        cards.push(`
            <div class="party-slot monster-card">
                <h3>${escapeHtml(monster.name)}</h3>
                <p>${escapeHtml(monster.reward)}</p>
                <small>Slain</small>
            </div>
        `);
    }

    while (cards.length < count) {
        cards.push('<div class="party-slot"></div>');
    }

    return cards.slice(0, count).join('');
}

function renderMonsters() {
    els.activeMonsters.innerHTML = currentGame.activeMonsters.map(monster => `
        <article class="monster-card ${selectedMonsterId === monster.id ? 'selected' : ''}" data-monster-id="${escapeHtml(monster.id)}">
            <h3>${escapeHtml(monster.name)}</h3>
            <p>Roll ${monster.rollRequirement}+ to slay.</p>
            <p>Penalty: ${escapeHtml(monster.penalty)}</p>
            <p>Reward: ${escapeHtml(monster.reward)}</p>
            <small>Monster</small>
        </article>
    `).join('');

    document.querySelectorAll('.monster-card[data-monster-id]').forEach(card => {
        card.addEventListener('click', () => {
            selectedMonsterId = card.dataset.monsterId;
            renderGame();
        });
    });
}

function renderHand(player) {
    if (!player) {
        els.handOwner.textContent = '-';
        els.playerHand.innerHTML = '<p class="empty-text">No active player.</p>';
        return;
    }

    els.handOwner.textContent = `${player.name} hand`;

    if (player.hand.length === 0) {
        els.playerHand.innerHTML = '<p class="empty-text">No cards in hand.</p>';
        return;
    }

    els.playerHand.innerHTML = player.hand.map(card => `
        <article class="card ${escapeHtml(card.type)} ${escapeHtml(card.class || '')}">
            <h3>${escapeHtml(card.name)}</h3>
            <p>${escapeHtml(card.description)}</p>
            <small>${card.rollRequirement ? 'Roll ' + card.rollRequirement + '+' : escapeHtml(card.type)}</small>
            <span class="card-type">${escapeHtml(card.type)}</span>
            <div class="hand-card-actions">
                <button class="play-card-btn" data-card-id="${escapeHtml(card.id)}" ${currentGame.actionPoints < 1 || gameHasWinner() ? 'disabled' : ''}>Play - 1 AP</button>
            </div>
        </article>
    `).join('');

    document.querySelectorAll('.play-card-btn').forEach(button => {
        button.addEventListener('click', async event => {
            event.stopPropagation();
            await playCard(button.dataset.cardId);
        });
    });
}

function getCurrentPlayer() {
    if (!currentGame) {
        return null;
    }

    return currentGame.players.find(player => player.id === currentGame.currentTurnPlayerId) || null;
}

function gameHasWinner() {
    if (!currentGame || !currentGame.players) {
        return false;
    }

    return currentGame.players.some(player => {
        const heroCount = player.party.filter(card => card.type === 'hero').length;
        return player.slainMonsters.length >= 3 || heroCount >= 5;
    });
}

async function drawCard() {
    const currentPlayer = getCurrentPlayer();

    if (!currentPlayer) {
        return;
    }

    try {
        await request('/deck/draw', {
            method: 'POST',
            body: JSON.stringify({
                gameId: currentGame.id,
                playerId: currentPlayer.id
            })
        });

        await reloadGame();
    } catch (error) {}
}

async function playCard(cardId) {
    const currentPlayer = getCurrentPlayer();

    if (!currentPlayer || !cardId) {
        return;
    }

    try {
        const result = await request(`/games/${currentGame.id}/cards/play`, {
            method: 'POST',
            body: JSON.stringify({
                playerId: currentPlayer.id,
                cardId
            })
        });

        els.messageBox.textContent = result.message;
        await reloadGame();
    } catch (error) {}
}

async function discardAndDraw() {
    const currentPlayer = getCurrentPlayer();

    if (!currentPlayer) {
        return;
    }

    try {
        const result = await request(`/games/${currentGame.id}/discard-draw`, {
            method: 'POST',
            body: JSON.stringify({
                playerId: currentPlayer.id
            })
        });

        els.messageBox.textContent = result.message;
        await reloadGame();
    } catch (error) {}
}

async function attackMonster() {
    const currentPlayer = getCurrentPlayer();

    if (!currentPlayer || !selectedMonsterId || lastRoll === 0) {
        els.messageBox.textContent = 'Select a monster and roll the dice first.';
        return;
    }

    try {
        const result = await request(`/monsters/${selectedMonsterId}/attack`, {
            method: 'POST',
            body: JSON.stringify({
                playerId: currentPlayer.id,
                roll: lastRoll
            })
        });

        els.messageBox.textContent = result.message;
        selectedMonsterId = null;
        lastRoll = 0;
        els.dieOne.textContent = '?';
        els.dieTwo.textContent = '?';

        await reloadGame();
    } catch (error) {}
}

async function endTurn() {
    if (!currentGame) {
        return;
    }

    try {
        currentGame = await request(`/games/${currentGame.id}/turn/end`, {
            method: 'POST'
        });

        selectedMonsterId = null;
        lastRoll = 0;
        els.dieOne.textContent = '?';
        els.dieTwo.textContent = '?';

        renderGame();
    } catch (error) {}
}

function rollDice() {
    const d1 = Math.floor(Math.random() * 6) + 1;
    const d2 = Math.floor(Math.random() * 6) + 1;
    const currentPlayer = getCurrentPlayer();
    const bonus = currentPlayer ? calculateVisibleBonus(currentPlayer) : 0;

    lastRoll = d1 + d2;
    els.dieOne.textContent = d1;
    els.dieTwo.textContent = d2;
    els.messageBox.textContent = `You rolled ${lastRoll}. Current attack bonus from party: +${bonus}. Select a monster and attack.`;

    renderGame();
}

function calculateVisibleBonus(player) {
    let bonus = 0;

    for (const card of player.party) {
        if (card.type === 'hero') {
            bonus += 1;
        }

        if (card.type === 'item') {
            bonus += 1;
        }

        if (card.type === 'modifier') {
            bonus += card.name === 'Plus Two' ? 2 : 1;
        }
    }

    return bonus;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

els.newGameBtn.addEventListener('click', async () => {
    currentGame = await createGame();
    selectedMonsterId = null;
    lastRoll = 0;
    els.dieOne.textContent = '?';
    els.dieTwo.textContent = '?';
    renderGame();
});

els.refreshBtn.addEventListener('click', reloadGame);
els.drawBtn.addEventListener('click', drawCard);
els.discardDrawBtn.addEventListener('click', discardAndDraw);
els.attackBtn.addEventListener('click', attackMonster);
els.endTurnBtn.addEventListener('click', endTurn);
els.rollBtn.addEventListener('click', rollDice);

init();