<?php

namespace App\Service;

class TutorialService
{
    /**
     * Returneaza lista completa de pasi ai tutorialului.
     * Fiecare pas are numar, titlu, descriere si un cod de actiune folosit de frontend sau API.
     */
    public function getTutorialSteps(): array
    {
        return [
            [
                'step' => 1,
                'title' => 'Game Setup',
                'description' => 'Each player chooses a Party Leader. The leader goes in the Party zone. Shuffle the main deck and deal 5 cards to each player. Shuffle monster deck and place 3 monsters face up.',
                'action' => 'setup'
            ],
            [
                'step' => 2,
                'title' => 'Turn Structure',
                'description' => 'Players take turns clockwise. Each turn has 3 action points. Actions cost: DRAW (1), PLAY CARD (1), ATTACK MONSTER (2), DISCARD+DRAW 5 (3).',
                'action' => 'turn_start'
            ],
            [
                'step' => 3,
                'title' => 'Drawing Cards',
                'description' => 'Click DRAW to take a card from the main deck to your hand. Costs 1 action point.',
                'action' => 'draw_card'
            ],
            [
                'step' => 4,
                'title' => 'Playing Heroes',
                'description' => 'Play a Hero card from your hand to your Party. Heroes have abilities that activate when you roll their required number or higher.',
                'action' => 'play_hero'
            ],
            [
                'step' => 5,
                'title' => 'Rolling Dice',
                'description' => 'Use the ROLL DICE action to activate a hero ability. Roll 2d6 and add modifiers. Meet or exceed the requirement to succeed.',
                'action' => 'roll_dice'
            ],
            [
                'step' => 6,
                'title' => 'Attacking Monsters',
                'description' => 'Choose a monster and click ATTACK. Costs 2 action points. Roll dice to try to slay it. Success adds the monster to your Party.',
                'action' => 'attack_monster'
            ],
            [
                'step' => 7,
                'title' => 'Using Modifiers',
                'description' => 'Play modifier cards after rolling to adjust your dice result. +2 or -2 to any roll.',
                'action' => 'use_modifier'
            ],
            [
                'step' => 8,
                'title' => 'Challenges',
                'description' => 'When a player plays a card, you can challenge it. Both roll dice - higher roll wins. Loser discards the card.',
                'action' => 'challenge'
            ],
            [
                'step' => 9,
                'title' => 'End Turn',
                'description' => 'When done with actions or out of points, end your turn. Action points reset to 3 for the next player.',
                'action' => 'end_turn'
            ],
            [
                'step' => 10,
                'title' => 'Winning',
                'description' => 'Win by slaying 3 monsters OR having 6 heroes of 6 different classes in your Party (including leader).',
                'action' => 'check_win'
            ]
        ];
    }

    /**
     * Returneaza urmatorul pas pe baza indexului zero-based primit.
     * Ruta publica converteste numarul pasului din URL in acest index.
     */
    public function getNextStep(int $currentStep): ?array
    {
        $steps = $this->getTutorialSteps();
        return $steps[$currentStep] ?? null;
    }

    /**
     * Cauta pasul de tutorial asociat unei actiuni.
     * Este util cand interfata vrea sa afiseze ajutor pentru o actiune concreta.
     */
    public function getStepByAction(string $action): ?array
    {
        foreach ($this->getTutorialSteps() as $step) {
            if ($step['action'] === $action) {
                return $step;
            }
        }
        return null;
    }
}
