<?php

declare(strict_types=1);

namespace Bga\Games\bigtop\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\SystemException;
use Bga\Games\bigtop\Game;

class ResolveEffects extends GameState
{
    public function __construct(protected Game $game)
    {
        parent::__construct($game, id: 80, type: StateType::GAME);
    }

    public function onEnteringState(int $activePlayerId): string
    {
        $queue = $this->globals->get('effect_queue', []);
        if ($queue === []) {
            $returnState = $this->globals->get('effects_return', NextPlayer::class);
            $this->globals->delete('effects_return');
            return $returnState;
        }

        $effect = array_shift($queue);
        $this->globals->set('effect_queue', $queue);
        $targets = $this->game->getEmptyCircleTargets($activePlayerId);

        if ($effect === 'draw') {
            $card = $this->game->cards->pickItem('deck', ['hand', $activePlayerId]);
            if ($card === null) {
                throw new SystemException('The deck is empty while resolving a draw effect');
            }
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

            $this->notify->all('effectCardDrawn', clienttranslate('${player_name} draws ${card_name} with an immediate effect'), [
                'player_id' => $activePlayerId,
                'player_name' => $this->game->getPlayerNameById($activePlayerId),
                'card' => $card,
                'card_name' => $cardName,
                'deckCount' => $deckCount,
                'i18n' => ['card_name'],
            ]);
            $this->game->completeCard($card, $activePlayerId);
            return ResolveEffects::class;
        }

        if ($effect === 'ownCoin') {
            $remaining = $this->game->getBehindMoney($activePlayerId) >= 1 && $targets !== [] ? 1 : 0;
            $source = 'own';
        } elseif ($effect === 'bankTwo') {
            $remaining = min(2, $this->game->bank->get());
            if ($targets === []) {
                $remaining = 0;
            }
            $source = 'bank';
        } else {
            throw new SystemException('Unknown immediate card effect');
        }

        if ($remaining === 0) {
            $this->notify->all('effectSkipped', clienttranslate('${player_name} cannot apply an immediate card effect'), [
                'player_id' => $activePlayerId,
                'player_name' => $this->game->getPlayerNameById($activePlayerId),
            ]);
            return ResolveEffects::class;
        }

        $this->globals->set('effect_source', $source);
        $this->globals->set('effect_remaining', $remaining);
        return EffectCoins::class;
    }
}
