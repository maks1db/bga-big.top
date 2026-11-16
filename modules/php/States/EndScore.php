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
            $baseValues = [];
            $clownPoints = 0;
            $clownDetails = [];
            $completedClowns = 0;
            $starCount = 0;
            $hasTwoCoinClown = false;
            $coinsOnCards = $this->game->getCoinsOnCards($playerId);

            foreach ($completed as $card) {
                $definition = Game::CARD_TYPES[$card->type];
                $basePoints += $definition['points'];
                $baseValues[] = $definition['points'];
                $starCount += $definition['stars'];

                if ($definition['clown'] !== null) {
                    $completedClowns++;
                }
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
                    $count = count($completed);
                    $points = $count;
                    $clownPoints += $points;
                    $clownDetails[] = sprintf(clienttranslate('Completed cards: %1$d × 1 = %2$d'), $count, $points);
                } elseif ($clown === 'perStarCard') {
                    $count = count(array_filter(
                        $completed,
                        static fn($card): bool => Game::CARD_TYPES[$card->type]['stars'] > 0,
                    ));
                    $points = 2 * $count;
                    $clownPoints += $points;
                    $clownDetails[] = sprintf(clienttranslate('Star cards: %1$d × 2 = %2$d'), $count, $points);
                } elseif ($clown === 'perThreeCircles') {
                    $circleCount = array_sum(array_map(
                        static fn($card): int => count(Game::CARD_TYPES[$card->type]['bids']),
                        $completed,
                    ));
                    $points = intdiv($circleCount, 3);
                    $clownPoints += $points;
                    $detail = sprintf(clienttranslate('Circles: %1$d ÷ 3 = %2$d'), $circleCount, $points);
                    if ($circleCount % 3 !== 0) {
                        $detail .= ' ' . clienttranslate('(rounded down)');
                    }
                    $clownDetails[] = $detail;
                } elseif ($clown === 'perIncompleteCoin') {
                    $points = 2 * $coinsOnCards;
                    $clownPoints += $points;
                    $clownDetails[] = sprintf(
                        clienttranslate('Coins on incomplete attractions: %1$d × 2 = %2$d'),
                        $coinsOnCards,
                        $points,
                    );
                } elseif ($clown === 'perLowCard') {
                    $lowCards = array_filter(
                        $completed,
                        static fn($card): bool => $card->id !== $clownCard->id
                            && Game::CARD_TYPES[$card->type]['points'] <= 5,
                    );
                    $count = count($lowCards);
                    $points = 2 * $count;
                    $clownPoints += $points;
                    $clownDetails[] = sprintf(clienttranslate('Low-value cards: %1$d × 2 = %2$d'), $count, $points);
                }
            }

            if ($clownDetails === []) {
                $clownDetails[] = $completedClowns === 0
                    ? clienttranslate('No completed clowns')
                    : clienttranslate('Coin scoring bonus applied');
            }

            $coinDivisor = $hasTwoCoinClown ? 2 : 5;
            $coinCount = $this->game->playerMoney->get($playerId);
            $coinPoints = intdiv($coinCount, $coinDivisor);
            $scores[$playerId] = [
                'base' => $basePoints,
                'base_details' => $baseValues === [] ? [clienttranslate('No completed cards')] : [implode(' + ', $baseValues)],
                'clowns' => $clownPoints,
                'clown_details' => $clownDetails,
                'coins' => $coinPoints,
                'coin_count' => $coinCount,
                'coin_divisor' => $coinDivisor,
                'stars' => 0,
                'star_first_place' => null,
                'star_last_place' => null,
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
            $firstPlace = $position + 1;
            $lastPlace = $position + count($group);
            foreach ($group as $playerId) {
                $scores[$playerId]['stars'] = $points;
                $scores[$playerId]['star_first_place'] = $firstPlace;
                $scores[$playerId]['star_last_place'] = $lastPlace;
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

        $table = [[clienttranslate('Scoring category')]];
        foreach ($playerIds as $playerId) {
            $table[0][] = [
                'str' => '${player_name}',
                'args' => [
                    'player_id' => $playerId,
                    'player_name' => $this->game->getPlayerNameById($playerId),
                ],
            ];
        }

        $table[] = array_merge(
            [clienttranslate('Card points')],
            array_map(
                fn(int $playerId): string => $this->formatScoreCell(
                    $scores[$playerId]['base'],
                    $scores[$playerId]['base_details'],
                ),
                $playerIds,
            ),
        );
        $table[] = array_merge(
            [clienttranslate('Clown bonuses')],
            array_map(
                fn(int $playerId): string => $this->formatScoreCell(
                    $scores[$playerId]['clowns'],
                    $scores[$playerId]['clown_details'],
                ),
                $playerIds,
            ),
        );

        $coinCells = [];
        $starCells = [];
        foreach ($playerIds as $playerId) {
            $score = $scores[$playerId];
            $coinDetails = [sprintf(
                clienttranslate('%1$d coins ÷ %2$d = %3$d'),
                $score['coin_count'],
                $score['coin_divisor'],
                $score['coins'],
            )];
            if ($score['coin_count'] % $score['coin_divisor'] !== 0) {
                $coinDetails[] = clienttranslate('Remainder ignored');
            }
            if ($score['coin_divisor'] === 2) {
                $coinDetails[] = clienttranslate('Clown bonus applied');
            }
            $coinCells[] = $this->formatScoreCell($score['coins'], $coinDetails);
            $starCells[] = $this->formatScoreCell($score['stars'], [$this->formatStarDetails(
                $starCounts[$playerId],
                $score['star_first_place'],
                $score['star_last_place'],
            )]);
        }
        $table[] = array_merge([clienttranslate('Coin points')], $coinCells);
        $table[] = array_merge([clienttranslate('Star award')], $starCells);

        $totalRow = ['<div class="bigtop-final-score-total"><strong>' . clienttranslate('Total') . '</strong></div>'];
        foreach ($playerIds as $playerId) {
            $score = $scores[$playerId];
            $total = $score['base'] + $score['clowns'] + $score['coins'] + $score['stars'];
            $scores[$playerId]['total'] = $total;
            $this->bga->playerScore->set($playerId, $total);
            $totalRow[] = '<div class="bigtop-final-score-total"><strong>' . $total . '</strong></div>';

            if ($starCounts[$playerId] === 0) {
                $this->game->eliminatePlayer($playerId);
                $this->notify->all('message', clienttranslate('${player_name} has no completed star attraction and cannot win'), [
                    'player_id' => $playerId,
                    'player_name' => $this->game->getPlayerNameById($playerId),
                ]);
            }
        }
        $table[] = $totalRow;

        $this->notify->all('tableWindow', '', [
            'id' => 'finalScoring',
            'title' => clienttranslate('Final scoring'),
            'table' => $table,
            'closing' => clienttranslate('Close'),
        ]);

        return 99;
    }

    private function formatScoreCell(int $points, array $details): string
    {
        $html = '<div class="bigtop-final-score-cell"><strong>' . $points . '</strong>';
        foreach ($details as $detail) {
            $html .= '<br>' . $detail;
        }
        return $html . '</div>';
    }

    private function formatStarDetails(int $starCount, ?int $firstPlace, ?int $lastPlace): string
    {
        if ($starCount === 0 || $firstPlace === null || $lastPlace === null) {
            return clienttranslate('No stars, cannot win');
        }
        if ($firstPlace === $lastPlace) {
            return sprintf(
                clienttranslate('%1$d stars — %2$s'),
                $starCount,
                $this->formatPlace($firstPlace),
            );
        }
        return sprintf(
            clienttranslate('%1$d stars — tied %2$s–%3$s'),
            $starCount,
            $this->formatPlace($firstPlace, true),
            $this->formatPlace($lastPlace, true),
        );
    }

    private function formatPlace(int $place, bool $short = false): string
    {
        return match ([$place, $short]) {
            [1, false] => clienttranslate('1st place'),
            [2, false] => clienttranslate('2nd place'),
            [3, false] => clienttranslate('3rd place'),
            [4, false] => clienttranslate('4th place'),
            [1, true] => clienttranslate('1st'),
            [2, true] => clienttranslate('2nd'),
            [3, true] => clienttranslate('3rd'),
            [4, true] => clienttranslate('4th'),
            default => (string) $place,
        };
    }
}
