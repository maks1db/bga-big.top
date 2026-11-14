<?php

declare(strict_types=1);

namespace Bga\Games\bigtop\States;

use Bga\GameFramework\NotificationMessage;
use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\SystemException;
use Bga\Games\bigtop\Game;

class StartAuction extends GameState
{
    public function __construct(protected Game $game)
    {
        parent::__construct(
            $game,
            id: 20,
            type: StateType::GAME,
            updateGameProgression: true,
        );
    }

    public function onEnteringState(int $activePlayerId): string
    {
        $this->globals->set('auctioneer_id', $activePlayerId);
        $card = $this->game->cards->pickItem('deck', ['hand', $activePlayerId]);
        if ($card === null) {
            throw new SystemException('The deck is empty before the Game End card was drawn');
        }

        $this->game->giveExtraTime($activePlayerId);
        $deckCount = $this->game->cards->countItemsInLocation('deck');
        $cardName = Game::CARD_TYPES[$card->type]['name'];

        if ($card->type === Game::TYPE_GAME_END) {
            $this->notify->all('gameEndDrawn', clienttranslate('${player_name} draws the Game End card'), [
                'player_id' => $activePlayerId,
                'player_name' => $this->game->getPlayerNameById($activePlayerId),
                'deckCount' => $deckCount,
            ]);
            return EndScore::class;
        }

        $this->notify->all('cardDrawn', clienttranslate('${player_name} draws an attraction card'), [
            'player_id' => $activePlayerId,
            'player_name' => $this->game->getPlayerNameById($activePlayerId),
            'deckCount' => $deckCount,
            '_private' => [
                $activePlayerId => new NotificationMessage(
                    clienttranslate('You draw ${card_name}'),
                    [
                        'card' => $card,
                        'card_name' => $cardName,
                        'i18n' => ['card_name'],
                    ],
                ),
            ],
            '_merge_private' => true,
        ]);

        return PlayerTurn::class;
    }
}
