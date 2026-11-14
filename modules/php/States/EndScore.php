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
        $playerIds = array_map('intval', array_keys($this->game->loadPlayersBasicInfos()));
        $scores = [];
        $starCounts = [];

        foreach ($playerIds as $playerId) {
            $completed = $this->game->cards->getItemsInLocation(['completed', $playerId])->values();
            $basePoints = 0;
            $clownPoints = 0;
            $starCount = 0;
            $hasTwoCoinClown = false;

            foreach ($completed as $card) {
                $definition = Game::CARD_TYPES[$card->type];
                $basePoints += $definition['points'];
                $starCount += $definition['stars'];

                if ($definition['clown'] === 'perTwoCoins') {
                    $hasTwoCoinClown = true;
                }
            }

            foreach ($completed as $clownCard) {
                $clown = Game::CARD_TYPES[$clownCard->type]['clown'];
                if ($clown === null) {
                    continue;
                }

                if ($clown === 'perCard') {
                    $clownPoints += count($completed);
                } elseif ($clown === 'perStarCard') {
                    $clownPoints += 2 * count(array_filter(
                        $completed,
                        static fn($card): bool => Game::CARD_TYPES[$card->type]['stars'] > 0,
                    ));
                } elseif ($clown === 'perThreeCircles') {
                    $circleCount = array_sum(array_map(
                        static fn($card): int => count(Game::CARD_TYPES[$card->type]['bids']),
                        $completed,
                    ));
                    $clownPoints += intdiv($circleCount, 3);
                } elseif ($clown === 'perIncompleteCoin') {
                    $clownPoints += 2 * $this->game->getCoinsOnCards($playerId);
                } elseif ($clown === 'perLowCard') {
                    $lowCards = array_filter(
                        $completed,
                        static fn($card): bool => $card->id !== $clownCard->id
                            && Game::CARD_TYPES[$card->type]['points'] <= 5,
                    );
                    $clownPoints += 2 * count($lowCards);
                }
            }

            $coinDivisor = $hasTwoCoinClown ? 2 : 5;
            $coinPoints = intdiv($this->game->playerMoney->get($playerId), $coinDivisor);
            $scores[$playerId] = [
                'base' => $basePoints,
                'clowns' => $clownPoints,
                'coins' => $coinPoints,
                'stars' => 0,
                'total' => 0,
            ];
            $starCounts[$playerId] = $starCount;
        }

        arsort($starCounts, SORT_NUMERIC);
        $position = 0;
        $previousCount = null;
        $group = [];
        $applyGroup = function () use (&$group, &$position, &$scores): void {
            if ($group === []) {
                return;
            }
            $awards = array_slice([10, 7, 3], $position, count($group));
            $points = $awards === [] ? 0 : min($awards);
            foreach ($group as $playerId) {
                $scores[$playerId]['stars'] = $points;
            }
            $position += count($group);
            $group = [];
        };

        foreach ($starCounts as $playerId => $count) {
            if ($previousCount !== null && $count !== $previousCount) {
                $applyGroup();
            }
            if ($count > 0) {
                $group[] = $playerId;
            } else {
                $scores[$playerId]['stars'] = 0;
            }
            $previousCount = $count;
        }
        $applyGroup();

        $table = [[
            clienttranslate('Player'),
            clienttranslate('Attractions'),
            clienttranslate('Clowns'),
            clienttranslate('Coins'),
            clienttranslate('Star awards'),
            clienttranslate('Total'),
        ]];

        foreach ($playerIds as $playerId) {
            $score = $scores[$playerId];
            $total = $score['base'] + $score['clowns'] + $score['coins'] + $score['stars'];
            $scores[$playerId]['total'] = $total;
            $this->bga->playerScore->set($playerId, $total);
            $playerName = $this->game->getPlayerNameById($playerId);
            $table[] = [
                [
                    'str' => '${player_name}',
                    'args' => ['player_id' => $playerId, 'player_name' => $playerName],
                ],
                $score['base'],
                $score['clowns'],
                $score['coins'],
                $score['stars'],
                $total,
            ];

            if ($starCounts[$playerId] === 0) {
                $this->game->eliminatePlayer($playerId);
                $this->notify->all('message', clienttranslate('${player_name} has no completed star attraction and cannot win'), [
                    'player_id' => $playerId,
                    'player_name' => $playerName,
                ]);
            }
        }

        $this->notify->all('tableWindow', '', [
            'id' => 'finalScoring',
            'title' => clienttranslate('Final scoring'),
            'table' => $table,
            'closing' => clienttranslate('Close'),
        ]);

        return 99;
    }
}
