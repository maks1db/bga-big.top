<?php

declare(strict_types=1);

namespace Bga\Games\bigtop;

use Bga\GameFramework\Components\Counters\PlayerCounter;
use Bga\GameFramework\Components\Counters\TableCounter;
use Bga\GameFramework\Components\ItemManager\ItemLocation;
use Bga\GameFramework\Components\ItemManager\ItemManager;
use Bga\GameFramework\SystemException;
use Bga\GameFramework\UserException;
use Bga\Games\bigtop\States\StartAuction;

class Game extends \Bga\GameFramework\Table
{
    public const TYPE_GAME_END = 35;

    public const CARD_TYPES = [
        1 => ['name' => 'Clown', 'points' => 3, 'stars' => 1, 'bids' => [7, 7, 7], 'effect' => 'draw', 'clown' => null],
        2 => ['name' => 'Snake Charmer', 'points' => 10, 'stars' => 0, 'bids' => [3, 5, 7, 10, 12], 'effect' => null, 'clown' => null],
        3 => ['name' => 'Knife Thrower', 'points' => 8, 'stars' => 0, 'bids' => [11, 12], 'effect' => null, 'clown' => null],
        4 => ['name' => 'Clown', 'points' => 1, 'stars' => 1, 'bids' => [4, 5, 6, 7, 10], 'effect' => null, 'clown' => 'perTwoCoins'],
        5 => ['name' => 'Fire Eater', 'points' => 2, 'stars' => 1, 'bids' => [1, 2, 3], 'effect' => null, 'clown' => null],
        6 => ['name' => 'Tightrope Walker', 'points' => 3, 'stars' => 0, 'bids' => [10, 12], 'effect' => 'bankTwo', 'clown' => null],
        7 => ['name' => 'Clown', 'points' => 1, 'stars' => 1, 'bids' => [4, 4, 6, 6], 'effect' => null, 'clown' => 'perCard'],
        8 => ['name' => 'Strongman', 'points' => 1, 'stars' => 0, 'bids' => [5, 6], 'effect' => null, 'clown' => null],
        9 => ['name' => 'Clown', 'points' => 2, 'stars' => 1, 'bids' => [5, 6, 7, 10], 'effect' => null, 'clown' => 'perStarCard'],
        10 => ['name' => 'Clown', 'points' => 2, 'stars' => 1, 'bids' => [7, 7, 7], 'effect' => null, 'clown' => 'perLowCard'],
        11 => ['name' => 'Stilt Walker', 'points' => 8, 'stars' => 1, 'bids' => [5, 6, 7, 8], 'effect' => null, 'clown' => null],
        12 => ['name' => 'Plate Spinner', 'points' => 11, 'stars' => 0, 'bids' => [3, 5, 9], 'effect' => null, 'clown' => null],
        13 => ['name' => 'Juggler', 'points' => 6, 'stars' => 0, 'bids' => [4, 5, 6], 'effect' => null, 'clown' => null],
        14 => ['name' => 'Equestrian', 'points' => 7, 'stars' => 0, 'bids' => [10, 12], 'effect' => null, 'clown' => null],
        15 => ['name' => 'Tower of Chairs', 'points' => 3, 'stars' => 2, 'bids' => [6, 7, 8], 'effect' => null, 'clown' => null],
        16 => ['name' => 'Fortune Teller', 'points' => 3, 'stars' => 1, 'bids' => [4, 7], 'effect' => null, 'clown' => null],
        17 => ['name' => 'Animal Trainer', 'points' => 4, 'stars' => 0, 'bids' => [5, 6, 7], 'effect' => null, 'clown' => null],
        18 => ['name' => 'Contortionist', 'points' => 2, 'stars' => 0, 'bids' => [2, 4], 'effect' => 'ownCoin', 'clown' => null],
        19 => ['name' => 'Trapeze Artist', 'points' => 9, 'stars' => 0, 'bids' => [1, 3, 5], 'effect' => null, 'clown' => null],
        20 => ['name' => 'Acrobats', 'points' => 3, 'stars' => 0, 'bids' => [2, 6, 8], 'effect' => 'ownCoin', 'clown' => null],
        21 => ['name' => 'Reptile Keeper', 'points' => 1, 'stars' => 1, 'bids' => [3, 5], 'effect' => null, 'clown' => null],
        22 => ['name' => 'Human Cannonball', 'points' => 3, 'stars' => 2, 'bids' => [3, 3, 7, 10], 'effect' => null, 'clown' => null],
        23 => ['name' => 'Extra Acrobat', 'points' => 9, 'stars' => 1, 'bids' => [4, 6, 8, 9], 'effect' => null, 'clown' => null],
        24 => ['name' => 'Sword Swallower', 'points' => 12, 'stars' => 0, 'bids' => [3, 5, 7, 9, 11], 'effect' => null, 'clown' => null],
        25 => ['name' => 'Clown', 'points' => 1, 'stars' => 1, 'bids' => [1, 2, 3, 4, 5, 6, 7, 8, 9], 'effect' => null, 'clown' => 'perThreeCircles'],
        26 => ['name' => 'Teeterboard Acrobats', 'points' => 4, 'stars' => 0, 'bids' => [7, 9, 11], 'effect' => 'ownCoin', 'clown' => null],
        27 => ['name' => 'Clown', 'points' => 2, 'stars' => 1, 'bids' => [1, 3, 6, 11], 'effect' => null, 'clown' => 'perIncompleteCoin'],
        28 => ['name' => 'Ring of Fire', 'points' => 20, 'stars' => 1, 'bids' => [8, 9, 11, 13], 'effect' => null, 'clown' => null],
        29 => ['name' => 'Mime', 'points' => 10, 'stars' => 0, 'bids' => [2, 4, 5, 6, 7], 'effect' => null, 'clown' => null],
        30 => ['name' => 'Illusionist', 'points' => 13, 'stars' => 0, 'bids' => [2, 4, 6, 8, 10], 'effect' => null, 'clown' => null],
        31 => ['name' => 'Ringmaster', 'points' => 3, 'stars' => 1, 'bids' => [2, 5, 7, 8], 'effect' => null, 'clown' => null],
        32 => ['name' => 'Ringmaster', 'points' => 3, 'stars' => 1, 'bids' => [3, 5, 6, 8], 'effect' => null, 'clown' => null],
        33 => ['name' => 'Ringmaster', 'points' => 3, 'stars' => 1, 'bids' => [3, 4, 7, 9], 'effect' => null, 'clown' => null],
        34 => ['name' => 'Ringmaster', 'points' => 3, 'stars' => 1, 'bids' => [2, 4, 6, 9], 'effect' => null, 'clown' => null],
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
        $result['deckCount'] = $this->cards->countItemsInLocation('deck');
        $result['hand'] = $this->cards->getItemsInLocation(['hand', $currentPlayerId])->values();
        $result['attractions'] = $this->cards->getItemsInLocation('attraction')->values();
        $result['completed'] = $this->cards->getItemsInLocation('completed')->values();
        $result['auction'] = $this->cards->getItemsInLocation('auction')->values();

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

        $this->cards->moveItem($card, ['completed', $playerId]);
        $card->covered = [];
        $this->cards->updateItem($card, 'covered');

        $this->bga->notify->all('attractionCompleted', clienttranslate('${player_name} completes ${card_name}'), [
            'player_id' => $playerId,
            'player_name' => $this->getPlayerNameById($playerId),
            'card' => $card,
            'card_name' => self::CARD_TYPES[$card->type]['name'],
            'i18n' => ['card_name'],
        ]);

        return true;
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

    public function debug_goToState(int $state = 10)
    {
        $this->gamestate->jumpToState($state);
    }
}
