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

function createTile(id) {
    return `<div id="mur-tile-${id}" class="mur-tile" data-id="${id}" style="--index: ${id - 1}">
            <div class="mur-tile-back"></div>                
            <div class="mur-tile-front"></div>
            <div class="mur-tile-side"></div>
        </div>`;
}

function createDealtTiles(tiles, token) {
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

function createLeftoverTile(index) {
    return `<div id="mur-space-tile-${index}" 
                class="mur-tile mur-flipped mur-leftover" 
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

        const extraPlayer = data.tiles.some(tile =>
            tile.player_id === null);

        const areaCount = extraPlayer ? playerCount + 1 : playerCount;

        function createPlayerArea(player, tiles, index) {
            const angle = 90 + index * 360 / areaCount;
            const playerArea = createElement(playArea,
                createPlayer(index, angle, player));

            if (player) {
                playerArea.addEventListener("click", event => {
                    event.stopPropagation();
                    console.log("Player click");
                    this.onPlayerClick(player);
                });
            }

            const tileContainer = createElement(playerArea,
                createDealtTiles(tiles, player && player.token));

            for (const tile of tileContainer.querySelectorAll(".mur-tile")) {
                console.log("New tile", tile);
                tile.addEventListener("click", event => {
                    event.stopPropagation();
                    console.log("Tile click");
                    this.onTileClick(tile, player && player.id);
                });
            }
        }

        for (const playerId of playerIds) {
            const player = players[playerId];
            const index = sortKey(player);
            const tiles = data.tiles.filter(tile => tile.player_id === playerId);

            createPlayerArea.call(this, player, tiles, index);
        }

        if (extraPlayer) {
            const freeTiles = data.tiles.filter(tile => tile.player_id === null);
            for (const tile of freeTiles) {
                createPlayerArea.call(this, null, freeTiles, areaCount - 1);
            }
        }

        const maxTile = data.hasWitch ?
            data.tiles.length - 2 :
            data.tiles.length - 1;

        Array.from(document.querySelectorAll(" .mur-placeholder")).forEach((place, index) => {
            if (index > maxTile) {
                createElement(place, createLeftoverTile(index + 1), undefined);
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
                        if (player.dataset.player !== "none") {
                            player.classList.add("mur-selectable");
                        }
                    }
                    break;
                }
                case "clientSelectTiles": {
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
                case "clientSelectTiles":
                case "vote": {
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
                    this.addActionButton("mur-inspect", _("Inspect"), () => {
                        this.request("inspect", {
                            tileId: this.selectedTile.dataset.id
                        });
                    });
                    document.getElementById("mur-inspect").classList.add("disabled");
                    break;
                }
                case "clientSelectTiles":
                    this.addActionButton("mur-cancel", _("Cancel"), () => {
                        this.restoreServerGameState();
                    }, null, null, "gray");
                    break;
                case "answer": {
                    const that = this;
                    const answer = parseInt(args._private.answer);

                    function answerButton(id, label, answer, disable) {
                        that.addActionButton(id, label, () => {
                            that.request("answer", {answer});
                        });
                        document.getElementById(id).classList.toggle("disabled", disable);
                    }

                    answerButton("mur-answer-yes", _("Yes"), true, answer === 1);
                    answerButton("mur-answer-no", _("No"), false, answer === 2);
                    break;
                }
                case "vote": {
                    for (const player of document.querySelectorAll(".mur-player")) {
                        if (player.dataset.player !== "none") {
                            player.classList.add("mur-selectable");
                        }
                    }
                    this.addActionButton("mur-vote", _("Vote"), () => {
                        this.request("vote", {
                            playerId: this.selectedPlayer.dataset.player
                        }, () => {
                            this.selectPlayer(null);
                        });
                    });
                    document.getElementById("mur-vote").classList.add("disabled");
                }
            }
        } else if (!this.isSpectator) {
            switch (stateName) {
                case "vote": {
                    this.addActionButton("mur-cancel", _("Cancel"), () => {
                        this.request("cancel", {});
                    }, null, null, "gray");
                    clearTag("mur-selectable");
                    break;
                }
            }
        }
    },

    request(action, args, onSuccess) {
        this.ajaxcall(`/${gameName}/${gameName}/${action}.html`, {
            lock: true,
            ...args
        }, () => {
            if (typeof onSuccess === "function") {
                onSuccess();
            }
        });
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
        const element = document.getElementById(`mur-player-${player.id}`);

        if (this.checkAction("ask", true)) {
            if (this.selectPlayer(element)) {
                this.setClientState("clientSelectTiles", {
                    descriptionmyturn: _("${you} must select a tile to ask ${player_name} about"),
                    possibleactions: ["clientAsk"],
                    args: {player_name: `<span style="color: #${player.color}; -webkit-text-stroke: 0.5px black">${player.name}</span>`}
                });
            }
        } else if (this.checkAction("vote", true)) {
            if (this.selectPlayer(element)) {
                document.getElementById("mur-vote").classList.remove("disabled");
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
        dojo.subscribe("round", this, () => {
            const tiles = document.querySelectorAll(".mur-tile:not(.mur-leftover)");
            for (const tile of tiles) {
                tile.classList.remove("mur-flipped");
            }
        });
        this.notifqueue.setSynchronous('round', 600);

        dojo.subscribe("inspect", this, data => {
            console.log(data);
            const tile = document.querySelector(`#mur-tile-${data.args.tileId}`);
            tile.style.setProperty("--character", data.args.character);
            tile.classList.add("mur-flipped");
        });

        dojo.subscribe("question", this, data => {
            console.log(data);
        })

        this.notifqueue.setSynchronous("inspect", 500);
    },

    formatNumbers(bitmask) {
        bitmask = parseInt(bitmask);
        const values = [];
        for (i = 0; i < 7; ++i) {
            if (bitmask & (1 << i)) {
                values.push(i + 1);
            }
        }
        return values
            .map(n => `<div class="mur-single-number mur-icon" data-number="${n}"></div>`)
            .join("");
    },

    formatTokens(tokens) {
        console.log("formatTokens", tokens);
        function createToken(player) {
            const token = parseInt(player.token);
            const style = `--token-x: ${token % 4}; --token-y: ${Math.floor(token / 4)}`;
            return `<div class="mur-icon mur-token" style="${style}"></div>`;
        }

        const players =
            Object.keys(this.gamedatas.players).map(id => this.gamedatas.players[id]);
        const player = players.filter(player => player.name === tokens)[0];
        if (player) {
            return createToken(player);
        }

        if (tokens.startsWith("@tile:")) {
            console.log("tileID", tokens.substring("@tile:".length));
            const tile = this.gamedatas.tiles.filter(tile => tile.id === tokens.substring("@tile:".length))[0];
            console.log(tile);
            const owner = tile && this.gamedatas.players[tile.player_id];
            return owner ? createToken(owner) : "";
        }

        const bits = parseInt(tokens);
        return Object.keys(players)
            .map(id => players[id])
            .filter(player => bits & (1 << parseInt(player.token)))
            .map(player => {
                const token = parseInt(player.token);
                const style = `--token-x: ${token % 4}; --token-y: ${Math.floor(token / 4)}`;
                return `<div class="mur-icon mur-token" style="${style}"></div>`
            })
            .join("");
    },

    format_string_recursive(log, args) {
        if (args && !("substitutionComplete" in args)) {
            args.substitutionComplete = true;
            const formatters = {
                'number': this.formatNumbers,
                'token': this.formatTokens
            };
            for (const iconType of Object.keys(formatters)) {
                const icons = Object.keys(args).filter(name => name.startsWith(`${iconType}Icon`));

                for (const icon of icons) {
                    const values = args[icon].toString().split(",");
                    args[icon] = formatters[iconType].call(this, ...values);
                }
            }
        }
        return this.inherited({callee: this.format_string_recursive}, arguments);
    }
}));