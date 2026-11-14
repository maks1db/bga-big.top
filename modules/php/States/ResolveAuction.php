<?php

declare(strict_types=1);

namespace Bga\Games\bigtop\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\SystemException;
use Bga\Games\bigtop\Game;

class ResolveAuction extends GameState
{
    public function __construct(protected Game $game)
    {
        parent::__construct($game, id: 50, type: StateType::GAME);
    }

    public function onEnteringState(): string
    {
        $card = $this->game->cards->getItemOnTop('auction');
        if ($card === null) {
            throw new SystemException('Cannot resolve an auction without a card');
        }

        $winnerId = $this->globals->get('high_bidder');
        if ($winnerId === null) {
            $this->game->cards->moveItem($card, 'discard');
            $this->notify->all('auctionDiscarded', clienttranslate('Everybody passes: ${card_name} is discarded'), [
                'card' => $card,
                'card_name' => Game::CARD_TYPES[$card->type]['name'],
                'i18n' => ['card_name'],
            ]);
            return NextPlayer::class;
        }

        $winnerId = (int)$winnerId;
        $this->game->changeActivePlayer($winnerId);
        return Payment::class;
    }
}
