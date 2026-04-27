const API_BASE = document.querySelector('meta[name="api-base"]').content;

// Starea jocului incarcata din backend; toate randarile pornesc de aici.
let currentGame = null;
// Id-ul monstrului selectat de utilizator pentru atac.
let selectedMonsterId = null;
// Ultima suma obtinuta din cele doua zaruri; este trimisa la endpoint-ul de atac.
let lastRoll = 0;

const els = { //elemente importante
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

/**
 * Trimite request-uri catre backend si intoarce raspunsul JSON.
 * Adauga automat headerul de JSON si rolul player, apoi trateaza erorile HTTP.
 */
async function request(path, options = {}) { //functia generala pentru requesturi catre backend
    // fetch construieste cererea HTTP catre API folosind baza calculata din meta tag.
    const response = await fetch(`${API_BASE}${path}`, { //trimite requestul catre backend
        headers: {
            'Content-Type': 'application/json', //date trimise in format json
            'X-User-Role': 'player' //trimite catre backend rolul utilizatorului
        },
        ...options //te lasa sa adaugi metoda body etc
    });

    // Variabila ramane null pana cand raspunsul serverului este convertit din JSON.
    let data = null;

    try { //incearca sa transforme raspunsul de la server in obiect js
        data = await response.json();
    } catch (error) {
        // Daca serverul trimite raspuns invalid, cream un obiect standard de eroare.
        data = { success: false, message: 'Server response could not be read.' };
    }

    if (!response.ok) { //afiseaza mesajele de eroare
        // Cauta mesajul in formatele folosite de backend: message sau error.
        const message = data.message || data.error || 'Request failed.';
        // Afiseaza mesajul in interfata, ca utilizatorul sa vada ce nu a mers.
        els.messageBox.textContent = message;
        // Arunca eroarea ca functia apelanta sa poata opri fluxul curent.
        throw new Error(message);
    }

    // Pentru raspunsuri reusite, intoarce corpul JSON deja parsat.
    return data;
}

/**
 * Porneste aplicatia in browser.
 * Incearca sa refoloseasca jocul salvat in localStorage, altfel incarca primul joc existent sau creeaza unul nou.
 */
async function init() { //proneste jocul cand se incarca pagina
    // Citeste din browser id-ul ultimului joc folosit.
    const savedGameId = localStorage.getItem('hereToSlayGameId');

    if (savedGameId) {
        try {
            // Daca exista un id salvat, incearca sa incarce acel joc din API.
            currentGame = await request(`/games/${savedGameId}`); //incarca jocul salvat daca exista
        } catch (error) {
            // Daca jocul salvat nu mai exista, curata starea locala.
            currentGame = null;
            localStorage.removeItem('hereToSlayGameId');
        }
    }

    if (!currentGame || !currentGame.id) { //cere toate jocurile din backend si daca exista cel putin un joc il ia pe primul
        // Daca nu avem joc valid, cerem lista de jocuri existente.
        const games = await request('/games');
        // Folosim primul joc gasit; daca lista e goala, cream unul nou.
        currentGame = games.length > 0 ? games[0] : await createGame();
    }

    if (currentGame && currentGame.id) {
        // Persistam id-ul ca refresh-ul paginii sa revina la acelasi joc.
        localStorage.setItem('hereToSlayGameId', currentGame.id); //salveaza id ul jocului in browser
    }

    // Dupa ce exista o stare valida, desenam interfata.
    renderGame();
}

/**
 * Creeaza un joc nou prin POST /games si salveaza id-ul local.
 */
async function createGame() { //creaza un joc nou
    // Trimite catre backend numele jocului nou.
    const game = await request('/games', {
        method: 'POST', //trimite la backend POST /games
        body: JSON.stringify({
            name: 'Table Game'
        })
    });

    // Salvam local id-ul primit de la backend.
    localStorage.setItem('hereToSlayGameId', game.id); //id ul jocului e salvat in browser
    // Returnam jocul pentru ca init sau click handler-ul sa il puna in currentGame.
    return game;
}

/**
 * Reincarca starea jocului curent din backend si redeseneaza interfata.
 */
async function reloadGame() { //reincarca jocul curent din backend
    if (!currentGame || !currentGame.id) {
        // Daca nu exista joc curent, repornim fluxul de initializare.
        await init();
        return;
    }

    // Cere backend-ului cea mai noua stare a jocului curent.
    currentGame = await request(`/games/${currentGame.id}`); //daca exista jocul curent face GET /games/{id}
    // Dupa refresh de date, redeseneaza tot ce se vede.
    renderGame();
}

/**
 * Randarea principala a paginii.
 * Actualizeaza contoarele, mesajul, zonele jucatorilor, monstrii, mana si starea butoanelor.
 */
function renderGame() { //functia de afisare
    if (!currentGame) { //verifica daca exista joc
        // Daca nu avem date, nu incercam sa accesam proprietati inexistente.
        return;
    }

    const currentPlayer = getCurrentPlayer(); //gaseste jocatorul activ
    const hasWinner = gameHasWinner(); //verifica daca exista castigator

    //actualizeaza informatiile din partea de sus a paginii 
    // Numarul de puncte de actiune ramase pentru tura curenta.
    els.actionPoints.textContent = currentGame.actionPoints;
    // Numarul de carti ramase in deck-ul principal, afisat in doua locuri.
    els.mainDeckCount.textContent = currentGame.mainDeck.length;
    els.mainDeckMiniCount.textContent = currentGame.mainDeck.length;
    // Numarul de monstri ramasi in deck-ul de monstri.
    els.monsterDeckCount.textContent = currentGame.monsterDeck.length;
    // Numarul de carti din discard pile.
    els.discardCount.textContent = currentGame.discardPile.length;
    // Mesajul de stare vine din backend dupa ultima actiune.
    els.messageBox.textContent = currentGame.lastMessage || 'Game loaded.';

    // Randam zonele mari ale interfetei in ordine.
    renderPlayers();
    renderMonsters();
    renderHand(currentPlayer);

    // Butonul Draw se dezactiveaza daca jocul s-a terminat, nu exista jucator sau nu exista AP.
    els.drawBtn.disabled = hasWinner || !currentPlayer || currentGame.actionPoints < 1;
    // Discard + Draw costa 3 AP, deci cere toate punctele turei.
    els.discardDrawBtn.disabled = hasWinner || !currentPlayer || currentGame.actionPoints < 3;
    // Attack cere monstru selectat, zar aruncat si cel putin 2 AP.
    els.attackBtn.disabled = hasWinner || !currentPlayer || !selectedMonsterId || currentGame.actionPoints < 2 || lastRoll === 0;
    // Roll si End Turn se dezactiveaza cand jocul are castigator sau nu exista jucator curent.
    els.rollBtn.disabled = hasWinner || !currentPlayer;
    els.endTurnBtn.disabled = hasWinner || !currentPlayer;
}

/**
 * Pune cei patru jucatori in pozitiile tablei: jos, stanga, sus si dreapta.
 */
function renderPlayers() {
    // Luam lista de jucatori sau lista goala ca fallback.
    const players = currentGame.players || [];

    // Fiecare jucator este pus intr-o zona fixa a tablei.
    els.playerBottom.innerHTML = players[0] ? playerZone(players[0], false) : '';
    els.playerLeft.innerHTML = players[1] ? playerZone(players[1], false) : '';
    els.playerTop.innerHTML = players[2] ? playerZone(players[2], false) : '';
    els.playerRight.innerHTML = players[3] ? playerZone(players[3], false) : '';
}

/**
 * Creeaza HTML-ul pentru zona unui jucator.
 * Include statusul turei, datele rapide si sloturile de party.
 */
function playerZone(player, isMain) {
    // Daca id-ul jucatorului coincide cu tura curenta, afisam Active.
    const active = player.id === currentGame.currentTurnPlayerId ? 'Active' : 'Waiting';
    // Construim sloturile de party separat ca functia sa ramana mai usor de citit.
    const partySlots = buildPartySlots(player, 4);

    // Template-ul contine datele jucatorului si zona lui de party.
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

/**
 * Construieste sloturile vizuale pentru party leader, cartile jucate si monstrii invinsi.
 * Completeaza cu sloturi goale ca layout-ul sa ramana stabil.
 */
function buildPartySlots(player, count) {
    // Lista finala de sloturi HTML care va fi lipita in zona de party.
    const cards = [];

    // Primul slot este mereu party leader-ul jucatorului.
    cards.push(`
        <div class="party-slot card hero ${escapeHtml(player.partyLeaderClass)}">
            <h3>${escapeHtml(player.partyLeader)}</h3>
            <p>Party Leader</p>
            <small>${escapeHtml(player.partyLeaderClass)}</small>
        </div>
    `);

    // Adaugam cartile jucate in party: eroi, iteme, modifier etc.
    for (const card of player.party) {
        cards.push(`
            <div class="party-slot card ${escapeHtml(card.type || '')} ${escapeHtml(card.class || '')}">
                <h3>${escapeHtml(card.name)}</h3>
                <p>${escapeHtml(card.description || '')}</p>
                <small>${escapeHtml(card.type || '')}</small>
            </div>
        `);
    }

    // Monstrii invinsi sunt afisati tot in zona jucatorului ca progres spre victorie.
    for (const monster of player.slainMonsters) {
        cards.push(`
            <div class="party-slot monster-card">
                <h3>${escapeHtml(monster.name)}</h3>
                <p>${escapeHtml(monster.reward)}</p>
                <small>Slain</small>
            </div>
        `);
    }

    // Completam cu sloturi goale ca zona sa aiba dimensiune constanta.
    while (cards.length < count) {
        cards.push('<div class="party-slot"></div>');
    }

    // Limitam numarul vizibil de sloturi si le unim intr-un singur string HTML.
    return cards.slice(0, count).join('');
}

/**
 * Afiseaza monstrii activi si le ataseaza click handler pentru selectie.
 */
function renderMonsters() {
    // Construim cardurile HTML pentru fiecare monstru activ.
    els.activeMonsters.innerHTML = currentGame.activeMonsters.map(monster => `
        <article class="monster-card ${selectedMonsterId === monster.id ? 'selected' : ''}" data-monster-id="${escapeHtml(monster.id)}">
            <h3>${escapeHtml(monster.name)}</h3>
            <p>Roll ${monster.rollRequirement}+ to slay.</p>
            <p>Penalty: ${escapeHtml(monster.penalty)}</p>
            <p>Reward: ${escapeHtml(monster.reward)}</p>
            <small>Monster</small>
        </article>
    `).join('');

    // Dupa ce HTML-ul a fost pus in pagina, atasam evenimente click pe fiecare monstru.
    document.querySelectorAll('.monster-card[data-monster-id]').forEach(card => {
        card.addEventListener('click', () => {
            // Retinem id-ul monstrului ales pentru atac.
            selectedMonsterId = card.dataset.monsterId;
            // Re-randam ca sa se vada clasa selected pe cardul ales.
            renderGame();
        });
    });
}

/**
 * Afiseaza mana jucatorului curent.
 * Pentru fiecare carte adauga butonul Play care apeleaza endpoint-ul de joc carte.
 */
function renderHand(player) {
    if (!player) {
        // Fallback pentru situatia rara in care nu exista current player.
        els.handOwner.textContent = '-';
        els.playerHand.innerHTML = '<p class="empty-text">No active player.</p>';
        return;
    }

    // Titlul zonei de mana arata carui jucator ii apartin cartile.
    els.handOwner.textContent = `${player.name} hand`;

    if (player.hand.length === 0) {
        // Mesaj simplu cand jucatorul nu are carti.
        els.playerHand.innerHTML = '<p class="empty-text">No cards in hand.</p>';
        return;
    }

    // Construim cate un card HTML pentru fiecare carte din mana.
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

    // Atasam click handler pe fiecare buton Play generat dinamic.
    document.querySelectorAll('.play-card-btn').forEach(button => {
        button.addEventListener('click', async event => {
            // Oprim propagarea ca click-ul pe buton sa nu declanseze alte actiuni pe card.
            event.stopPropagation();
            // Trimitem catre backend id-ul cartii alese.
            await playCard(button.dataset.cardId);
        });
    });
}

/**
 * Gaseste jucatorul care are tura curenta.
 */
function getCurrentPlayer() {
    if (!currentGame) {
        // Fara joc incarcat nu exista jucator curent.
        return null;
    }

    // Cautam in lista de jucatori id-ul salvat de backend ca tura activa.
    return currentGame.players.find(player => player.id === currentGame.currentTurnPlayerId) || null;
}

/**
 * Verifica local daca exista castigator.
 * Conditiile sunt 3 monstri invinsi sau 5 eroi in party in versiunea simplificata.
 */
function gameHasWinner() {
    if (!currentGame || !currentGame.players) {
        // Daca nu avem date complete, presupunem ca nu exista castigator.
        return false;
    }

    // some intoarce true imediat ce gaseste un jucator care indeplineste conditia.
    return currentGame.players.some(player => {
        // Numarul de eroi din party este una dintre conditiile de victorie.
        const heroCount = player.party.filter(card => card.type === 'hero').length;
        // In varianta simplificata: 3 monstri invinsi sau 5 eroi in party.
        return player.slainMonsters.length >= 3 || heroCount >= 5;
    });
}

/**
 * Apeleaza backend-ul pentru tragerea unei carti si reincarca starea jocului.
 */
async function drawCard() {
    // Identificam jucatorul activ inainte de request.
    const currentPlayer = getCurrentPlayer();

    if (!currentPlayer) {
        // Daca nu exista jucator activ, actiunea nu are sens.
        return;
    }

    try {
        // Cerem backend-ului sa traga o carte si sa modifice starea jocului.
        await request('/deck/draw', {
            method: 'POST',
            body: JSON.stringify({
                gameId: currentGame.id,
                playerId: currentPlayer.id
            })
        });

        // Dupa actiune, reincarcam jocul ca UI-ul sa reflecte noua mana si AP-ul scazut.
        await reloadGame();
    } catch (error) {}
}

/**
 * Trimite catre backend id-ul cartii care trebuie jucata din mana.
 */
async function playCard(cardId) {
    // Luam jucatorul activ pentru a trimite playerId catre backend.
    const currentPlayer = getCurrentPlayer();

    if (!currentPlayer || !cardId) {
        // Fara jucator sau fara card selectat, iesim fara request.
        return;
    }

    try {
        // Endpoint-ul primeste cardId si decide efectul in functie de tipul cartii.
        const result = await request(`/games/${currentGame.id}/cards/play`, {
            method: 'POST',
            body: JSON.stringify({
                playerId: currentPlayer.id,
                cardId
            })
        });

        // Afisam mesajul intors de backend, de exemplu ce carte a fost jucata.
        els.messageBox.textContent = result.message;
        // Reincarcam pentru mana, party, discard si actionPoints actualizate.
        await reloadGame();
    } catch (error) {}
}

/**
 * Cere backend-ului sa arunce mana jucatorului si sa traga carti noi.
 */
async function discardAndDraw() {
    // Actiunea se aplica doar jucatorului care are tura.
    const currentPlayer = getCurrentPlayer();

    if (!currentPlayer) {
        // Protectie pentru stare incompleta.
        return;
    }

    try {
        // Backend-ul muta mana in discard si trage pana la 5 carti noi.
        const result = await request(`/games/${currentGame.id}/discard-draw`, {
            method: 'POST',
            body: JSON.stringify({
                playerId: currentPlayer.id
            })
        });

        // Mesajul include cate carti au fost aruncate si cate au fost trase.
        els.messageBox.textContent = result.message;
        // Refresh complet pentru noua mana si AP ramas.
        await reloadGame();
    } catch (error) {}
}

/**
 * Ataca monstrul selectat folosind ultimul rezultat de zar.
 * Dupa atac reseteaza selectia si zarurile, apoi reincarca jocul.
 */
async function attackMonster() {
    // Identificam jucatorul activ inainte de atac.
    const currentPlayer = getCurrentPlayer();

    if (!currentPlayer || !selectedMonsterId || lastRoll === 0) {
        // Atacul are nevoie de jucator, monstru selectat si zar aruncat.
        els.messageBox.textContent = 'Select a monster and roll the dice first.';
        return;
    }

    try {
        // Trimitem monstrul ales, jucatorul si suma zarurilor catre backend.
        const result = await request(`/monsters/${selectedMonsterId}/attack`, {
            method: 'POST',
            body: JSON.stringify({
                playerId: currentPlayer.id,
                roll: lastRoll
            })
        });

        // Afisam rezultatul atacului: succes, esec, recompensa sau penalizare.
        els.messageBox.textContent = result.message;
        // Dupa atac curatam selectia ca utilizatorul sa aleaga din nou explicit.
        selectedMonsterId = null;
        lastRoll = 0;
        // Resetam vizual zarurile.
        els.dieOne.textContent = '?';
        els.dieTwo.textContent = '?';

        // Starea jocului se reincarca deoarece backend-ul a mutat carti/monstri/AP.
        await reloadGame();
    } catch (error) {}
}

/**
 * Incheie tura curenta si actualizeaza interfata cu urmatorul jucator.
 */
async function endTurn() {
    if (!currentGame) {
        // Nu putem termina tura fara joc incarcat.
        return;
    }

    try {
        // Backend-ul calculeaza urmatorul jucator si reseteaza AP-ul la 3.
        currentGame = await request(`/games/${currentGame.id}/turn/end`, {
            method: 'POST'
        });

        // Curatam actiunile temporare ale turei vechi.
        selectedMonsterId = null;
        lastRoll = 0;
        els.dieOne.textContent = '?';
        els.dieTwo.textContent = '?';

        // Randam direct jocul primit de la endpoint.
        renderGame();
    } catch (error) {}
}

/**
 * Simuleaza aruncarea a doua zaruri in frontend.
 * Rezultatul este pastrat in lastRoll si apoi trimis la atac.
 */
function rollDice() {
    // Genereaza primul zar, valoare intre 1 si 6.
    const d1 = Math.floor(Math.random() * 6) + 1;
    // Genereaza al doilea zar, valoare intre 1 si 6.
    const d2 = Math.floor(Math.random() * 6) + 1;
    // Bonusul este afisat informativ pentru jucatorul curent.
    const currentPlayer = getCurrentPlayer();
    const bonus = currentPlayer ? calculateVisibleBonus(currentPlayer) : 0;

    // Salvam suma zarurilor pentru viitorul atac.
    lastRoll = d1 + d2;
    // Afisam fiecare zar separat in UI.
    els.dieOne.textContent = d1;
    els.dieTwo.textContent = d2;
    // Mesajul explica urmatorul pas: selectare monstru si atac.
    els.messageBox.textContent = `You rolled ${lastRoll}. Current attack bonus from party: +${bonus}. Select a monster and attack.`;

    // Re-randam pentru ca butonul Attack poate deveni activ dupa roll.
    renderGame();
}

/**
 * Calculeaza bonusul vizibil din party-ul jucatorului.
 * Aceasta valoare este afisata informativ; backend-ul recalculeaza bonusul real.
 */
function calculateVisibleBonus(player) {
    // Pornim de la bonus zero si adunam efectele cartilor din party.
    let bonus = 0;

    for (const card of player.party) {
        if (card.type === 'hero') {
            // Fiecare erou contribuie cu +1 in varianta simplificata.
            bonus += 1;
        }

        if (card.type === 'item') {
            // Itemele din party adauga +1 la atac.
            bonus += 1;
        }

        if (card.type === 'modifier') {
            // Plus Two este singurul modifier cu +2; restul dau +1 aici.
            bonus += card.name === 'Plus Two' ? 2 : 1;
        }
    }

    // Intoarcem valoarea finala folosita doar pentru afisare.
    return bonus;
}

/**
 * Escapare HTML pentru valorile venite din backend.
 * Previne injectarea de markup cand datele sunt puse in template strings.
 */
function escapeHtml(value) {
    // Convertim orice valoare in string, iar null/undefined devin string gol.
    return String(value ?? '')
        // Inlocuieste caracterele care pot crea HTML sau script injectat.
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

els.newGameBtn.addEventListener('click', async () => {
    // Creeaza joc nou si il seteaza ca joc activ.
    currentGame = await createGame();
    // Resetam selectia si zarurile pentru noua sesiune.
    selectedMonsterId = null;
    lastRoll = 0;
    els.dieOne.textContent = '?';
    els.dieTwo.textContent = '?';
    // Afisam imediat jocul nou creat.
    renderGame();
});

// Legam butoanele din HTML de functiile care executa actiunile.
els.refreshBtn.addEventListener('click', reloadGame);
els.drawBtn.addEventListener('click', drawCard);
els.discardDrawBtn.addEventListener('click', discardAndDraw);
els.attackBtn.addEventListener('click', attackMonster);
els.endTurnBtn.addEventListener('click', endTurn);
els.rollBtn.addEventListener('click', rollDice);

// Porneste aplicatia dupa incarcarea scriptului.
init();
