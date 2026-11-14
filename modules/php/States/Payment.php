<?php

declare(strict_types=1);

namespace Bga\Games\bigtop\States;

use Bga\GameFramework\NotificationMessage;
use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\SystemException;
use Bga\GameFramework\UserException;
use Bga\Games\bigtop\Game;

class Payment extends GameState
{
    public function __construct(protected Game $game)
    {
        parent::__construct($game, id: 70, type: StateType::ACTIVE_PLAYER);
    }

    public function getArgs(int $activePlayerId): array
    {
        $removable = [];
        $cards = $this->game->cards->getItemsInLocation(['attraction', $activePlayerId]);
        foreach ($cards as $card) {
            if ($card->covered !== []) {
                $removable[$card->id] = array_map('intval', $card->covered);
            }
        }

        return [
            'bid' => (int)$this->globals->get('current_bid'),
            'removable' => $removable,
        ];
    }

    #[PossibleAction]
    public function actRemoveCoin(int $cardId, int $circleIndex, int $activePlayerId): string
    {
        $this->game->moveCoinToBehind($cardId, $circleIndex, $activePlayerId);
        return Payment::class;
    }

    #[PossibleAction]
    public function actPay(int $activePlayerId): string
    {
        $bid = (int)$this->globals->get('current_bid');
        if ($this->game->getBehindMoney($activePlayerId) < $bid) {
            throw new UserException(clienttranslate('Take coins from your cards before paying this bid'));
        }

        $auctioneerId = (int)$this->globals->get('auctioneer_id');
        $this->game->changeMoney($activePlayerId, -$bid);
        if ($activePlayerId === $auctioneerId) {
            $this->game->bank->inc($bid, new NotificationMessage());
        } else {
            $this->game->changeMoney($auctioneerId, $bid);
        }

        $card = $this->game->cards->getItemOnTop('auction');
        if ($card === null) {
            throw new SystemException('Cannot pay for an auction without a card');
        }

        $bankCoins = count($card->covered);
        if ($bankCoins > 0) {
            $this->game->changeMoney($activePlayerId, $bankCoins);
        }
        $this->game->cards->moveItem($card, ['attraction', $activePlayerId]);

        $this->notify->all('auctionWon', clienttranslate('${player_name} wins ${card_name} for ${bid}'), [
            'player_id' => $activePlayerId,
            'player_name' => $this->game->getPlayerNameById($activePlayerId),
            'auctioneer_id' => $auctioneerId,
            'card' => $card,
            'card_name' => Game::CARD_TYPES[$card->type]['name'],
            'bid' => $bid,
            'i18n' => ['card_name'],
        ]);
        if ($this->game->completeIfNeeded($card, $activePlayerId)) {
            $this->globals->set('effects_return', NextPlayer::class);
            return ResolveEffects::class;
        }

        return NextPlayer::class;
    }

    public function zombie(int $playerId): string
    {
        $bid = (int)$this->globals->get('current_bid');
        while ($this->game->getBehindMoney($playerId) < $bid) {
            $args = $this->getArgs($playerId);
            if ($args['removable'] === []) {
                throw new SystemException('A zombie player cannot pay a previously validated bid');
            }
            $cardId = (int)$this->getRandomZombieChoice(array_keys($args['removable']));
            $circleIndex = (int)$this->getRandomZombieChoice($args['removable'][$cardId]);
            $this->game->moveCoinToBehind($cardId, $circleIndex, $playerId);
        }

        return $this->actPay($playerId);
    }
}
