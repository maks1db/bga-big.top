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

export class Game {
    constructor(bga) {
        this.bga = bga;
        this.bga.states.register('PlayerTurn', new PlayerTurn(this, bga));
        this.bga.states.register('Auction', new Auction(this, bga));
        this.moneyCounters = {};
        this.handSelectionActive = false;
    }

    /** @param {bigtopGamedatas} gamedatas */
    setup(gamedatas) {
        this.gamedatas = gamedatas;

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

    createCard(card) {
        const element = document.createElement('div');
        const type = Number(card.type);
        const spriteIndex = type === 0 ? 4 : type <= 30 ? type + 5 : type === 35 ? 5 : type - 31;
        const column = spriteIndex % 6;
        const row = Math.floor(spriteIndex / 6);

        element.className = 'bigtop-card';
        element.dataset.cardId = card.id;
        element.dataset.cardType = type;
        element.style.backgroundImage = `url(${this.bga.images.getImgUrl('cards.png')})`;
        element.style.backgroundPosition = `${-column * 180}px ${-row * 252}px`;
        element.setAttribute('role', 'img');
        element.setAttribute('aria-label', type === 0 ? _('Face-down attraction card') : `${_('Card')} ${type}`);

        return element;
    }

    moveCard(card, targetId) {
        let element = document.querySelector(`.bigtop-card[data-card-id="${Number(card.id)}"]`);
        if (!element) {
            element = this.createCard(card);
        }
        document.getElementById(targetId).appendChild(element);
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

    notif_auctionWon(args) {
        this.moveCard(args.card, `bigtop-attractions-${args.player_id}`);
    }

    notif_auctionDiscarded(args) {
        document.querySelector(`.bigtop-card[data-card-id="${Number(args.card.id)}"]`)?.remove();
    }
}
