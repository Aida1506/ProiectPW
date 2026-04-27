# Documentatie Proiect - Here to Slay API

## 1. Descriere

Proiectul implementeaza o aplicatie web si un API REST pentru un joc inspirat de "Here to Slay". Aplicatia permite crearea unei sesiuni de joc, gestionarea jucatorilor, cartilor, monstrilor si executarea actiunilor principale din joc.

Interfata web este servita din folderul `public`, iar logica de backend este organizata in `src`.

## 2. Tehnologii folosite

- PHP 8
- Slim Framework 4 pentru routing
- Doctrine DBAL pentru acces la baza de date
- Laminas ACL pentru roluri si permisiuni
- SQLite pentru baza de date locala
- OpenAPI 3.0 pentru documentarea endpoint-urilor

## 3. Structura proiectului

```text
config/                 configurare baza de date
public/                 punctul de intrare web si asset-uri frontend
src/Acl/                configurare roluri si permisiuni
src/Database/           conectare si abstractizare baza de date
src/Middleware/         middleware pentru ACL
src/Repository/         operatii de citire/scriere in baza de date
src/Service/            logica jocului
templates/              pagina principala a jocului
storage/                fisiere locale generate, inclusiv SQLite
openapi.yaml            specificatie API
setup_database.php      script initializare baza de date
```

## 4. Rulare locala

Din folderul proiectului:

```powershell
cd C:\xampp\htdocs\proiectpw
php setup_database.php
php -S localhost:8000 -t public
```

Aplicatia se deschide la:

```text
http://localhost:8000
```

Daca portul 8000 este ocupat:

```powershell
php -S localhost:8101 -t public
```

## 5. Baza de date

Configurarea se afla in:

```text
config/database.php
```

In configuratia curenta se foloseste SQLite:

```text
storage/database.sqlite
```

Scriptul `setup_database.php` initializeaza tabelele si datele necesare.

## 6. Roluri si permisiuni

API-ul foloseste ACL cu trei roluri:

- `guest`: poate vedea jocuri, jucatori, carti si monstri
- `player`: poate crea jocuri si poate executa actiuni de joc
- `admin`: poate face orice actiune, inclusiv stergeri

Rolul poate fi trimis prin query parameter:

```text
http://localhost:8000/games?role=player
```

sau prin header:

```text
X-User-Role: player
```

## 7. Endpoint-uri importante

### Jocuri

```text
GET    /games
POST   /games
GET    /games/{gameId}
PUT    /games/{gameId}
PATCH  /games/{gameId}
DELETE /games/{gameId}
POST   /games/{gameId}/turn/end
```

### Jucatori

```text
GET   /players
PUT   /players/{playerId}
PATCH /players/{playerId}
```

### Carti si actiuni

```text
GET    /cards
POST   /deck/draw
POST   /games/{gameId}/cards/play
DELETE /games/{gameId}/players/{playerId}/hand/{cardId}
DELETE /games/{gameId}/players/{playerId}/party/{cardId}
```

### Monstri

```text
GET    /monsters
POST   /monsters/{monsterId}/attack
DELETE /games/{gameId}/active-monsters/{monsterId}
```

## 8. Exemple request

### Creare joc

```powershell
Invoke-RestMethod -Uri "http://localhost:8000/games?role=player" -Method Post -ContentType "application/json" -Body '{"name":"Game 1"}'
```

Body JSON:

```json
{
  "name": "Game 1"
}
```

### Actualizare joc

```http
PATCH /games/{gameId}?role=player
Content-Type: application/json
```

```json
{
  "name": "Game updated",
  "actionPoints": 2
}
```

### Actualizare jucator

```http
PATCH /players/{playerId}?role=player
Content-Type: application/json
```

```json
{
  "name": "Alex",
  "partyLeader": "Blade Leader",
  "partyLeaderClass": "fighter"
}
```

### Tragere carte

```http
POST /deck/draw?role=player
Content-Type: application/json
```

```json
{
  "gameId": "game_...",
  "playerId": "game_..._p1"
}
```

### Joc carte

```http
POST /games/{gameId}/cards/play?role=player
Content-Type: application/json
```

```json
{
  "playerId": "game_..._p1",
  "cardId": "c1_copy_1"
}
```

### Stergere joc

```http
DELETE /games/{gameId}?role=admin
```

## 9. Reguli implementate

- fiecare joc are 4 jucatori
- fiecare jucator primeste carti la crearea jocului
- jucatorii au puncte de actiune
- se pot trage si juca carti
- se pot ataca monstri cu zaruri
- monstrii invinsi intra la `slainMonsters`
- jocul verifica mesaje de castig
- tura poate fi schimbata intre jucatori

## 10. Observatii pentru prezentare

- Pornirea recomandata este cu serverul PHP built-in.
- MySQL din XAMPP nu este obligatoriu daca proiectul foloseste SQLite.
- Specificatia API completa este in `openapi.yaml`.
- Pentru metode care modifica date, foloseste `?role=player` sau `?role=admin`.
