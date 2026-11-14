<?php

declare(strict_types=1);

namespace Bga\Games\bigtop\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\bigtop\Game;

class NextBid extends GameState
{
    public function __construct(protected Game $game)
    {
        parent::__construct($game, id: 40, type: StateType::GAME);
    }

    public function onEnteringState(): string
    {
        $playerIds = array_map('intval', array_keys($this->game->loadPlayersBasicInfos()));
        $passedPlayers = array_map('intval', $this->globals->get('passed_players', []));
        $highBidder = $this->globals->get('high_bidder');
        $highBidder = $highBidder === null ? null : (int)$highBidder;

        $remainingPlayers = array_values(array_diff($playerIds, $passedPlayers));
        if ($highBidder !== null) {
            $remainingPlayers = array_values(array_diff($remainingPlayers, [$highBidder]));
        }

        if ($remainingPlayers === []) {
            return ResolveAuction::class;
        }

        do {
            $nextPlayerId = (int)$this->game->activeNextPlayer();
        } while (in_array($nextPlayerId, $passedPlayers, true));

        return Auction::class;
    }
}
