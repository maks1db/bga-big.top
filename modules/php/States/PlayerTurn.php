<?php

declare(strict_types=1);

namespace Bga\Games\bigtop\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\bigtop\Game;

class PlayerTurn extends GameState
{
    public function __construct(protected Game $game)
    {
        parent::__construct($game, id: 10, type: StateType::ACTIVE_PLAYER);
    }

    public function getArgs(int $activePlayerId): array
    {
        $hand = $this->game->cards->getItemsInLocation(['hand', $activePlayerId]);
        return [
            'cardIds' => array_map('intval', $hand->keys()),
        ];
    }

    #[PossibleAction]
    public function actAuctionCard(int $cardId, int $activePlayerId, array $args): string
    {
        if (!in_array($cardId, $args['cardIds'], true)) {
            throw new UserException(clienttranslate('This card is not in your hand'));
        }

        $card = $this->game->cards->getItemById($cardId);
        $this->game->cards->moveItem($card, 'auction');
        $this->globals->set('current_bid', 0);
        $this->globals->set('high_bidder', null);
        $this->globals->set('passed_players', []);

        $this->notify->all('auctionCard', clienttranslate('${player_name} auctions ${card_name}'), [
            'player_id' => $activePlayerId,
            'player_name' => $this->game->getPlayerNameById($activePlayerId),
            'card' => $card,
            'card_name' => Game::CARD_TYPES[$card->type]['name'],
            'i18n' => ['card_name'],
        ]);

        return Auction::class;
    }

    public function zombie(int $playerId): string
    {
        $args = $this->getArgs($playerId);
        $cardId = $this->getRandomZombieChoice($args['cardIds']);
        return $this->actAuctionCard($cardId, $playerId, $args);
    }
}
