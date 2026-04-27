const players = [
    {
        name: 'Player 1',
        leader: 'Blade Leader',
        className: 'fighter',
        hand: 5,
        slain: 1
    },
    {
        name: 'Player 2',
        leader: 'Shadow Leader',
        className: 'thief',
        hand: 4,
        slain: 0
    },
    {
        name: 'Player 3',
        leader: 'Mystic Leader',
        className: 'wizard',
        hand: 6,
        slain: 2
    },
    {
        name: 'Player 4',
        leader: 'Forest Leader',
        className: 'ranger',
        hand: 3,
        slain: 0
    }
];

const monsters = [
    {
        id: 'm1',
        name: 'Dark Bat',
        roll: 7,
        penalty: 'Sacrifice a hero.',
        reward: 'Draw two cards.'
    },
    {
        id: 'm2',
        name: 'Forest Beast',
        roll: 8,
        penalty: 'Discard two cards.',
        reward: 'Gain +1 on attacks.'
    },
    {
        id: 'm3',
        name: 'Crystal Dragon',
        roll: 9,
        penalty: 'Destroy one item.',
        reward: 'Draw one card each turn.'
    }
];

const hand = [
    {
        name: 'Brave Fighter',
        type: 'hero',
        className: 'fighter',
        description: 'Roll 5+ to draw a card.',
        roll: 5
    },
    {
        name: 'Shield Guardian',
        type: 'hero',
        className: 'guardian',
        description: 'Roll 6+ to protect a hero.',
        roll: 6
    },
    {
        name: 'Forest Ranger',
        type: 'hero',
        className: 'ranger',
        description: 'Roll 7+ to search the deck.',
        roll: 7
    },
    {
        name: 'Fire Spell',
        type: 'magic',
        className: 'magic',
        description: 'Use a one-time powerful spell.',
        roll: null
    },
    {
        name: 'Challenge',
        type: 'challenge',
        className: 'challenge',
        description: 'Try to stop another player from playing a card.',
        roll: null
    }
];

let selectedMonsterId = null;
let actionPoints = 3;

/**
 * Afiseaza jucatorii demo pe tabla statica de preview.
 * Datele sunt locale, deci functia nu face request-uri catre API.
 */
function renderPlayers() {
    // Fiecare zona primeste cate un jucator din array-ul demo.
    document.getElementById('playerBottom').innerHTML = renderPlayer(players[0], true);
    document.getElementById('playerLeft').innerHTML = renderPlayer(players[1], false);
    document.getElementById('playerTop').innerHTML = renderPlayer(players[2], false);
    document.getElementById('playerRight').innerHTML = renderPlayer(players[3], false);
}

/**
 * Creeaza HTML-ul pentru un jucator din preview.
 * Parametrul active decide daca afiseaza statusul Active sau Waiting.
 */
function renderPlayer(player, active) {
    // Intoarcem template HTML pentru un jucator.
    return `
        <div class="player-title">
            <span>${player.name}</span>
            <span>${active ? 'Active' : 'Waiting'}</span>
        </div>

        <div class="player-mini-data">
            <span class="badge">${player.leader}</span>
            <span class="badge">${player.className}</span>
            <span class="badge">Hand ${player.hand}</span>
            <span class="badge">Slain ${player.slain}</span>
        </div>

        <div class="party-grid">
            <div class="party-slot card hero ${player.className}">
                <h3>${player.leader}</h3>
                <p>Party Leader</p>
                <small>${player.className}</small>
            </div>
            <div class="party-slot"></div>
            <div class="party-slot"></div>
            <div class="party-slot"></div>
            <div class="party-slot"></div>
        </div>
    `;
}

/**
 * Afiseaza monstrii demo si permite selectarea vizuala a unuia dintre ei.
 */
function renderMonsters() {
    // Generam cate un card HTML pentru fiecare monstru demo.
    document.getElementById('activeMonsters').innerHTML = monsters.map(monster => `
        <article class="monster-card ${selectedMonsterId === monster.id ? 'selected' : ''}" data-id="${monster.id}">
            <h3>${monster.name}</h3>
            <p>Roll ${monster.roll}+ to slay.</p>
            <p>Penalty: ${monster.penalty}</p>
            <p>Reward: ${monster.reward}</p>
            <small>Monster</small>
        </article>
    `).join('');

    // Dupa randare, atasam click handler pe cardurile de monstru.
    document.querySelectorAll('.monster-card[data-id]').forEach(card => {
        card.addEventListener('click', () => {
            // Retinem monstrul selectat.
            selectedMonsterId = card.dataset.id;
            // Re-randam ca sa se vada selectia.
            renderMonsters();
            // Afisam mesaj explicativ pentru utilizator.
            document.getElementById('messageBox').textContent = 'Monster selected. Roll dice before attacking.';
        });
    });
}

/**
 * Afiseaza mana demo a jucatorului activ.
 */
function renderHand() {
    // Afisam cartile demo din mana.
    document.getElementById('playerHand').innerHTML = hand.map(card => `
        <article class="card ${card.type} ${card.className}">
            <h3>${card.name}</h3>
            <p>${card.description}</p>
            <small>${card.roll ? 'Roll ' + card.roll + '+' : card.type}</small>
            <span class="card-type">${card.type}</span>
        </article>
    `).join('');
}

/**
 * Simuleaza aruncarea a doua zaruri si afiseaza totalul.
 */
function rollDice() {
    // Genereaza doua valori intre 1 si 6.
    const d1 = Math.floor(Math.random() * 6) + 1;
    const d2 = Math.floor(Math.random() * 6) + 1;

    // Afiseaza valorile zarurilor.
    document.getElementById('dieOne').textContent = d1;
    document.getElementById('dieTwo').textContent = d2;
    // Afiseaza suma.
    document.getElementById('messageBox').textContent = `You rolled ${d1 + d2}.`;
}

/**
 * Simuleaza actiunea de draw in preview.
 * Scade un punct de actiune si afiseaza un mesaj local.
 */
function drawCard() {
    if (actionPoints <= 0) {
        // Nu permitem actiunea daca nu mai exista puncte de actiune.
        document.getElementById('messageBox').textContent = 'No action points left.';
        return;
    }

    // Draw costa 1 action point in preview.
    actionPoints--;
    // Actualizam contorul vizual.
    document.getElementById('actionPoints').textContent = actionPoints;
    // Mesaj local, fara backend.
    document.getElementById('messageBox').textContent = 'Preview action: card drawn.';
}

/**
 * Simuleaza atacul asupra monstrului selectat.
 * Verifica selectia si numarul de action points inainte de a scadea costul.
 */
function attackMonster() {
    if (!selectedMonsterId) {
        // Trebuie ales un monstru inainte de atac.
        document.getElementById('messageBox').textContent = 'Select a monster first.';
        return;
    }

    if (actionPoints < 2) {
        // Atacul costa 2 action points.
        document.getElementById('messageBox').textContent = 'Not enough action points.';
        return;
    }

    // Scadem costul atacului.
    actionPoints -= 2;
    // Actualizam UI-ul.
    document.getElementById('actionPoints').textContent = actionPoints;
    document.getElementById('messageBox').textContent = 'Preview action: monster attacked.';
}

/**
 * Reseteaza tura demo: action points revin la 3, zarurile si selectia sunt curatate.
 */
function endTurn() {
    // Resetam AP-ul pentru urmatoarea tura demo.
    actionPoints = 3;
    // Curatam selectia monstrului.
    selectedMonsterId = null;

    // Resetam elementele vizuale.
    document.getElementById('actionPoints').textContent = actionPoints;
    document.getElementById('dieOne').textContent = '?';
    document.getElementById('dieTwo').textContent = '?';
    document.getElementById('messageBox').textContent = 'Preview action: turn ended.';
    // Re-randam monstrii ca sa dispara starea selected.
    renderMonsters();
}

document.getElementById('rollBtn').addEventListener('click', rollDice);
document.getElementById('drawBtn').addEventListener('click', drawCard);
document.getElementById('attackBtn').addEventListener('click', attackMonster);
document.getElementById('endTurnBtn').addEventListener('click', endTurn);
document.getElementById('newGameBtn').addEventListener('click', endTurn);
document.getElementById('refreshBtn').addEventListener('click', endTurn);

renderPlayers();
renderMonsters();
renderHand();
