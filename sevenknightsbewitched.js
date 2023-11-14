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

function createPlayer(index, angle, playerId, playerName, playerColor) {
    const radians = angle / 180 * Math.PI;
    const coords = [Math.cos(radians), -Math.sin(radians)];
    const style = `--index: ${index}; --cx: ${coords[0]}; --cy: ${coords[1]}`;
    return `<div id="mur-player-${playerId}" class="mur-player" style="${style}" data-player="${playerId}">
        <div class="mur-player-name" style="color: #${playerColor}">${playerName}</div>
    </div>`;
}

function createTile(token, character) {
    console.log("token", token);
    let settings = [];
    if (token !== undefined) {
        token = parseInt(token);
        settings.push(`--token-x: ${token % 4}; --token-y: ${Math.floor(token / 4)}`);
    }
    if (character !== undefined) {
       settings.push(`--character: ${character}`);
    }
    const style = settings.join("; ");
    return `<div class="mur-tile" style="${style}">
        <div class="mur-tile-back"></div>                
        <div class="mur-tile-front"></div>
        <div class="mur-tile-side"></div>
    </div>`;
}

function createPlaceholder(index) {
    return `<div class="mur-placeholder" data-number="${index + 1}"></div>`
}

function createQuestionDialog() {
    const questionText = _("Is your number one of...");
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

        for (const playerId of playerIds) {
            const player = players[playerId];
            const index = sortKey(player);
            const angle = 90 + index * 360 / playerCount;

            const playerArea = createElement(playArea,
                createPlayer(index, angle, player.id, player.name, player.color));
            const tile = createElement(playerArea,
                createTile(player.token, player.character));

            tile.classList.add("mur-owned");
            if ("character" in player) {
                tile.classList.add("mur-flipped");
            }

            console.log("New tile", tile);
            tile.addEventListener('click', event => {
                event.stopPropagation();
                console.log("Tile click");
                this.onTileClick(tile);
            })
        }

        Array.from(document.querySelectorAll(" .mur-placeholder")).forEach((place, index) => {
            if (index >= playerCount - 1) {
                createElement(place, createTile(undefined, index + 1)).classList.add("mur-flipped");
            }
        });

        this.setupNotifications();

        console.log("Ending game setup");
    },

    onEnteringState(stateName, args) {
        console.log(`Entering state: ${stateName}`);

        if (this.isCurrentPlayerActive()) {
            switch (stateName) {
                case "inspect":
                case "question": {
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
                case "question": {
                    clearTag("mur-selectable");
                    this.selectTile(null);
                    break;
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
                            playerId: this.selectedTile.parentElement.dataset.player
                        });
                    });
                    document.getElementById("mur-inspect").classList.add("disabled");
                    break;
                }
                case 'answer': {
                    const that = this;
                    const answer = parseInt(args._private.answer);

                    function answerButton(id, label, answer, disable) {
                        that.addActionButton(id, label, () => {
                            that.request('answer', {answer});
                        });
                        document.getElementById("mur-answer-yes").classList.toggle("disabled", disable);
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

    selectTile(tile) {
        console.log("Selecting tile", tile);
        if (this.selectedTile) {
            this.selectedTile.classList.remove("mur-selected");
        }
        this.selectedTile = tile;
        if (this.selectedTile) {
            this.selectedTile.classList.add("mur-selected");
            return true;
        }
        return false;
    },

    questionDialog(tile) {
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
                playerId: tile.parentElement.dataset.player,
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

    onTileClick(tile) {
        if (this.checkAction("inspect", true)) {
            document.getElementById("mur-inspect").classList.toggle(
                "disabled", !this.selectTile(tile));
        } else if (this.checkAction("ask", true)) {
            if (this.selectTile(tile)) {
                this.selectTile(null);
                this.questionDialog(tile);
            }
        }
    },

    setupNotifications() {
        console.log("notifications subscriptions setup");
        dojo.subscribe('inspect', this, data => {
            console.log(data);
            const tile = document.querySelector(`#mur-player-${data.args.playerId} .mur-tile`);
            tile.style.setProperty("--character", data.args.character);
            tile.classList.add("mur-flipped");
        });

        this.notifqueue.setSynchronous('inspect', 500);
    }
}));