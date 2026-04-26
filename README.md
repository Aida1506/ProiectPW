# Here to Slay API

A REST API for the Here to Slay board game built with Slim Framework and database abstraction.

## Features

- Database abstraction with support for MySQL/SQLite
- ACL (Access Control List) system for role-based permissions
- Complete game logic implementation
- Tutorial system with step-by-step guidance
- PSR-4 compliant PHP code
- RESTful API endpoints

## Requirements

- PHP 8.0+
- Composer
- MySQL or SQLite

## Installation

1. Clone the repository to your XAMPP htdocs folder
2. Run `composer install` to install dependencies
3. Run `php setup_database.php` to initialize the database
4. Access the API at `http://localhost/proiectpw/`

## Database Setup

The application automatically detects and configures the database:

- **MySQL**: Uses XAMPP's MySQL with default settings (localhost:3306, user: root, no password)
- **SQLite**: Falls back to SQLite if MySQL is not available

Database configuration is saved in `config/database.php`.

## API Endpoints

### Games
- `GET /games` - List all games
- `POST /games` - Create new game
- `GET /games/{gameId}` - Get game details
- `POST /games/{gameId}/turn/end` - End current turn

### Game Actions
- `POST /deck/draw` - Draw a card
- `POST /games/{gameId}/cards/play` - Play a card
- `POST /games/{gameId}/heroes/{heroId}/roll` - Roll dice for hero
- `POST /games/{gameId}/modifiers/use` - Use modifier card
- `POST /games/{gameId}/challenges` - Challenge a card play
- `POST /games/{gameId}/discard-draw` - Discard hand and draw 5 new cards
- `POST /monsters/{monsterId}/attack` - Attack a monster

### Static Data
- `GET /players` - Get all players
- `GET /cards` - Get all cards
- `GET /monsters` - Get all monsters

### Tutorial
- `GET /tutorial` - Get all tutorial steps
- `GET /tutorial/step/{step}` - Get specific tutorial step

## ACL Permissions

- **Guest**: View games, players, cards, monsters
- **Player**: Create/join games, play cards, draw cards, attack monsters
- **Admin**: All permissions

Pass role in query parameter `?role=player` or header `X-User-Role: player`.

## Game Rules Implementation

The API implements the complete "Here to Slay" game rules:

- Turn-based gameplay with action points
- Card drawing and playing
- Hero abilities with dice rolling
- Monster attacks with success/failure
- Modifiers and challenges
- Party leaders and classes
- Win conditions (3 monsters slain or 6 different classes)

## Development

The project follows PSR-4 standards and uses:
- Slim Framework for routing
- Doctrine DBAL for database abstraction
- Zend Permissions ACL for access control
- JSON storage for game state

## Testing

Start XAMPP, ensure MySQL is running, then access the endpoints via HTTP requests or the web interface.