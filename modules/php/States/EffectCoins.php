<?php

declare(strict_types=1);

namespace Bga\Games\bigtop\States;

use Bga\GameFramework\NotificationMessage;
use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\bigtop\Game;

class EffectCoins extends GameState
{
    public function __construct(protected Game $game)
    {
        parent::__construct($game, id: 81, type: StateType::ACTIVE_PLAYER);
    }

    public function getArgs(int $activePlayerId): array
    {
        return [
            'targets' => $this->game->getEmptyCircleTargets($activePlayerId),
            'source' => $this->globals->get('effect_source'),
            'remaining' => (int)$this->globals->get('effect_remaining'),
        ];
    }

    #[PossibleAction]
    public function actEffectPlaceCoin(int $cardId, int $circleIndex, int $activePlayerId, array $args): string
    {
        if (!in_array($circleIndex, $args['targets'][$cardId] ?? [], true)) {
            throw new UserException(clienttranslate('That circle is not available'));
        }

        $source = $args['source'];
        if ($source === 'own' && $this->game->getBehindMoney($activePlayerId) < 1) {
            throw new UserException(clienttranslate('You have no coin behind your screen'));
        }
        if ($source === 'bank') {
            if ($this->game->bank->get() < 1) {
                throw new UserException(clienttranslate('The bank has no coins'));
            }
            $this->game->bank->inc(-1, new NotificationMessage());
            $this->game->changeMoney($activePlayerId, 1);
        }

        $card = $this->game->cards->getItemById($cardId);
        $card->covered[] = $circleIndex;
        sort($card->covered);
        $this->game->cards->updateItem($card, 'covered');
        $this->notify->all('coinPlaced', clienttranslate('${player_name} places a coin with an immediate effect'), [
            'player_id' => $activePlayerId,
            'player_name' => $this->game->getPlayerNameById($activePlayerId),
            'card' => $card,
        ]);
        $this->game->completeIfNeeded($card, $activePlayerId);

        $remaining = (int)$this->globals->get('effect_remaining') - 1;
        $this->globals->set('effect_remaining', $remaining);
        $hasTargets = $this->game->getEmptyCircleTargets($activePlayerId) !== [];
        $hasCoins = $source === 'own'
            ? $this->game->getBehindMoney($activePlayerId) >= 1
            : $this->game->bank->get() >= 1;
        if ($remaining > 0 && $hasTargets && $hasCoins) {
            return EffectCoins::class;
        }

        return ResolveEffects::class;
    }

    public function zombie(int $playerId): string
    {
        $args = $this->getArgs($playerId);
        $cardId = (int)$this->getRandomZombieChoice(array_keys($args['targets']));
        $circleIndex = (int)$this->getRandomZombieChoice($args['targets'][$cardId]);
        return $this->actEffectPlaceCoin($cardId, $circleIndex, $playerId, $args);
    }
}
