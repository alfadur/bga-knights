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
    return `<div class="mur-player" style="${style}" data-player="${playerId}">
        <div class="mur-player-name" style="color: #${playerColor}">${playerName}</div>
    </div>`;
}

function createTile() {
    return `<div class="mur-tile">
        <div class="mur-tile-back"></div>                
        <div class="mur-tile-front"></div>
        <div class="mur-tile-side"></div>
    </div>`;
}

function createPlaceholder(index) {
    return `<div class="mur-placeholder" data-number="${index + 1}"></div>`
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

        for (const playerId of Object.keys(players)) {
            const player = players[playerId];
            const index = parseInt(player.no) - 1;
            const angle = 90 + index * 360 / playerCount;

            const playerArea = createElement(playArea,
                createPlayer(index, angle, player.id, player.name, player.color));
            const tile = createElement(playerArea, createTile());
            console.log("New tile", tile);
            tile.addEventListener('click', event => {
                event.stopPropagation();
                console.log("Tile click");
                this.onTileClick(tile);
            })
        }

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
            }
        }
    },

    onLeavingState(stateName) {
        console.log(`Leaving state: ${stateName}`);

        if (this.isCurrentPlayerActive()) {
            switch (stateName) {
                case "inspect": {
                    clearTag("mur-selectable");
                    this.selectTile(null);
                    break;
                }
            }
        }
    },

    onUpdateActionButtons(stateName, args) {
        console.log(`onUpdateActionButtons: ${stateName}`);

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

    onTileClick(tile) {
        if (this.checkAction("inspect", true)) {
            document.getElementById("mur-inspect").classList.toggle(
                "disabled", !this.selectTile(tile));
        }
    },

    setupNotifications() {
        console.log("notifications subscriptions setup");
    }
}));