/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * SevenKnightsBewitched implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

const gameName = "sevenknightsbewitched";

function clearTag(tag) {
    for (const element of document.querySelectorAll(`.${tag}`)) {
        element.classList.remove(tag);
    }
}

function createElement(parent, html) {
    const element = parent.appendChild(
        document.createElement("div"));
    element.outerHTML = html;
    return parent.lastElementChild;
}

function createPlayer(index, angle, player) {
    const radians = angle / 180 * Math.PI;
    const coords = [Math.cos(radians), -Math.sin(radians)];
    const style = `--index: ${index}; --cx: ${coords[0]}; --cy: ${coords[1]}`;

    const id = player ? player.id : "none";
    const name = player ?
        `<div class="mur-player-name" style="color: #${player.color}">${player.name}</div>` :
        "";

    return `<div id="mur-player-${id}" class="mur-player" style="${style}" data-player="${id}">
        ${name}
    </div>`;
}

function createTiles(tiles, token) {
    const settings = [];
    if (token !== null) {
        token = parseInt(token);
        settings.push(`--token-x: ${token % 4}; --token-y: ${Math.floor(token / 4)}`);
    }

    const tileElements = tiles.map(tile => {
        const character = tile.character !== null ?
            [`--character: ${tile.character}`] : [];
        const style = [character, ...settings].join("; ");

        const classes = [];
        if (token !== null) {
            classes.push("mur-owned");
        }
        if (tile.character !== null) {
            classes.push("mur-flipped");
        }

        return `<div id="mur-tile-${tile.id}" 
                    class="mur-tile ${classes.join(" ")}" 
                    style="${style}"
                    data-id="${tile.id}">
            <div class="mur-tile-back"></div>                
            <div class="mur-tile-front"></div>
            <div class="mur-tile-side"></div>
        </div>`
    });

    return `<div class="mur-tile-container">
        ${tileElements.join("")}
    </div>`;
}

function createSpareTile(index) {
    return `<div id="mur-space-tile-${index}" 
                class="mur-tile mur-flipped" 
                style="--character: ${index}">               
        <div class="mur-tile-front"></div>
    </div>`
}

function createPlaceholder(index) {
    return `<div class="mur-placeholder" data-number="${index + 1}"></div>`
}

function createQuestionDialog() {
    const questionText = _("Is the tile number one of...");
    const numbers = [1, 2, 3, 4, 5, 6, 7].map(n =>
        `<div class="mur-single-number" data-number="${n}"></div>`);
    return `<div>
        <div class="mur-question-text">${questionText}</div>
        <div class="mur-number-list">
            ${numbers.join("")}
        </div>
        <div id="mur-question-dialog-buttons"></div>
    </div>`;
}

define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter"
], (dojo, declare) => declare(`bgagame.${gameName}`, ebg.core.gamegui, {
    constructor() {
        console.log(`${gameName} constructor`);
        this.selectedTile = null;
        this.selectedPlayer = null;
    },

    setup(data) {
        console.log("Starting game setup");

        const players = data.players;
        const playerIds = Object.keys(players);
        const playerCount = playerIds.length;

        const playArea = document.getElementById("mur-play-area");

        const currentIndex = parseInt(players[this.getCurrentPlayerId().toString()].no) - 1

        function sortKey(player) {
            return (parseInt(player.no) - 1 - currentIndex + playerCount) % playerCount;
        }

        const freeTiles = data.tiles.filter(tile => tile.player_id === null);
        const areaCount = freeTiles.length > 0 ? playerCount + 1 : playerCount;

        function createPlayerArea(player, tiles, index) {
            const angle = 90 + index * 360 / areaCount;
            const playerArea = createElement(playArea,
                createPlayer(index, angle, player));

            if (player) {
                playerArea.addEventListener('click', event => {
                    event.stopPropagation();
                    console.log("Player click");
                    this.onPlayerClick(player);
                });
            }

            const tileContainer = createElement(playerArea,
                createTiles(tiles, player && player.token));

            for (const tile of tileContainer.querySelectorAll(".mur-tile")) {
                console.log("New tile", tile);
                tile.addEventListener('click', event => {
                    event.stopPropagation();
                    console.log("Tile click");
                    this.onTileClick(tile, player && player.id);
                });
            }
        }

        for (const playerId of playerIds) {
            const player = players[playerId];
            const index = sortKey(player);

            createPlayerArea.call(this, player, data.tiles.filter(tile =>
                tile.player_id === playerId), index);
        }

        if (freeTiles.length > 0) {
            createPlayerArea.call(this, null, freeTiles, areaCount - 1);
        }

        const maxTile = data.hasWitch ?
            data.tiles.length - 2 :
            data.tiles.length - 1;

        Array.from(document.querySelectorAll(" .mur-placeholder")).forEach((place, index) => {
            if (index > maxTile) {
                createElement(place, createSpareTile(index + 1), undefined);
            }
        });

        this.setupNotifications();

        console.log("Ending game setup");
    },

    onEnteringState(stateName, args) {
        console.log(`Entering state: ${stateName}`);

        if (this.isCurrentPlayerActive()) {
            switch (stateName) {
                case "inspect": {
                    for (const tile of document.querySelectorAll(".mur-tile")) {
                        tile.classList.add("mur-selectable");
                    }
                    break;
                }
                case "question": {
                    for (const player of document.querySelectorAll(".mur-player")) {
                        player.classList.add("mur-selectable");
                    }
                    break;
                }
                case 'clientSelectTiles': {
                    for (const tile of document.querySelectorAll(".mur-tile")) {
                        tile.classList.add("mur-selectable");
                    }
                    break;
                }
            }
        }
    },

    onLeavingState(stateName) {
        console.log(`Leaving state: ${stateName}`);

        if (this.isCurrentPlayerActive()) {
            switch (stateName) {
                case "inspect":
                case "clientSelectTiles": {
                    clearTag("mur-selectable");
                    this.selectTile(null);
                    this.selectPlayer(null);
                    break;
                }
                case "question": {
                    clearTag("mur-selectable");
                }
            }
        }
    },

    onUpdateActionButtons(stateName, args) {
        console.log(`onUpdateActionButtons: ${stateName}`, args);

        if (this.isCurrentPlayerActive()) {
            switch (stateName) {
                case "inspect": {
                    this.addActionButton('mur-inspect', _("Inspect"), () => {
                        this.request("inspect", {
                            tileId: this.selectedTile.dataset.id
                        });
                    });
                    document.getElementById("mur-inspect").classList.add("disabled");
                    break;
                }
                case 'clientSelectTiles':
                    this.addActionButton('nur-cancel', _("Cancel"), () => {
                        this.restoreServerGameState();
                    }, null, null, 'red');
                    break;
                case 'answer': {
                    const that = this;
                    const answer = parseInt(args._private.answer);

                    function answerButton(id, label, answer, disable) {
                        that.addActionButton(id, label, () => {
                            that.request('answer', {answer});
                        });
                        document.getElementById(id).classList.toggle("disabled", disable);
                    }

                    answerButton("mur-answer-yes", _("Yes"), true, answer === 1);
                    answerButton("mur-answer-no", _("No"), false, answer === 2);
                    break;
                }
            }
        }
    },

    request(action, args) {
        this.ajaxcall(`/${gameName}/${gameName}/${action}.html`, {
            lock: true,
            ...args
        }, () => {});
    },

    selectItem(item, property) {
        console.log("Selecting", item);
        if (this[property]) {
            this[property].classList.remove("mur-selected");
        }
        this[property] = item;
        if (this[property]) {
            this[property].classList.add("mur-selected");
            return true;
        }
        return false;
    },

    selectPlayer(player) {
        return this.selectItem(player, "selectedPlayer");
    },

    selectTile(tile) {
        return this.selectItem(tile, "selectedTile");
    },

    questionDialog(player, tile) {
        const dialog = new ebg.popindialog();
        dialog.create("mur-question-dialog");
        dialog.setTitle(_("Choose a question"));
        dialog.setContent(createQuestionDialog());
        dialog.show();

        function ask() {
            let values = 0;
            for (const number of document.querySelectorAll(".mur-single-number.mur-selected")) {
                values = values | (0x1 << (parseInt(number.dataset.number) - 1));
            }

            this.request("ask", {
                playerId: player.dataset.player,
                tileId: tile.dataset.id,
                values
            }, () => {
                dialog.destroy();
            });
        }

        this.addActionButton("mur-question-dialog-ask", _("Ask"), ask, "mur-question-dialog-buttons");
        const askButton = document.getElementById("mur-question-dialog-ask");
        askButton.classList.add("disabled");

        for (const number of document.querySelectorAll(".mur-single-number")) {
            number.addEventListener("click", event => {
                event.stopPropagation();
                number.classList.toggle("mur-selected");
                const selectionSize = document.querySelectorAll(".mur-single-number.mur-selected").length;
                askButton.classList.toggle("disabled", selectionSize % 7 === 0);
            });
        }
    },

    onPlayerClick(player) {
        if (this.checkAction("ask", true)) {
            const element = document.getElementById(`mur-player-${player.id}`);
            if (this.selectPlayer(element)) {
                this.setClientState("clientSelectTiles", {
                    descriptionmyturn: _("${you} must select a tile to ask ${player_name} about"),
                    possibleactions: ["clientAsk"],
                    args: {player_name: `<span style="color: #${player.color}; -webkit-text-stroke: 0.5px black">${player.name}</span>`}
                });
            }
        }
    },

    onTileClick(tile) {
        if (this.checkAction("inspect", true)) {
            document.getElementById("mur-inspect").classList.toggle(
                "disabled", !this.selectTile(tile));
        } else if (this.checkAction("clientAsk", true)) {
            this.questionDialog(this.selectedPlayer, tile);
        }
    },

    setupNotifications() {
        console.log("notifications subscriptions setup");
        dojo.subscribe('inspect', this, data => {
            console.log(data);
            const tile = document.querySelector(`#mur-tile-${data.args.tileId}`);
            tile.style.setProperty("--character", data.args.character);
            tile.classList.add("mur-flipped");
        });

        this.notifqueue.setSynchronous('inspect', 500);
    }
}));