<?php

declare(strict_types=1);

namespace Bga\Games\bigtop\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\bigtop\Game;

class PlaceCoins extends GameState
{
    public function __construct(protected Game $game)
    {
        parent::__construct($game, id: 60, type: StateType::ACTIVE_PLAYER);
    }

    public function getArgs(int $activePlayerId): array
    {
        $placedCardIds = array_map('intval', $this->globals->get('placed_card_ids', []));
        $currentBid = (int)$this->globals->get('current_bid');
        $placeable = [];
        $removable = [];

        $cards = $this->game->cards->getItemsInLocation(['attraction', $activePlayerId]);
        foreach ($cards as $card) {
            $covered = array_map('intval', $card->covered);
            if ($covered !== []) {
                $removable[$card->id] = $covered;
            }
            if (in_array($card->id, $placedCardIds, true)) {
                continue;
            }

            $matching = [];
            foreach (Game::CARD_TYPES[$card->type]['bids'] as $index => $value) {
                if ($value === $currentBid && !in_array($index, $covered, true)) {
                    $matching[] = $index;
                }
            }
            if ($matching !== []) {
                $placeable[$card->id] = $matching;
            }
        }

        return [
            'placeable' => $placeable,
            'removable' => $removable,
        ];
    }

    #[PossibleAction]
    public function actPlaceCoin(int $cardId, int $circleIndex, int $activePlayerId, array $args): string
    {
        if (!in_array($circleIndex, $args['placeable'][$cardId] ?? [], true)) {
            throw new UserException(clienttranslate('You cannot cover that circle with this bid'));
        }
        if ($this->game->getBehindMoney($activePlayerId) < 1) {
            throw new UserException(clienttranslate('You have no coin behind your screen'));
        }

        $card = $this->game->cards->getItemById($cardId);
        $card->covered[] = $circleIndex;
        sort($card->covered);
        $this->game->cards->updateItem($card, 'covered');

        $placedCardIds = array_map('intval', $this->globals->get('placed_card_ids', []));
        $placedCardIds[] = $cardId;
        $this->globals->set('placed_card_ids', array_values(array_unique($placedCardIds)));

        $this->notify->all('coinPlaced', clienttranslate('${player_name} covers a circle on a card'), [
            'player_id' => $activePlayerId,
            'player_name' => $this->game->getPlayerNameById($activePlayerId),
            'card' => $card,
        ]);
        if ($this->game->completeIfNeeded($card, $activePlayerId)) {
            $this->globals->set('effects_return', PlaceCoins::class);
            return ResolveEffects::class;
        }

        return PlaceCoins::class;
    }

    #[PossibleAction]
    public function actFinishPlacement(): string
    {
        return NextBid::class;
    }

    public function zombie(int $playerId): string
    {
        $args = $this->getArgs($playerId);
        $choices = ['finish'];
        foreach ($args['placeable'] as $cardId => $circleIndexes) {
            foreach ($circleIndexes as $circleIndex) {
                $choices[] = "place:$cardId:$circleIndex";
            }
        }

        $choice = $this->getRandomZombieChoice($choices);
        if ($choice === 'finish') {
            return $this->actFinishPlacement();
        }

        [, $cardId, $circleIndex] = explode(':', $choice);
        return $this->actPlaceCoin((int)$cardId, (int)$circleIndex, $playerId, $args);
    }
}
