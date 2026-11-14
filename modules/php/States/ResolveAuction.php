<?php

declare(strict_types=1);

namespace Bga\Games\bigtop\States;

use Bga\GameFramework\NotificationMessage;
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
        $auctioneerId = (int)$this->globals->get('auctioneer_id');
        $bid = (int)$this->globals->get('current_bid');
        $this->game->playerMoney->inc($winnerId, -$bid, new NotificationMessage());

        if ($winnerId === $auctioneerId) {
            $this->game->bank->inc($bid, new NotificationMessage());
        } else {
            $this->game->playerMoney->inc($auctioneerId, $bid, new NotificationMessage());
        }

        $this->game->cards->moveItem($card, ['attraction', $winnerId]);
        $this->notify->all('auctionWon', clienttranslate('${player_name} wins ${card_name} for ${bid}'), [
            'player_id' => $winnerId,
            'player_name' => $this->game->getPlayerNameById($winnerId),
            'auctioneer_id' => $auctioneerId,
            'card' => $card,
            'card_name' => Game::CARD_TYPES[$card->type]['name'],
            'bid' => $bid,
            'i18n' => ['card_name'],
        ]);

        return NextPlayer::class;
    }
}
