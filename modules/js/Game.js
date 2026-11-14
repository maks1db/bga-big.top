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
        const bidValue = document.getElementById('bigtop-bid-value');
        bidValue.dataset.min = args.minBid;
        bidValue.dataset.max = maxBid;
        bidValue.dataset.value = Math.min(args.minBid, maxBid);
        this.game.updateBidStepper();

        if (args.minBid <= maxBid) {
            this.bga.statusBar.addActionButton(_('Bid'), () => {
                this.bga.actions.performAction('actBid', { bid: Number(bidValue.dataset.value) });
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
            const label = _('Pay ${bid}').replace('${bid}', args.bid);
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

class EffectCoins {
    constructor(game, bga) {
        this.game = game;
        this.bga = bga;
    }

    onEnteringState(args, isCurrentPlayerActive) {
        this.bga.statusBar.setTitle(isCurrentPlayerActive
            ? _('${you} must place coins for the immediate effect')
            : _('${actplayer} must place coins for an immediate effect')
        );
        this.game.setCoinMode(isCurrentPlayerActive ? 'effect' : null, args.targets);
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
        this.bga.states.register('EffectCoins', new EffectCoins(this, bga));
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
                    <div id="bigtop-scene" class="bigtop-scene" aria-hidden="true">
                        <div class="bigtop-layer bigtop-tent" data-depth="4"></div>
                        <div class="bigtop-layer bigtop-tent-wall" data-depth="6"></div>
                        <div class="bigtop-layer bigtop-garland" data-depth="9"></div>
                        <div class="bigtop-layer bigtop-floor" data-depth="3"></div>
                        <div class="bigtop-layer" data-depth="12">
                            <div class="bigtop-ring-wrap">
                                <div class="bigtop-arena"></div>
                            </div>
                        </div>
                        <div class="bigtop-layer bigtop-beam bigtop-b1" data-depth="7"></div>
                        <div class="bigtop-layer bigtop-beam bigtop-b2" data-depth="7"></div>
                        <div class="bigtop-layer bigtop-beam bigtop-b3" data-depth="5"></div>
                        <div class="bigtop-layer bigtop-dust-layer" data-depth="14"></div>
                        <div class="bigtop-layer bigtop-curtain bigtop-left" data-depth="16"></div>
                        <div class="bigtop-layer bigtop-curtain bigtop-right" data-depth="16"></div>
                        <div class="bigtop-layer bigtop-valance" data-depth="18"></div>
                        <div class="bigtop-layer bigtop-fringe" data-depth="18"></div>
                        <div class="bigtop-layer bigtop-vignette"></div>
                    </div>
                    <div id="bigtop-deck" class="bigtop-common-section">
                        <h3>${_('Attraction deck')}</h3>
                        <div id="bigtop-deck-card"></div>
                        <div class="bigtop-deck-count">${_('Cards')}: <span id="bigtop-deck-count"></span></div>
                    </div>
                    <div id="bigtop-auction-area" class="bigtop-common-section">
                        <h3>${_('Current auction')}</h3>
                        <div id="bigtop-auction"></div>
                        <div id="bigtop-current-bid"></div>
                        <div id="bigtop-bid-controls" hidden>
                            <button id="bigtop-bid-decrease" class="bigtop-bid-step" type="button" aria-label="${_('Decrease bid')}">−</button>
                            <output id="bigtop-bid-value" aria-label="${_('Your bid')}" aria-live="polite"></output>
                            <button id="bigtop-bid-increase" class="bigtop-bid-step" type="button" aria-label="${_('Increase bid')}">+</button>
                        </div>
                    </div>
                    <div id="bigtop-bank-area" class="bigtop-common-section">
                        <h3>${_('Bank')}</h3>
                        <div class="bigtop-bank"><span id="bigtop-bank-counter"></span><span class="bigtop-bank-coin" role="img" aria-label="${_('Coins')}"></span></div>
                    </div>
                </div>
                <div id="bigtop-hand-area">
                    <h3>${_('Your hand')}</h3>
                    <div id="bigtop-hand"></div>
                    <div class="bigtop-behind" aria-label="${_('Coins behind your screen')}"><span id="bigtop-behind-counter"></span> <span class="bigtop-money-icon" role="img" aria-label="${_('Coins')}"></span></div>
                </div>
                <div id="bigtop-player-tables">
                    <button id="bigtop-player-previous" class="bigtop-player-arrow bigtop-player-previous" type="button" hidden>‹</button>
                    <button id="bigtop-player-next" class="bigtop-player-arrow bigtop-player-next" type="button" hidden>›</button>
                    <div id="bigtop-player-track"></div>
                </div>
            </div>
        `);

        this.setupArena();
        this.setupBidStepper();
        document.getElementById('bigtop-deck-card').appendChild(this.createCard({ id: 0, type: 0 }));
        document.getElementById('bigtop-deck-count').textContent = gamedatas.deckCount;

        this.bankCounter = new ebg.counter();
        this.bankCounter.create('bigtop-bank-counter', {
            value: gamedatas.bank,
            tableCounter: 'bank',
        });

        const playerIds = Object.values(gamedatas.players).map(player => Number(player.id));
        this.visiblePlayerId = this.bga.players.getCurrentPlayerId();
        if (!playerIds.includes(this.visiblePlayerId)) {
            this.visiblePlayerId = playerIds[0];
        }
        this.playerIds = [
            this.visiblePlayerId,
            ...playerIds.filter(playerId => playerId !== this.visiblePlayerId),
        ];
        Object.values(gamedatas.players).forEach(player => {
            const playerId = Number(player.id);
            if (player.money !== undefined) {
                this.bga.playerPanels.getElement(playerId).insertAdjacentHTML('beforeend', `
                    <span class="bigtop-player-bank">${_('Bank')}: <span id="bigtop-money-counter-${playerId}"></span> <span class="bigtop-money-icon" role="img" aria-label="${_('Coins')}"></span></span>
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
            if (player.color) {
                title.style.color = `#${player.color.replace(/^#/, '')}`;
            }
            table.appendChild(title);
            table.insertAdjacentHTML('beforeend', `
                <div class="bigtop-card-row" id="bigtop-attractions-${playerId}"></div>
                <div class="bigtop-card-row" id="bigtop-completed-${playerId}"></div>
            `);
            document.getElementById('bigtop-player-track').appendChild(table);
        });

        this.playerIds.forEach(playerId => {
            document.getElementById('bigtop-player-track').appendChild(
                document.getElementById(`bigtop-player-table-${playerId}`),
            );
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
        this.setupPlayerSlider();
        this.updateBehindMoney();
        this.bga.notifications.setupPromiseNotifications();
    }

    setupPlayerSlider() {
        if (!this.playerIds.includes(this.visiblePlayerId)) {
            this.visiblePlayerId = this.playerIds[0];
        }
        document.getElementById('bigtop-player-previous').addEventListener('click', () => this.slidePlayer(-1));
        document.getElementById('bigtop-player-next').addEventListener('click', () => this.slidePlayer(1));
        this.updatePlayerSlider();
    }

    slidePlayer(direction) {
        const currentIndex = this.playerIds.indexOf(this.visiblePlayerId);
        const nextIndex = Math.max(0, Math.min(this.playerIds.length - 1, currentIndex + direction));
        if (nextIndex === currentIndex) {
            return;
        }
        this.visiblePlayerId = this.playerIds[nextIndex];
        this.updatePlayerSlider();
    }

    updatePlayerSlider() {
        const currentIndex = this.playerIds.indexOf(this.visiblePlayerId);
        document.getElementById('bigtop-player-track').style.transform = `translateX(-${currentIndex * 100}%)`;
        this.playerIds.forEach(playerId => {
            const table = document.getElementById(`bigtop-player-table-${playerId}`);
            const isVisible = playerId === this.visiblePlayerId;
            table.inert = !isVisible;
            table.setAttribute('aria-hidden', String(!isVisible));
        });

        const updateArrow = (elementId, playerId, label) => {
            const arrow = document.getElementById(elementId);
            arrow.hidden = playerId === undefined;
            if (playerId !== undefined) {
                const text = `${label}: ${this.gamedatas.players[playerId].name}`;
                arrow.setAttribute('aria-label', text);
                arrow.title = text;
            }
        };
        updateArrow('bigtop-player-previous', this.playerIds[currentIndex - 1], _('Previous player'));
        updateArrow('bigtop-player-next', this.playerIds[currentIndex + 1], _('Next player'));
    }

    setupBidStepper() {
        const bidValue = document.getElementById('bigtop-bid-value');
        document.getElementById('bigtop-bid-decrease').addEventListener('click', () => {
            this.changeBid(Number(bidValue.dataset.value) - 1);
        });
        document.getElementById('bigtop-bid-increase').addEventListener('click', () => {
            this.changeBid(Number(bidValue.dataset.value) + 1);
        });
    }

    clampBid(value) {
        const bidValue = document.getElementById('bigtop-bid-value');
        const min = Number(bidValue.dataset.min);
        const max = Number(bidValue.dataset.max);
        const normalized = Number.isFinite(value) ? Math.round(value) : min;
        return Math.min(max, Math.max(min, normalized));
    }

    updateBidStepper() {
        const bidValue = document.getElementById('bigtop-bid-value');
        const value = this.clampBid(Number(bidValue.dataset.value));
        bidValue.dataset.value = value;
        bidValue.textContent = value;
        this.bidValue = value;
        document.getElementById('bigtop-bid-decrease').disabled = value <= Number(bidValue.dataset.min);
        document.getElementById('bigtop-bid-increase').disabled = value >= Number(bidValue.dataset.max);
    }

    changeBid(value) {
        const bidValue = document.getElementById('bigtop-bid-value');
        const previousValue = this.bidValue ?? Number(bidValue.dataset.value);
        bidValue.dataset.value = this.clampBid(value);
        this.updateBidStepper();
        if (this.bidValue !== previousValue) {
            this.animateBidValue(this.bidValue > previousValue ? 1 : -1);
        }
    }

    animateBidValue(direction) {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }
        const bidValue = document.getElementById('bigtop-bid-value');
        const scale = direction > 0 ? 1.35 : 0.7;
        bidValue.getAnimations().forEach(animation => animation.cancel());
        bidValue.animate([
            { transform: 'scale(1)' },
            { transform: `scale(${scale})`, offset: 0.45 },
            { transform: 'scale(1)' },
        ], {
            duration: 320,
            easing: 'ease-out',
        });
    }

    setupArena() {
        const scene = document.getElementById('bigtop-scene');
        const arena = scene.querySelector('.bigtop-arena');
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        for (let i = 0; i < 46; i++) {
            const angle = (i / 46) * Math.PI * 2 - Math.PI / 2;
            const bulb = document.createElement('i');
            bulb.className = `bigtop-bulb${i % 4 === 2 ? ' bigtop-alt' : ''}`;
            bulb.style.left = `${50 + Math.cos(angle) * 50.6}%`;
            bulb.style.top = `${50 + Math.sin(angle) * 50.6}%`;
            bulb.style.animationDelay = `${-(i * 0.13)}s`;
            bulb.style.animationDuration = `${1.6 + (i % 5) * 0.17}s`;
            arena.appendChild(bulb);
        }

        const garland = scene.querySelector('.bigtop-garland');
        const strands = [
            { x0: 4, y0: 10, x1: 50, y1: 26, n: 16 },
            { x0: 96, y0: 10, x1: 50, y1: 26, n: 16 },
            { x0: 12, y0: 22, x1: 50, y1: 34, n: 12 },
            { x0: 88, y0: 22, x1: 50, y1: 34, n: 12 },
        ];
        strands.forEach((strand, strandIndex) => {
            for (let i = 0; i <= strand.n; i++) {
                const t = i / strand.n;
                const bulb = document.createElement('i');
                bulb.className = `bigtop-bulb${(i + strandIndex) % 3 === 1 ? ' bigtop-alt' : ''}`;
                bulb.style.left = `${strand.x0 + (strand.x1 - strand.x0) * t}%`;
                bulb.style.top = `${strand.y0 + (strand.y1 - strand.y0) * t + Math.sin(t * Math.PI) * 7}%`;
                bulb.style.animationDelay = `${-(i * 0.31 + strandIndex)}s`;
                bulb.style.animationDuration = `${1.5 + ((i + strandIndex) % 4) * 0.22}s`;
                garland.appendChild(bulb);
            }
        });

        if (!reduceMotion) {
            const dustLayer = scene.querySelector('.bigtop-dust-layer');
            for (let i = 0; i < 30; i++) {
                const particle = document.createElement('i');
                const size = 2 + Math.random() * 3;
                particle.className = 'bigtop-dust';
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                particle.style.left = `${18 + Math.random() * 64}%`;
                particle.style.top = `${38 + Math.random() * 52}%`;
                particle.style.animationDuration = `${9 + Math.random() * 14}s`;
                particle.style.animationDelay = `${-Math.random() * 18}s`;
                dustLayer.appendChild(particle);
            }
        }

        if (reduceMotion || !window.matchMedia('(pointer: fine)').matches) {
            return;
        }
        const layers = Array.from(scene.querySelectorAll('.bigtop-layer[data-depth]'));
        let targetX = 0;
        let targetY = 0;
        let currentX = 0;
        let currentY = 0;
        let animationFrame = null;
        const animate = () => {
            currentX += (targetX - currentX) * 0.06;
            currentY += (targetY - currentY) * 0.06;
            layers.forEach(layer => {
                const depth = Number(layer.dataset.depth) || 0;
                layer.style.transform = `translate3d(${currentX * depth}px, ${currentY * depth * 0.6}px, 0)`;
            });
            if (Math.abs(targetX - currentX) > 0.05 || Math.abs(targetY - currentY) > 0.05) {
                animationFrame = requestAnimationFrame(animate);
            } else {
                animationFrame = null;
            }
        };
        document.getElementById('bigtop-game').addEventListener('pointermove', event => {
            const bounds = scene.getBoundingClientRect();
            targetX = ((event.clientX - bounds.left) / bounds.width - 0.5) * 2;
            targetY = ((event.clientY - bounds.top) / bounds.height - 0.5) * 2;
            if (!animationFrame) {
                animationFrame = requestAnimationFrame(animate);
            }
        }, { passive: true });
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
        if (this.coinMode === 'effect') {
            this.bga.actions.performAction('actEffectPlaceCoin', { cardId, circleIndex });
        } else if (this.coinMode === 'payment' || slot.classList.contains('bigtop-covered')) {
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
            slot.tabIndex = -1;
        });
        if (!mode) {
            return;
        }

        const playerId = this.bga.players.getCurrentPlayerId();
        const root = document.getElementById(`bigtop-attractions-${playerId}`);
        if (mode === 'place' || mode === 'effect') {
            Object.entries(this.placeable).forEach(([cardId, circleIndexes]) => {
                circleIndexes.forEach(circleIndex => {
                    const slot = root.querySelector(`[data-card-id="${cardId}"] [data-circle-index="${circleIndex}"]`);
                    if (slot) {
                        slot.classList.add('bigtop-actionable');
                        slot.tabIndex = 0;
                    }
                });
            });
        }
        if (mode === 'place' || mode === 'payment') {
            root.querySelectorAll('.bigtop-coin-slot.bigtop-covered').forEach(slot => {
                slot.classList.add('bigtop-actionable');
                slot.tabIndex = 0;
            });
        }
    }

    getCoinLayout(type, circleCount) {
        if (type >= 31 && type <= 34) {
            return {
                size: 50,
                points: [[50, 172], [130, 172], [50, 222], [130, 222]],
            };
        }
        const layouts = {
            2: { size: 50, points: [[50, 220], [130, 220]] },
            3: { size: 50, points: [[40, 215], [90, 215], [140, 215]] },
            4: { size: 44, points: [[50, 182], [130, 182], [50, 231], [130, 231]] },
            5: { size: 44, points: [[50, 181], [130, 181], [28, 230], [90, 230], [152, 230]] },
            9: {
                size: 46,
                points: [
                    [40, 124], [90, 124], [140, 124],
                    [40, 175], [90, 175], [140, 175],
                    [40, 226], [90, 226], [140, 226],
                ],
            },
        };
        return layouts[circleCount] ?? { size: 44, points: [] };
    }

    getCardHelp(type) {
        const definition = this.cardTypes[type];
        if (!definition) {
            return null;
        }
        const effects = {
            draw: _('Immediate effect: draw the top deck card and complete it immediately. Its immediate effect also applies.'),
            bankTwo: _('Immediate effect: place 2 coins from the bank on any free circles of your incomplete attractions.'),
            ownCoin: _('Immediate effect: place one coin from behind your screen on any free circle of your incomplete attractions.'),
        };
        const clowns = {
            perCard: _('Final scoring: 1 point for each of your completed attractions.'),
            perStarCard: _('Final scoring: 2 points for each completed attraction with a star.'),
            perThreeCircles: _('Final scoring: 1 point for every 3 circles on your completed attractions.'),
            perTwoCoins: _('Final scoring: 1 point for every 2 coins instead of 1 point for every 5 coins.'),
            perIncompleteCoin: _('Final scoring: 2 points for each coin on your incomplete attractions.'),
            perLowCard: _('Final scoring: 2 points for each other completed attraction worth no more than 5 points.'),
        };
        return [effects[definition.effect], clowns[definition.clown]].filter(Boolean).join(' ') || null;
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
        const layout = this.getCoinLayout(type, bids.length);
        slots.style.setProperty('--bigtop-coin-size', `${layout.size}px`);
        bids.forEach((bid, circleIndex) => {
            const [x, y] = layout.points[circleIndex];
            const slot = document.createElement('button');
            slot.type = 'button';
            slot.className = 'bigtop-coin-slot';
            slot.dataset.circleIndex = circleIndex;
            slot.dataset.bid = bid;
            slot.tabIndex = -1;
            slot.style.left = `${x / 180 * 100}%`;
            slot.style.top = `${y / 252 * 100}%`;
            slots.appendChild(slot);
        });
        element.appendChild(slots);

        const help = this.getCardHelp(type);
        if (help) {
            element.classList.add('bigtop-card-has-help');
            element.tabIndex = 0;
            const icon = document.createElement('span');
            icon.className = 'bigtop-card-help-icon';
            icon.textContent = '!';
            icon.setAttribute('aria-hidden', 'true');
            element.appendChild(icon);

            const tooltip = document.createElement('div');
            tooltip.id = `bigtop-card-help-${card.id}`;
            tooltip.className = 'bigtop-card-tooltip';
            tooltip.setAttribute('role', 'tooltip');
            tooltip.textContent = help;
            element.appendChild(tooltip);
            element.setAttribute('aria-describedby', tooltip.id);
        }
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
            slot.setAttribute('aria-label', `${_('Bid circle')} ${slot.dataset.bid}${isCovered ? ` — ${_('covered')}` : ''}`);
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
        const player = this.gamedatas.players[highBidder];
        const playerName = document.createElement('span');
        playerName.textContent = player?.name ?? '';
        if (player?.color) {
            playerName.style.color = `#${player.color.replace(/^#/, '')}`;
        }
        element.replaceChildren(
            document.createTextNode(`${_('Current bid')}: ${bid} — `),
            playerName,
        );
    }

    notif_moneyChanged(args) {
        const playerId = this.bga.players.getCurrentPlayerId();
        this.moneyCounters[playerId]?.toValue(Number(args.value));
        this.updateBehindMoney();
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

    notif_effectCardDrawn(args) {
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
