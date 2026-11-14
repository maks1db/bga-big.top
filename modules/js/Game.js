/**
 * @typedef {Player & {
 *   money?: number
 * }} bigtopPlayer
 */
/**
 * @typedef {Object} bigtopCard
 * @property {number} id
 * @property {number} type
 * @property {string} location
 * @property {number|null} locationArg
 * @property {number[]} covered
 */
/**
 * @typedef {Gamedatas<bigtopPlayer> & {
 *   bank: number,
 *   cardTypes: Object,
 *   deckCount: number,
 *   hand: bigtopCard[],
 *   attractions: bigtopCard[],
 *   completed: bigtopCard[],
 *   auction: bigtopCard[]
 * }} bigtopGamedatas
 */

class PlayerTurn {
    constructor(game, bga) {
        this.game = game;
        this.bga = bga;
    }

    onEnteringState(args, isCurrentPlayerActive) {
        this.bga.statusBar.setTitle(isCurrentPlayerActive
            ? _('${you} must choose an attraction to auction')
            : _('${actplayer} must choose an attraction to auction')
        );
        this.game.handSelectionActive = isCurrentPlayerActive;
        document.getElementById('bigtop-hand-area').classList.toggle('bigtop-selectable', isCurrentPlayerActive);
    }

    onLeavingState() {
        this.game.handSelectionActive = false;
        document.getElementById('bigtop-hand-area').classList.remove('bigtop-selectable');
    }
}

class Auction {
    constructor(game, bga) {
        this.game = game;
        this.bga = bga;
    }

    onEnteringState(args, isCurrentPlayerActive) {
        this.game.updateBid(args.currentBid, args.highBidder);
        this.bga.statusBar.setTitle(isCurrentPlayerActive
            ? _('${you} must increase the bid or pass')
            : _('${actplayer} must increase the bid or pass')
        );

        const controls = document.getElementById('bigtop-bid-controls');
        controls.hidden = !isCurrentPlayerActive;
        if (!isCurrentPlayerActive) {
            return;
        }

        const playerId = this.bga.players.getCurrentPlayerId();
        const maxBid = this.game.moneyCounters[playerId]?.getValue() ?? 0;
        const input = document.getElementById('bigtop-bid-input');
        input.min = args.minBid;
        input.max = maxBid;
        input.value = Math.min(args.minBid, maxBid);

        if (args.minBid <= maxBid) {
            this.bga.statusBar.addActionButton(_('Bid'), () => {
                this.bga.actions.performAction('actBid', { bid: Number(input.value) });
            });
        }
        this.bga.statusBar.addActionButton(
            _('Pass'),
            () => this.bga.actions.performAction('actPass'),
            { color: 'secondary' },
        );
    }

    onLeavingState() {
        document.getElementById('bigtop-bid-controls').hidden = true;
    }
}

class PlaceCoins {
    constructor(game, bga) {
        this.game = game;
        this.bga = bga;
    }

    onEnteringState(args, isCurrentPlayerActive) {
        this.bga.statusBar.setTitle(isCurrentPlayerActive
            ? _('${you} may place or take back coins, then continue')
            : _('${actplayer} may place or take back coins')
        );
        this.game.setCoinMode(isCurrentPlayerActive ? 'place' : null, args.placeable);
        if (isCurrentPlayerActive) {
            this.bga.statusBar.addActionButton(
                _('Continue auction'),
                () => this.bga.actions.performAction('actFinishPlacement'),
            );
        }
    }

    onLeavingState() {
        this.game.setCoinMode(null, {});
    }
}

class Payment {
    constructor(game, bga) {
        this.game = game;
        this.bga = bga;
    }

    onEnteringState(args, isCurrentPlayerActive) {
        this.bga.statusBar.setTitle(isCurrentPlayerActive
            ? _('${you} must pay the winning bid')
            : _('${actplayer} must pay the winning bid')
        );
        this.game.setCoinMode(isCurrentPlayerActive ? 'payment' : null, {});
        if (isCurrentPlayerActive) {
            const label = _('Pay $${bid}').replace('${bid}', args.bid);
            this.bga.statusBar.addActionButton(
                label,
                () => this.bga.actions.performAction('actPay'),
            );
        }
    }

    onLeavingState() {
        this.game.setCoinMode(null, {});
    }
}

export class Game {
    constructor(bga) {
        this.bga = bga;
        this.bga.states.register('PlayerTurn', new PlayerTurn(this, bga));
        this.bga.states.register('Auction', new Auction(this, bga));
        this.bga.states.register('PlaceCoins', new PlaceCoins(this, bga));
        this.bga.states.register('Payment', new Payment(this, bga));
        this.moneyCounters = {};
        this.handSelectionActive = false;
        this.coinMode = null;
        this.placeable = {};
    }

    /** @param {bigtopGamedatas} gamedatas */
    setup(gamedatas) {
        this.gamedatas = gamedatas;
        this.cardTypes = gamedatas.cardTypes;

        this.bga.gameArea.getElement().insertAdjacentHTML('beforeend', `
            <div id="bigtop-game">
                <div id="bigtop-common">
                    <div id="bigtop-deck" class="bigtop-common-section">
                        <h3>${_('Attraction deck')}</h3>
                        <div id="bigtop-deck-card"></div>
                        <div class="bigtop-deck-count">${_('Cards')}: <span id="bigtop-deck-count"></span></div>
                    </div>
                    <div class="bigtop-common-section">
                        <h3>${_('Current auction')}</h3>
                        <div id="bigtop-auction"></div>
                        <div id="bigtop-current-bid"></div>
                        <div id="bigtop-bid-controls" hidden>
                            <input id="bigtop-bid-input" type="number" step="1" aria-label="${_('Your bid')}">
                        </div>
                    </div>
                    <div class="bigtop-common-section">
                        <h3>${_('Bank')}</h3>
                        <div class="bigtop-bank"><span id="bigtop-bank-counter"></span> $</div>
                    </div>
                </div>
                <div id="bigtop-player-tables"></div>
                <div id="bigtop-hand-area">
                    <h3>${_('Your hand')}</h3>
                    <div id="bigtop-hand"></div>
                    <div class="bigtop-behind">${_('Coins behind your screen')}: <span id="bigtop-behind-counter"></span> $</div>
                </div>
            </div>
        `);

        document.getElementById('bigtop-deck-card').appendChild(this.createCard({ id: 0, type: 0 }));
        document.getElementById('bigtop-deck-count').textContent = gamedatas.deckCount;

        this.bankCounter = new ebg.counter();
        this.bankCounter.create('bigtop-bank-counter', {
            value: gamedatas.bank,
            tableCounter: 'bank',
        });

        Object.values(gamedatas.players).forEach(player => {
            const playerId = Number(player.id);
            if (player.money !== undefined) {
                this.bga.playerPanels.getElement(playerId).insertAdjacentHTML('beforeend', `
                    <span id="bigtop-money-counter-${playerId}"></span> $
                `);
                const counter = new ebg.counter();
                counter.create(`bigtop-money-counter-${playerId}`, {
                    value: player.money,
                    playerCounter: 'money',
                    playerId,
                });
                this.moneyCounters[playerId] = counter;
            }

            const table = document.createElement('section');
            table.id = `bigtop-player-table-${playerId}`;
            table.className = 'bigtop-player-table';
            const title = document.createElement('h3');
            title.textContent = player.name;
            table.appendChild(title);
            table.insertAdjacentHTML('beforeend', `
                <div class="bigtop-card-row" id="bigtop-attractions-${playerId}"></div>
                <div class="bigtop-card-row" id="bigtop-completed-${playerId}"></div>
            `);
            document.getElementById('bigtop-player-tables').appendChild(table);
        });

        gamedatas.attractions.forEach(card => {
            document.getElementById(`bigtop-attractions-${card.locationArg}`).appendChild(this.createCard(card));
        });
        gamedatas.completed.forEach(card => {
            document.getElementById(`bigtop-completed-${card.locationArg}`).appendChild(this.createCard(card));
        });
        gamedatas.auction.forEach(card => {
            document.getElementById('bigtop-auction').appendChild(this.createCard(card));
        });
        gamedatas.hand.forEach(card => {
            document.getElementById('bigtop-hand').appendChild(this.createCard(card));
        });

        document.getElementById('bigtop-hand').addEventListener('click', event => this.onHandClick(event));
        document.getElementById('bigtop-player-tables').addEventListener('click', event => this.onCoinSlotClick(event));
        this.updateBehindMoney();
        this.bga.notifications.setupPromiseNotifications();
    }

    onHandClick(event) {
        if (!this.handSelectionActive) {
            return;
        }
        const card = event.target.closest('[data-card-id]');
        if (!card) {
            return;
        }
        this.bga.actions.performAction('actAuctionCard', { cardId: Number(card.dataset.cardId) });
    }

    onCoinSlotClick(event) {
        const slot = event.target.closest('.bigtop-coin-slot.bigtop-actionable');
        if (!slot || !this.coinMode) {
            return;
        }

        const card = slot.closest('[data-card-id]');
        const cardId = Number(card.dataset.cardId);
        const circleIndex = Number(slot.dataset.circleIndex);
        if (this.coinMode === 'payment' || slot.classList.contains('bigtop-covered')) {
            this.bga.actions.performAction('actRemoveCoin', { cardId, circleIndex });
        } else {
            this.bga.actions.performAction('actPlaceCoin', { cardId, circleIndex });
        }
    }

    setCoinMode(mode, placeable) {
        this.coinMode = mode;
        this.placeable = placeable || {};
        document.querySelectorAll('.bigtop-coin-slot').forEach(slot => {
            slot.classList.remove('bigtop-actionable');
        });
        if (!mode) {
            return;
        }

        const playerId = this.bga.players.getCurrentPlayerId();
        const root = document.getElementById(`bigtop-attractions-${playerId}`);
        if (mode === 'place') {
            Object.entries(this.placeable).forEach(([cardId, circleIndexes]) => {
                circleIndexes.forEach(circleIndex => {
                    root.querySelector(`[data-card-id="${cardId}"] [data-circle-index="${circleIndex}"]`)
                        ?.classList.add('bigtop-actionable');
                });
            });
        }
        root.querySelectorAll('.bigtop-coin-slot.bigtop-covered').forEach(slot => {
            slot.classList.add('bigtop-actionable');
        });
    }

    createCard(card) {
        const type = Number(card.type);
        const element = document.createElement('div');
        element.className = 'bigtop-card-frame';
        element.dataset.cardId = card.id;
        element.dataset.cardType = type;

        const art = document.createElement('div');
        const spriteIndex = type === 0 ? 4 : type <= 30 ? type + 5 : type === 35 ? 5 : type - 31;
        const column = spriteIndex % 6;
        const row = Math.floor(spriteIndex / 6);
        art.className = 'bigtop-card';
        art.style.backgroundImage = `url(${this.bga.images.getImgUrl('cards.png')})`;
        art.style.backgroundPosition = `${-column * 180}px ${-row * 252}px`;
        art.setAttribute('role', 'img');
        art.setAttribute('aria-label', type === 0 ? _('Face-down attraction card') : `${_('Card')} ${type}`);
        element.appendChild(art);

        const slots = document.createElement('div');
        slots.className = 'bigtop-coin-slots';
        const bids = this.cardTypes[type]?.bids ?? [];
        bids.forEach((bid, circleIndex) => {
            const slot = document.createElement('button');
            slot.type = 'button';
            slot.className = 'bigtop-coin-slot';
            slot.dataset.circleIndex = circleIndex;
            slot.textContent = bid;
            slots.appendChild(slot);
        });
        element.appendChild(slots);
        this.updateCardElement(element, card);

        return element;
    }

    updateCardElement(element, card) {
        const covered = (card.covered ?? []).map(Number);
        element.dataset.location = card.location;
        element.dataset.locationArg = card.locationArg;
        element.classList.toggle('bigtop-complete', card.location === 'completed');
        element.querySelectorAll('.bigtop-coin-slot').forEach(slot => {
            const isCovered = covered.includes(Number(slot.dataset.circleIndex));
            slot.classList.toggle('bigtop-covered', isCovered);
            slot.setAttribute('aria-label', `${_('Bid circle')} ${slot.textContent}${isCovered ? ` — ${_('covered')}` : ''}`);
        });
    }

    updateCard(card) {
        const element = document.querySelector(`.bigtop-card-frame[data-card-id="${Number(card.id)}"]`);
        if (element) {
            this.updateCardElement(element, card);
        }
    }

    moveCard(card, targetId) {
        let element = document.querySelector(`.bigtop-card-frame[data-card-id="${Number(card.id)}"]`);
        if (!element) {
            element = this.createCard(card);
        } else {
            this.updateCardElement(element, card);
        }
        document.getElementById(targetId).appendChild(element);
    }

    getBehindMoney() {
        const playerId = this.bga.players.getCurrentPlayerId();
        const total = this.moneyCounters[playerId]?.getValue() ?? 0;
        const onCards = document.querySelectorAll(`#bigtop-attractions-${playerId} .bigtop-coin-slot.bigtop-covered`).length;
        return total - onCards;
    }

    updateBehindMoney() {
        document.getElementById('bigtop-behind-counter').textContent = this.getBehindMoney();
    }

    updateBid(currentBid, highBidder) {
        const element = document.getElementById('bigtop-current-bid');
        const bid = Number(currentBid);
        if (!bid) {
            element.textContent = _('No bids yet');
            return;
        }
        const playerName = this.gamedatas.players[highBidder]?.name ?? '';
        element.textContent = `${_('Current bid')}: $${bid} — ${playerName}`;
    }

    notif_cardDrawn(args) {
        document.getElementById('bigtop-deck-count').textContent = args.deckCount;
        if (args.card) {
            document.getElementById('bigtop-hand').appendChild(this.createCard(args.card));
        }
    }

    notif_gameEndDrawn(args) {
        document.getElementById('bigtop-deck-count').textContent = args.deckCount;
    }

    notif_auctionCard(args) {
        this.moveCard(args.card, 'bigtop-auction');
        this.updateBid(0, null);
    }

    notif_bidMade(args) {
        this.updateBid(args.bid, args.player_id);
    }

    notif_coinPlaced(args) {
        this.updateCard(args.card);
        this.updateBehindMoney();
    }

    notif_coinRemoved(args) {
        this.updateCard(args.card);
        this.updateBehindMoney();
    }

    notif_attractionCompleted(args) {
        this.moveCard(args.card, `bigtop-completed-${args.player_id}`);
        this.updateBehindMoney();
    }

    notif_auctionWon(args) {
        this.moveCard(args.card, `bigtop-attractions-${args.player_id}`);
        this.updateBehindMoney();
    }

    notif_auctionDiscarded(args) {
        document.querySelector(`.bigtop-card-frame[data-card-id="${Number(args.card.id)}"]`)?.remove();
    }
}
