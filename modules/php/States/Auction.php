<?php

declare(strict_types=1);

namespace Bga\Games\bigtop\States;

use Bga\GameFramework\NotificationMessage;
use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\bigtop\Game;

class Auction extends GameState
{
    public function __construct(protected Game $game)
    {
        parent::__construct($game, id: 30, type: StateType::ACTIVE_PLAYER);
    }

    public function getArgs(int $activePlayerId): array
    {
        $currentBid = (int)$this->globals->get('current_bid', 0);
        return [
            'currentBid' => $currentBid,
            'minBid' => $currentBid + 1,
            'highBidder' => $this->globals->get('high_bidder'),
        ];
    }

    #[PossibleAction]
    public function actBid(int $bid, int $activePlayerId, array $args): string
    {
        if ($bid < $args['minBid']) {
            throw new UserException(clienttranslate('You must increase the current bid'));
        }
        if ($bid > $this->game->playerMoney->get($activePlayerId)) {
            throw new UserException(clienttranslate('You cannot afford this bid'));
        }

        $this->globals->set('current_bid', $bid);
        $this->globals->set('high_bidder', $activePlayerId);
        $this->globals->set('placed_card_ids', []);
        $this->notify->all('bidMade', clienttranslate('${player_name} bids ${bid}'), [
            'player_id' => $activePlayerId,
            'player_name' => $this->game->getPlayerNameById($activePlayerId),
            'bid' => $bid,
        ]);

        $auctionCard = $this->game->cards->getItemOnTop('auction');
        $covered = array_map('intval', $auctionCard->covered);
        foreach (Game::CARD_TYPES[$auctionCard->type]['bids'] as $index => $value) {
            if ($value === $bid && !in_array($index, $covered, true) && $this->game->bank->get() > 0) {
                $auctionCard->covered[] = $index;
                sort($auctionCard->covered);
                $this->game->cards->updateItem($auctionCard, 'covered');
                $this->game->bank->inc(-1, new NotificationMessage());
                $this->notify->all('coinPlaced', clienttranslate('A bank coin is placed on the auctioned attraction'), [
                    'card' => $auctionCard,
                ]);
                break;
            }
        }

        return PlaceCoins::class;
    }

    #[PossibleAction]
    public function actPass(int $activePlayerId, array $args): string
    {
        $passedPlayers = $this->globals->get('passed_players', []);
        $passedPlayers[] = $activePlayerId;
        $this->globals->set('passed_players', array_values(array_unique($passedPlayers)));

        $this->notify->all('auctionPass', clienttranslate('${player_name} passes'), [
            'player_id' => $activePlayerId,
            'player_name' => $this->game->getPlayerNameById($activePlayerId),
        ]);

        return NextBid::class;
    }

    public function zombie(int $playerId): string
    {
        $args = $this->getArgs($playerId);
        $maxBid = $this->game->playerMoney->get($playerId);
        $choices = $args['minBid'] <= $maxBid ? ['bid', 'pass'] : ['pass'];
        if ($this->getRandomZombieChoice($choices) === 'bid') {
            return $this->actBid($args['minBid'], $playerId, $args);
        }
        return $this->actPass($playerId, $args);
    }
}
