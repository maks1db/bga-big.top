<?php

declare(strict_types=1);

namespace Bga\Games\bigtop\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\Games\bigtop\Game;

class EndScore extends GameState
{
    public function __construct(protected Game $game)
    {
        parent::__construct($game, id: 98, type: StateType::GAME);
    }

    public function onEnteringState(): int
    {
        $scoring = $this->game->getScoring();
        $scores = $scoring['scores'];
        $starCounts = $scoring['starCounts'];

        foreach ($scores as $playerId => $score) {
            $this->bga->playerScore->set($playerId, $score['total']);
            if ($starCounts[$playerId] === 0) {
                $this->game->eliminatePlayer($playerId);
                $this->notify->all('message', clienttranslate('${player_name} has no completed star attraction and cannot win'), [
                    'player_id' => $playerId,
                    'player_name' => $this->game->getPlayerNameById($playerId),
                ]);
            }
        }

        $this->notify->all('tableWindow', '', [
            'id' => 'finalScoring',
            'title' => clienttranslate('Final scoring'),
            'table' => $scoring['table'],
            'closing' => clienttranslate('Close'),
        ]);

        return 99;
    }
}
