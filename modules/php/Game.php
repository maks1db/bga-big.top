<?php

declare(strict_types=1);

namespace Bga\Games\bigtop;

use Bga\GameFramework\Components\Counters\PlayerCounter;
use Bga\GameFramework\Components\Counters\TableCounter;
use Bga\GameFramework\Components\ItemManager\ItemLocation;
use Bga\GameFramework\Components\ItemManager\ItemManager;
use Bga\GameFramework\SystemException;
use Bga\GameFramework\UserException;
use Bga\Games\bigtop\States\NextPlayer;
use Bga\Games\bigtop\States\StartAuction;

class Game extends \Bga\GameFramework\Table
{
    public const TYPE_GAME_END = 35;

    public const CARD_TYPES = [
        1 => ['name' => 'Clown', 'points' => 3, 'stars' => 0, 'bids' => [7, 7, 7], 'effect' => 'draw', 'clown' => null],
        2 => ['name' => 'Snake Charmer', 'points' => 10, 'stars' => 0, 'bids' => [3, 5, 7, 10, 12], 'effect' => null, 'clown' => null],
        3 => ['name' => 'Knife Thrower', 'points' => 8, 'stars' => 0, 'bids' => [11, 12], 'effect' => null, 'clown' => null],
        4 => ['name' => 'Clown', 'points' => 1, 'stars' => 0, 'bids' => [4, 5, 6, 7, 10], 'effect' => null, 'clown' => 'perCard'],
        5 => ['name' => 'Fire Eater', 'points' => 2, 'stars' => 1, 'bids' => [1, 2, 3], 'effect' => null, 'clown' => null],
        6 => ['name' => 'Tightrope Walker', 'points' => 3, 'stars' => 0, 'bids' => [10, 12], 'effect' => 'bankTwo', 'clown' => null],
        7 => ['name' => 'Clown', 'points' => 1, 'stars' => 0, 'bids' => [4, 4, 6, 6], 'effect' => null, 'clown' => 'perTwoCoins'],
        8 => ['name' => 'Strongman', 'points' => 1, 'stars' => 2, 'bids' => [5, 6], 'effect' => null, 'clown' => null],
        9 => ['name' => 'Clown', 'points' => 2, 'stars' => 0, 'bids' => [5, 6, 7, 10], 'effect' => null, 'clown' => 'perIncompleteCoin'],
        10 => ['name' => 'Clown', 'points' => 2, 'stars' => 0, 'bids' => [7, 7, 7], 'effect' => null, 'clown' => 'perLowCard'],
        11 => ['name' => 'Stilt Walker', 'points' => 8, 'stars' => 1, 'bids' => [5, 6, 7, 8], 'effect' => null, 'clown' => null],
        12 => ['name' => 'Plate Spinner', 'points' => 11, 'stars' => 0, 'bids' => [3, 5, 9], 'effect' => null, 'clown' => null],
        13 => ['name' => 'Juggler', 'points' => 6, 'stars' => 0, 'bids' => [4, 5, 6], 'effect' => null, 'clown' => null],
        14 => ['name' => 'Equestrian', 'points' => 7, 'stars' => 0, 'bids' => [10, 12], 'effect' => null, 'clown' => null],
        15 => ['name' => 'Tower of Chairs', 'points' => 3, 'stars' => 3, 'bids' => [6, 7, 8], 'effect' => null, 'clown' => null],
        16 => ['name' => 'Fortune Teller', 'points' => 3, 'stars' => 1, 'bids' => [4, 7], 'effect' => null, 'clown' => null],
        17 => ['name' => 'Animal Trainer', 'points' => 4, 'stars' => 0, 'bids' => [5, 6, 7], 'effect' => null, 'clown' => null],
        18 => ['name' => 'Contortionist', 'points' => 2, 'stars' => 0, 'bids' => [2, 4], 'effect' => 'ownCoin', 'clown' => null],
        19 => ['name' => 'Trapeze Artist', 'points' => 9, 'stars' => 0, 'bids' => [1, 3, 5], 'effect' => null, 'clown' => null],
        20 => ['name' => 'Acrobats', 'points' => 3, 'stars' => 0, 'bids' => [2, 6, 8], 'effect' => 'ownCoin', 'clown' => null],
        21 => ['name' => 'Reptile Keeper', 'points' => 1, 'stars' => 1, 'bids' => [3, 5], 'effect' => null, 'clown' => null],
        22 => ['name' => 'Human Cannonball', 'points' => 3, 'stars' => 2, 'bids' => [3, 3, 7, 10], 'effect' => null, 'clown' => null],
        23 => ['name' => 'Extra Acrobat', 'points' => 9, 'stars' => 0, 'bids' => [4, 6, 8, 9], 'effect' => null, 'clown' => null],
        24 => ['name' => 'Sword Swallower', 'points' => 12, 'stars' => 0, 'bids' => [3, 5, 7, 9, 11], 'effect' => null, 'clown' => null],
        25 => ['name' => 'Clown', 'points' => 1, 'stars' => 0, 'bids' => [1, 2, 3, 4, 5, 6, 7, 8, 9], 'effect' => null, 'clown' => 'perThreeCircles'],
        26 => ['name' => 'Teeterboard Acrobats', 'points' => 4, 'stars' => 0, 'bids' => [7, 9, 11], 'effect' => 'ownCoin', 'clown' => null],
        27 => ['name' => 'Clown', 'points' => 2, 'stars' => 1, 'bids' => [1, 3, 6, 11], 'effect' => null, 'clown' => 'perStarCard'],
        28 => ['name' => 'Ring of Fire', 'points' => 20, 'stars' => 0, 'bids' => [8, 9, 11, 13], 'effect' => null, 'clown' => null],
        29 => ['name' => 'Mime', 'points' => 10, 'stars' => 0, 'bids' => [2, 4, 5, 6, 7], 'effect' => null, 'clown' => null],
        30 => ['name' => 'Illusionist', 'points' => 13, 'stars' => 0, 'bids' => [2, 4, 6, 8, 10], 'effect' => null, 'clown' => null],
        31 => ['name' => 'Ringmaster', 'points' => 3, 'stars' => 0, 'bids' => [2, 5, 7, 8], 'effect' => null, 'clown' => null],
        32 => ['name' => 'Ringmaster', 'points' => 3, 'stars' => 0, 'bids' => [3, 5, 6, 8], 'effect' => null, 'clown' => null],
        33 => ['name' => 'Ringmaster', 'points' => 3, 'stars' => 0, 'bids' => [3, 4, 7, 9], 'effect' => null, 'clown' => null],
        34 => ['name' => 'Ringmaster', 'points' => 3, 'stars' => 0, 'bids' => [2, 4, 6, 9], 'effect' => null, 'clown' => null],
        self::TYPE_GAME_END => ['name' => 'Game End', 'points' => 0, 'stars' => 0, 'bids' => [], 'effect' => null, 'clown' => null],
    ];

    public PlayerCounter $playerMoney;
    public TableCounter $bank;
    public ItemManager $cards;

    public function __construct()
    {
        parent::__construct();

        $this->playerMoney = $this->bga->counterFactory->createPlayerCounter('money');
        $this->bank = $this->bga->counterFactory->createTableCounter('bank');
        $this->cards = $this->bga->itemManagerFactory->createItemManager(
            Card::class,
            locations: [
                new ItemLocation('deck'),
                new ItemLocation('top_deck'),
                new ItemLocation('bottom_deck'),
                new ItemLocation('ringmaster_deck'),
                new ItemLocation('end_card'),
                new ItemLocation('hand'),
                new ItemLocation('attraction'),
                new ItemLocation('completed'),
                new ItemLocation('auction'),
                new ItemLocation('discard'),
            ],
        );
    }

    public function getGameProgression(): int
    {
        $remaining = $this->cards->countItemsInLocation('deck');
        return min(100, (int)round((30 - $remaining) * 100 / 30));
    }

    public function upgradeTableDb($from_version)
    {
    }

    public function getAllDatas(int $currentPlayerId): array
    {
        $result = [];
        $result['players'] = $this->getCollectionFromDb(
            'SELECT `player_id` AS `id`, `player_score` AS `score` FROM `player`'
        );
        $result['players'][$currentPlayerId]['money'] = $this->playerMoney->get($currentPlayerId);
        $this->bank->fillResult($result);

        $result['cardTypes'] = self::CARD_TYPES;
        $result['auctioneer'] = (int)$this->globals->get('auctioneer_id');
        $result['passedPlayers'] = array_map('intval', $this->globals->get('passed_players', []));
        $result['deckCount'] = $this->cards->countItemsInLocation('deck');
        $result['hand'] = $this->cards->getItemsInLocation(['hand', $currentPlayerId])->values();
        $result['attractions'] = $this->cards->getItemsInLocation('attraction')->values();
        $result['completed'] = $this->cards->getItemsInLocation('completed')->values();
        $result['auction'] = $this->cards->getItemsInLocation('auction')->values();
        $scoringFinished = (bool)$this->globals->get('scoring_finished', false);
        $result['scoringFinished'] = $scoringFinished;
        $result['scoring'] = $this->getScoring(!$scoringFinished);

        return $result;
    }

    public function setActivePlayer(int $playerId): void
    {
        $playerIds = array_map('intval', array_keys($this->loadPlayersBasicInfos()));
        foreach ($playerIds as $ignored) {
            if ((int)$this->activeNextPlayer() === $playerId) {
                return;
            }
        }
        throw new SystemException('Cannot activate an unknown player');
    }

    public function changeMoney(int $playerId, int $delta): int
    {
        $value = $this->playerMoney->inc($playerId, $delta, null);
        $this->bga->notify->player($playerId, 'moneyChanged', '', ['value' => $value]);
        return $value;
    }

    public function getCoinsOnCards(int $playerId): int
    {
        $cards = $this->cards->getItemsInLocation(['attraction', $playerId]);
        return array_sum(array_map(
            static fn(Card $card): int => count($card->covered),
            $cards->values(),
        ));
    }

    public function getBehindMoney(int $playerId): int
    {
        return $this->playerMoney->get($playerId) - $this->getCoinsOnCards($playerId);
    }

    public function moveCoinToBehind(int $cardId, int $circleIndex, int $playerId): Card
    {
        $card = $this->cards->getItemById($cardId);
        if (
            $card === null
            || $card->location !== 'attraction'
            || $card->locationArg !== $playerId
            || !in_array($circleIndex, array_map('intval', $card->covered), true)
        ) {
            throw new UserException(clienttranslate('There is no coin on that circle'));
        }

        $card->covered = array_values(array_filter(
            $card->covered,
            static fn(int $index): bool => $index !== $circleIndex,
        ));
        $this->cards->updateItem($card, 'covered');

        $this->bga->notify->all('coinRemoved', clienttranslate('${player_name} takes a coin back from a card'), [
            'player_id' => $playerId,
            'player_name' => $this->getPlayerNameById($playerId),
            'card' => $card,
            'scoring' => $this->getScoring(true),
        ]);

        return $card;
    }

    public function completeIfNeeded(Card $card, int $playerId): bool
    {
        if (
            $card->location !== 'attraction'
            || $card->locationArg !== $playerId
            || count($card->covered) !== count(self::CARD_TYPES[$card->type]['bids'])
        ) {
            return false;
        }

        $this->completeCard($card, $playerId);
        return true;
    }

    public function completeCard(Card $card, int $playerId): void
    {
        $this->cards->moveItem($card, ['completed', $playerId]);
        $card->covered = [];
        $this->cards->updateItem($card, 'covered');

        $this->bga->notify->all('attractionCompleted', clienttranslate('${player_name} completes ${card_name}'), [
            'player_id' => $playerId,
            'player_name' => $this->getPlayerNameById($playerId),
            'card' => $card,
            'card_name' => self::CARD_TYPES[$card->type]['name'],
            'scoring' => $this->getScoring(true),
            'i18n' => ['card_name'],
        ]);

        $effect = self::CARD_TYPES[$card->type]['effect'];
        if ($effect !== null) {
            $queue = $this->bga->globals->get('effect_queue', []);
            $queue[] = $effect;
            $this->bga->globals->set('effect_queue', $queue);
        }
    }

    public function getEmptyCircleTargets(int $playerId): array
    {
        $targets = [];
        $cards = $this->cards->getItemsInLocation(['attraction', $playerId]);
        foreach ($cards as $card) {
            $covered = array_map('intval', $card->covered);
            $empty = [];
            foreach (self::CARD_TYPES[$card->type]['bids'] as $index => $ignored) {
                if (!in_array($index, $covered, true)) {
                    $empty[] = $index;
                }
            }
            if ($empty !== []) {
                $targets[$card->id] = $empty;
            }
        }
        return $targets;
    }

    public function getScoring(bool $intermediate = false): array
    {
        $playerIds = array_map('intval', array_keys($this->loadPlayersBasicInfos()));
        $scores = [];
        $starCounts = [];

        foreach ($playerIds as $playerId) {
            $completed = $this->cards->getItemsInLocation(['completed', $playerId])->values();
            $basePoints = 0;
            $baseValues = [];
            $clownPoints = 0;
            $clownDetails = [];
            $completedClowns = 0;
            $starCount = 0;
            $hasTwoCoinClown = false;
            $coinsOnCards = $this->getCoinsOnCards($playerId);

            foreach ($completed as $card) {
                $definition = self::CARD_TYPES[$card->type];
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
                $clown = self::CARD_TYPES[$clownCard->type]['clown'];
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
                        static fn($card): bool => self::CARD_TYPES[$card->type]['stars'] > 0,
                    ));
                    $points = 2 * $count;
                    $clownPoints += $points;
                    $clownDetails[] = sprintf(clienttranslate('Star cards: %1$d × 2 = %2$d'), $count, $points);
                } elseif ($clown === 'perThreeCircles') {
                    $circleCount = array_sum(array_map(
                        static fn($card): int => count(self::CARD_TYPES[$card->type]['bids']),
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
                            && self::CARD_TYPES[$card->type]['points'] <= 5,
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
            $coinCount = $this->playerMoney->get($playerId);
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
                    'player_name' => $this->getPlayerNameById($playerId),
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
            if ($intermediate) {
                $coinCells[] = '<div class="bigtop-final-score-cell"><strong>?</strong></div>';
            } else {
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
            }
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
            $total = $starCounts[$playerId] === 0
                ? 0
                : $score['base'] + $score['clowns'] + $score['coins'] + $score['stars'];
            $scores[$playerId]['total'] = $total;
            $totalRow[] = '<div class="bigtop-final-score-total"><strong>' . $total . '</strong></div>';
        }
        $table[] = $totalRow;

        return [
            'scores' => $scores,
            'starCounts' => $starCounts,
            'table' => $table,
        ];
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

    protected function setupNewGame($players, $options = [])
    {
        $gameinfos = $this->getGameinfos();
        $colors = $gameinfos['player_colors'];
        $values = [];

        foreach ($players as $playerId => $player) {
            $values[] = vsprintf("(%s, '%s', '%s')", [
                $playerId,
                array_shift($colors),
                addslashes($player['player_name']),
            ]);
        }

        $this->DbQuery(
            'INSERT INTO `player` (`player_id`, `player_color`, `player_name`) VALUES ' . implode(',', $values)
        );
        $this->reattributeColorsBasedOnPreferences($players, $gameinfos['player_colors']);
        $this->reloadPlayersBasicInfos();

        $this->playerMoney->initDb(array_keys($players), 22);
        $this->bank->initDb(22);
        $this->cards->initDb();

        $rows = [];
        foreach (range(1, 30) as $type) {
            $rows[] = ['location' => 'deck', 'type' => $type];
        }
        foreach (range(31, 34) as $type) {
            $rows[] = ['location' => 'ringmaster_deck', 'type' => $type];
        }
        $rows[] = ['location' => 'end_card', 'type' => self::TYPE_GAME_END];
        $this->cards->createItems($rows);

        $this->cards->shuffle('deck');
        $this->cards->pickItems(4, 'deck', 'bottom_deck');
        $this->cards->moveAllItemsInLocation('end_card', 'bottom_deck');
        $this->cards->shuffle('bottom_deck');
        $this->cards->moveAllItemsInLocation('deck', 'top_deck');
        foreach ($this->cards->getItemsInLocation('bottom_deck') as $card) {
            $this->cards->moveItem($card, 'deck');
        }
        foreach ($this->cards->getItemsInLocation('top_deck') as $card) {
            $this->cards->moveItem($card, 'deck');
        }

        // [H1] В печатных правилах не указан способ распределения стартовых Рингмастеров.
        $this->cards->shuffle('ringmaster_deck');
        foreach (array_keys($players) as $playerId) {
            $this->cards->pickItem('ringmaster_deck', ['attraction', $playerId]);
            $this->cards->pickItem('deck', ['hand', $playerId]);
        }

        // [H2] Порядок ходов BGA заменяет выбор первого игрока при игре за столом.
        $this->activeNextPlayer();

        return StartAuction::class;
    }

    public function debug_completeCard(int $type = 1, int $playerId = 0): void
    {
        if ($this->getBgaEnvironment() !== 'studio') {
            return;
        }

        $playerId = $playerId > 0 ? $playerId : (int)$this->getCurrentPlayerId();
        $card = $this->cards->getItemsByFieldName('type', $type)->first();
        if ($card === null) {
            throw new SystemException('Unknown card type');
        }

        $this->cards->moveItem($card, ['attraction', $playerId]);
        $card->covered = array_keys(self::CARD_TYPES[$type]['bids']);
        $this->cards->updateItem($card, 'covered');
        $this->completeCard($card, $playerId);
        $this->bga->globals->set('effects_return', NextPlayer::class);
        $this->setActivePlayer($playerId);
        $this->gamestate->jumpToState(80);
    }

    public function debug_goToState(int $state = 10): void
    {
        if ($this->getBgaEnvironment() === 'studio') {
            $this->gamestate->jumpToState($state);
        }
    }
}
