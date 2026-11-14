<?php

declare(strict_types=1);

namespace Bga\Games\bigtop\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\bigtop\Game;

class NextPlayer extends GameState
{
    public function __construct(protected Game $game)
    {
        parent::__construct($game, id: 90, type: StateType::GAME);
    }

    public function onEnteringState(): string
    {
        $auctioneerId = (int)$this->globals->get('auctioneer_id');
        $this->game->changeActivePlayer($auctioneerId);
        $this->game->activeNextPlayer();

        return StartAuction::class;
    }
}
