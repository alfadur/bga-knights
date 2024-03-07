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

const GameMode = Object.freeze({
    standard: 1,
    advanced: 2,
    darkness: 3
})

function clearTag(tag) {
    for (const element of document.querySelectorAll(`.${tag}`)) {
        element.classList.remove(tag);
    }
}

function createElement(parent, html, position) {
    const element = typeof position === "number" ?
        parent.insertBefore(document.createElement("div"), parent.children[position]) :
        parent.appendChild(document.createElement("div"));
    element.outerHTML = html;
    return typeof position === "number" ?
        parent.children[position] :
        parent.lastElementChild;
}

function getElementCenter(element) {
    const bounds = element.getBoundingClientRect();
    return {
        x: (bounds.left + bounds.right) / 2,
        y: (bounds.top + bounds.bottom) / 2
    }
}

function createPlayer(index, angle, player) {
    let style;
    if (angle !== null) {
        const radians = angle / 180 * Math.PI;
        const coords = [-Math.cos(radians), -Math.sin(radians)];
        style = `--index: ${index}; --cx: ${coords[0]}; --cy: ${coords[1]}`;
    } else {
        style = `--cx: -0.75; --cy: -0.75`;
    }

    const id = player ? player.id : "none";
    const token = player ? player.token : "none";
    const name = player ?
        `<div class="mur-player-name" style="color: #${player.color}">${player.name}</div>` :
        "";

    return `<div id="mur-player-${id}" class="mur-player" style="${style}" data-player="${id}" data-token="${token}">
        ${name}
    </div>`;
}

function createTile(tile, token) {
    const settings = [];
    let tokenData = ""
    if (token !== null) {
        token = parseInt(token);
        settings.push(`--token-x: ${token % 4}; --token-y: ${Math.floor(token / 4)}`);
        tokenData = `data-token="${token}"`;
    }

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
                data-id="${tile.id}" ${tokenData}>
        <div class="mur-tile-back"></div>                
        <div class="mur-tile-front"></div>
        <div class="mur-tile-side"></div>
    </div>`
}

function createLeftoverTile(index) {
    return `<div id="mur-space-tile-${index}" 
                class="mur-tile mur-flipped mur-leftover" 
                style="--character: ${index}">               
        <div class="mur-tile-front"></div>
    </div>`
}

function createPlaceholder(index, unordered, optional, inactive) {
    const classes = ["mur-placeholder"];
    if (unordered) {
        classes.push("mur-unordered");
    }
    if (optional) {
        classes.push("mur-optional");
    }
    if (inactive) {
        classes.push("mur-inactive");
    }
    return `<div id="mur-placeholder-${index + 1}" class="${classes.join(" ")}" data-number="${index + 1}"></div>`
}

function createArrow(token, index) {
    token = parseInt(token);
    const style = `
        --sprite-x: ${token % 4}; --sprite-y: ${Math.floor(token / 4)}; z-index: ${7 - index}`;
    return `<div class="mur-arrow" style="${style}" data-token="${token}"></div>`;
}

function createToken(token) {
    token = parseInt(token);
    const style = `--token-x: ${token % 4}; --token-y: ${Math.floor(token / 4)}`;
    return `<div class="mur-icon mur-token" style="${style}" data-token="${token}"></div>`;
}

function createQuestionDialog(numberCount) {
    const questionText = _("Is the tile number one of...");
    const numbers = [1, 2, 3, 4, 5, 6, 7].slice(0, numberCount).map(n =>
        `<div class="mur-single-number" data-number="${n}"></div>`);
    return `<div>
        <div class="mur-question-text">${questionText}</div>
        <div class="mur-number-list">
            ${numbers.join("")}
        </div>
        <div id="mur-question-dialog-buttons"></div>
    </div>`;
}

function createMultiQuestionDialog() {
    const questionText = _("Is the following true...");
    return `<div>
        <div class="mur-question-text">${questionText}</div>
        <div class ="mur-expression"></div>
        <div id="mur-question-dialog-buttons"></div>
    </div>`;
}

const NodeGen = {
    wrap(content) {
        return `<div class="mur-expression-slot">${content}</div>`;
    },

    number(n) {
        return `<div class="mur-single-number" data-number="${n}"></div>`;
    },

    token(token, index) {
        token = parseInt(token);
        const style = `--token-x: ${token % 4}; --token-y: ${Math.floor(token / 4)}`;
        return `<div class="mur-token" data-token="${index}" style="${style}"></div>`;
    },

    operator(char) {
        return `<div class="mur-expression-operator" data-operator="${char}"></div>`;
    },

    wildcard() {
        return `<div class="mur-expression-wildcard"></div>`;
    }
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
        this.questionTiles = new Set();
        this.tokenTiles = Array(8).fill(null);
    },

    setup(data) {
        console.log("Starting game setup");

        this.gameMode = parseInt(data.mode);
        this.isCoop = parseInt(data.coop);

        const status = document.getElementById("mur-status");
        status.firstElementChild.innerText = _("Round");
        status.lastElementChild.innerText = this.isCoop ? _("Cooperative") : _("Competitive");
        const activeRoundMarker = status.querySelector(`:nth-child(${parseInt(data.round) + 1})`);
        if (activeRoundMarker) {
            activeRoundMarker.classList.add("mur-current");
        }

        const deployment = document.getElementById("mur-deployment");
        const sequential = this.gameMode === GameMode.standard
            || this.gameMode === GameMode.darkness && this.isCoop;

        let i = 0;
        while (i < data.tiles.length + this.isCoop - 1) {
            createElement(deployment,
                createPlaceholder(i++, !sequential));
        }

        if (this.gameMode !== GameMode.standard && !this.isCoop) {
            createElement(deployment,
                createPlaceholder(i++, !sequential, true))
        }

        if (this.gameMode === GameMode.standard) {
            while (i < 7) {
                createElement(deployment,
                    createPlaceholder(i++, false, false, true))
            }
        }

        for (const place of document.querySelectorAll(" .mur-placeholder")) {
            if (place.classList.contains("mur-inactive")) {
                createElement(place, createLeftoverTile(place.dataset.number), undefined);
            } else {
                place.addEventListener("click", event => {
                    event.stopPropagation();
                    console.log("Place click");
                    this.onPlaceholderClick(place);
                });
            }
        }

        const players = data.players;
        const playerIds = Object.keys(players);
        const playerCount = playerIds.length;

        const playArea = document.getElementById("mur-play-area");

        const currentIndex = this.isSpectator ? 0 :
            parseInt(players[this.getCurrentPlayerId().toString()].no) - 1;

        function sortKey(player) {
            return (parseInt(player.no) - 1 - currentIndex + playerCount) % playerCount;
        }

        const extraTiles = data.tiles.filter(tile =>
            tile.player_id === null);

        const areaCount = extraTiles.length === 1 ? playerCount + 1 : playerCount;

        function createPlayerArea(player, index) {
            const angle = index !== null ? 90 + index * 360 / areaCount : null;
            const playerArea = createElement(playArea, createPlayer(index, angle, player));

            if (player) {
                playerArea.addEventListener("click", event => {
                    event.stopPropagation();
                    console.log("Player click");
                    this.onPlayerClick(player);
                });
            }

            createElement(playerArea, `<div class="mur-tile-container"></div>`);
            createElement(playerArea, `<div class="mur-token-container"></div>`);
        }

        for (const playerId of playerIds) {
            const player = players[playerId];
            const index = sortKey(player);

            createPlayerArea.call(this, player, index);
        }

        if (extraTiles.length) {
            const index = extraTiles.length === 1 ? areaCount - 1 : null
            createPlayerArea.call(this, null, index);
        }

        for (const tile of data.tiles) {
            if (tile.player_id !== null) {
                const token = data.players[tile.player_id].token;
                this.tokenTiles[token] = tile;
            }
        }

        for (const descriptor of data.tiles) {
            const playerId = descriptor.player_id || "none";
            const container = document.querySelector(`#mur-player-${playerId} .mur-tile-container`);
            const tileSpace = createElement(container,
                `<div id="mur-tile-placeholder-${descriptor.id}" class="mur-placeholder mur-inactive">
                        <div id="mur-tile-arrows-${descriptor.id}" class="mur-arrows"></div>
                    </div>`);

            let token;

            if (descriptor.player_id === null) {
                token = this.tokenTiles.indexOf(null);
                this.tokenTiles[token] = descriptor;
            } else {
                token = data.players[descriptor.player_id].token;
            }

            let tile;

            if (descriptor.deployment !== null) {
                const place = document.getElementById(`mur-placeholder-${descriptor.deployment}`);
                tile  = createElement(place, createTile(descriptor, token));
            } else {
                tile = createElement(tileSpace, createTile(descriptor, token));
            }

            console.log("New tile", tile);
            tile.addEventListener("click", event => {
                event.stopPropagation();
                console.log("Tile click", tile);
                this.onTileClick(tile);
            });
        }

        for (const inspection of data.inspections) {
            const place = document.getElementById(`mur-tile-arrows-${inspection.tile_id}`);
            createElement(place,
                createArrow(data.players[inspection.player_id].token, place.childElementCount));
        }

        const firstPlayer = document.getElementById(`mur-player-${data.firstPlayer}`);
        createElement(firstPlayer, `<div id="mur-sword"></div>`)

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
                case "deployKnights": {
                    for (const tile of document.querySelectorAll(".mur-tile:not(.mur-leftover)")) {
                        tile.classList.add("mur-selectable");
                    }
                    break;
                }
                case "clientDeploy": {
                    for (const space of document.querySelectorAll(".mur-placeholder:not(.mur-inactive)")) {
                        if (this.selectedTile.parentElement !== space) {
                            space.classList.add("mur-selectable");
                        }
                    }
                    break;
                }
            }
        }

        switch (stateName) {
            case "answer": {
                this.displayQuestion(args.args.question);
                break;
            }
        }
    },

    onLeavingState(stateName) {
        console.log(`Leaving state: ${stateName}`);

        if (this.isCurrentPlayerActive()) {
            switch (stateName) {
                case "clientSelectTiles":
                    clearTag("mur-selected");
                    this.questionTiles.clear();
                //fallthrough
                case "inspect":
                case "vote":
                case "clientDeploy": {
                    clearTag("mur-selectable");
                    this.selectTile(null);
                    this.selectPlayer(null);
                    break;
                }
                case "question":
                case "deployKnights": {
                    clearTag("mur-selectable");
                    break;
                }

            }
        }

        switch (stateName) {
            case "answer": {
                let bubble = document.querySelector(".mur-bubble");
                bubble.parentElement.removeChild(bubble);
                break;
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
                case "clientSelectTiles": {
                    this.addActionButton("mur-ask", _("Ask"), () => {
                        if (this.questionTiles.size === 1) {
                            this.questionDialog(this.selectedPlayer, this.questionTiles.keys().next().value);
                        } else {
                            this.multiQuestionDialog(this.selectedPlayer, Array.from(this.questionTiles));
                        }
                    });
                    document.getElementById("mur-ask").classList.add("disabled");
                    break;
                }
                case "answer": {
                    const that = this;
                    const answer = parseInt(args._private.answer);
                    const canChoose = (answer & 0b10) !== 0

                    function answerButton(id, label, answer, isFalse) {
                        that.addActionButton(id, label, () => {
                            that.request("answer", {answer});
                        }, null, null, isFalse && canChoose ? "red" : "blue");
                        if (!canChoose) {
                            document.getElementById(id).classList.toggle("disabled", isFalse);
                        }
                    }

                    answerButton("mur-answer-yes", _("Yes"), true, (answer & 0b1) === 0);
                    answerButton("mur-answer-no", _("No"), false, (answer & 0b1) === 1);
                    break;
                }
                case "vote": {
                    for (const player of document.querySelectorAll(".mur-player")) {
                        if (player.dataset.player !== "none") {
                            player.classList.add("mur-selectable");
                        }
                    }
                    this.addActionButton("mur-vote", _("Recommend"), () => {
                        this.request("vote", {
                            playerId: this.selectedPlayer.dataset.player
                        }, () => {
                            this.selectPlayer(null);
                        });
                    });
                    document.getElementById("mur-vote").classList.add("disabled");
                    break;
                }
                case "deployKnights": {
                    this.addActionButton("mur-check", _("Fall in!"), () => {
                        this.request("check");
                    }, null, null, "red");

                    document.getElementById("mur-check")
                        .classList.toggle("disabled", !args.ready);
                    break;
                }
            }

            switch (stateName) {
                case "clientSelectTiles":
                case "clientDeploy": {
                    this.addActionButton("mur-cancel", _("Cancel"), () => {
                        this.restoreServerGameState();
                    }, null, null, "gray");
                    break;
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
        const numbersCount = this.gameMode === GameMode.standard ?
            this.gamedatas.tiles.length + this.isCoop - 1 : 7;

        const dialog = new ebg.popindialog();
        dialog.create("mur-question-dialog");
        dialog.setTitle(_("Choose a question"));
        dialog.setContent(createQuestionDialog(numbersCount));
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
                askButton.classList.toggle("disabled", selectionSize % numbersCount === 0);
            });
        }
    },

    multiQuestionDialog(player, tiles) {
        tiles.sort((t1, t2) => parseInt(t1.dataset.id) - parseInt(t2.dataset.id));
        const tokens = tiles.map(tile => parseInt(tile.dataset.token));
        const nodes = [{type: "wildcard"}];

        const dialog = new ebg.popindialog();
        dialog.create("mur-question-dialog");
        dialog.setTitle(_("Choose a question"));
        dialog.setContent(createMultiQuestionDialog());
        dialog.show();

        function reset() {
            const expression = document.querySelector(".mur-expression:not(.mur-icon)");
            console.log(expression);
            expression.innerHTML = `<div class="mur-expression-slot" data-node="0">
                <div class="mur-expression-wildcard"></div>
            </div>`;
            nodes.splice(0, nodes.length, {type: "wildcard"});
            initWildcard(expression.querySelector(".mur-expression-wildcard"), 0);
            askButton.classList.add("disabled");
        }

        function ask() {
            const expression = [];
            const operators = {
                "<": "l",
                "=": "e",
                "+": "p"
            }

            function add(index) {
                const node = nodes[index];
                switch (node.type) {
                    case "token":
                        expression.push(String.fromCharCode("a".charCodeAt(0) + parseInt(node.token)));
                        break;
                    case "number":
                        expression.push(String.fromCharCode("0".charCodeAt(0) + parseInt(node.number)));
                        break;
                    case "operator":
                        add(node.left);
                        add(node.right);
                        expression.push(operators[node.operator] || "");
                }
            }
            add(0);

            this.request("askMany", {
                playerId: player.dataset.player,
                tileIds: tiles.map(tile => tile.dataset.id).join(","),
                expression: expression.join("")
            }, () => {
                dialog.destroy();
            });
        }

        this.addActionButton("mur-question-dialog-ask", _("Ask"), ask, "mur-question-dialog-buttons");
        this.addActionButton("mur-question-dialog-reset", _("Reset"), reset, "mur-question-dialog-buttons", null, "gray");

        const askButton = document.getElementById("mur-question-dialog-ask");
        askButton.classList.add("disabled");

        function generate(level) {
            const result = [];

            if (level === 0) {
                result.push(NodeGen.operator('<'));
                result.push(NodeGen.operator('='));
            } else {
                if (level < 3) {
                    result.push(NodeGen.operator('+'));
                }
                for (let i = 1; i <= 9; ++i) {
                    result.push(NodeGen.number(i));
                }
                for (let i = 0; i < tokens.length; ++i) {
                    result.push(NodeGen.token(tokens[i], i));
                }
            }

            return result.map(NodeGen.wrap).join("");
        }

        function indexOfElement(element) {
            return Array.prototype.indexOf.call(element.parentElement.children, element)
        }

        function createNode(slot, level) {
            const nodeIndex = parseInt(slot.dataset.node);
            const element = slot.firstElementChild;

            if (element.classList.contains("mur-token")) {
                nodes[nodeIndex] = {
                    type: "token",
                    token: element.dataset.token
                };
            } else if (element.classList.contains("mur-single-number")) {
                nodes[nodeIndex] = {
                    type: "number",
                    number: element.dataset.number
                };
            } else if (element.classList.contains("mur-expression-operator")) {
                const nodesCount = nodes.length;
                const index = indexOfElement(slot);

                nodes[nodeIndex] = {
                    type: "operator",
                    operator: element.dataset.operator,
                    left: nodesCount,
                    right: nodesCount + 1
                };

                for (let i = 0; i <= 1; ++i) {
                    const newItem = createElement(slot.parentElement, NodeGen.wrap(NodeGen.wildcard()), index + 1 - i);
                    newItem.dataset.node = (nodesCount + 1 - i).toString();
                    nodes.push({type: "wildcard"});
                    initWildcard(newItem.firstElementChild, level);
                }
            }

            askButton.classList.toggle("disabled", nodes.some(n => n.type === "wildcard"));
        }

        function initWildcard(element, level) {
            element.addEventListener("mousedown", event => {
                event.stopPropagation();
                if (event.currentTarget === event.target) {
                    const existingSelector = document.querySelector(".mur-expression-selector");
                    if (existingSelector) {
                        existingSelector.remove();
                    }

                    const slot = event.target.parentElement;
                    const selector = createElement(slot,
                        `<div class="mur-expression-selector">${generate(level)}</div>`);

                    for (const item of selector.children) {
                        item.addEventListener("mousedown", event => {
                            event.stopPropagation();
                            slot.innerHTML = item.innerHTML;
                            createNode(slot, level + 1);
                        })
                    }
                }
            });
        }

        reset();
    },

    addBubble(player, content, timeout) {
        const cos = parseFloat(player.style.getPropertyValue("--cx"));
        const sin = parseFloat(player.style.getPropertyValue("--cy"));
        const style = `--cx: ${cos}; --cy: ${sin}`;
        const bubble = createElement(document.getElementById("mur-play-area"),
            `<div class="mur-bubble" style="${style}">
                <div class="mur-bubble-border"></div>
                <svg xmlns="http://www.w3.org/2000/svg"><path></path></svg>
                <div class="mur-bubble-content">${content}</div>
            </div>`);

        const svg = bubble.querySelector("svg");
        const {width, height} = svg.getBoundingClientRect();
        const cx = width - (cos + 1) * width / 2;
        const cy = height - (sin + 1) * height / 2;
        const size = 15;
        const path = `M${cx},${cy} L${width / 2 + sin * size},${height / 2 - cos * size} L${width / 2 - sin * size},${height / 2 + cos * size} Z`;
        svg.firstElementChild.setAttribute("d", path);
        if (timeout !== undefined) {
            setTimeout(() => bubble.parentElement.removeChild(bubble), timeout);
        }
    },

    displayQuestion(question, timeout) {
        const player = document.getElementById(`mur-player-${question.player_id}`);
        const recipient = this.gamedatas.players[question.recipient_id];

        if ("expression" in question && question.expression !== null) {
            const message = _("${tokenIcon1}${player_name1}, is this true? ${expressionIcon}");
            const args = {
                player_name1: `<span style="color: #${recipient.color}">${recipient.name}</span>`,
                tokenIcon1: `player,${recipient.name}`,
                expressionIcon: `${question.expression},${question.expression_tiles}`
            };

            this.addBubble(player, this.format_string_recursive(message, args), timeout);
        } else {
            const tileOwner = this.gamedatas.tiles.filter(tile => tile.id === question.tile_id.toString())[0].player_id;
            const bitset = parseInt(question.question);
            const isSingleNumber = (bitset & bitset - 1) === 0;

            const message = isSingleNumber ?
                (tileOwner === recipient.id ?
                    _("${tokenIcon1}${player_name1}, is your tile ${numberIcon}?") :
                    _("${tokenIcon1}${player_name1}, is ${tokenIcon2}${player_name2}'s tile ${numberIcon}?")) :
                (tileOwner === recipient.id ?
                    _("${tokenIcon1}${player_name1}, is your tile one of ${numberIcon}?") :
                    _("${tokenIcon1}${player_name1}, is ${tokenIcon2}${player_name2}'s tile one of ${numberIcon}?"));
            const args = {
                player_name1: `<span style="color: #${recipient.color}">${recipient.name}</span>`,
                tokenIcon1: `player,${recipient.name}`,
                numberIcon: question.question
            };

            if (tileOwner !== recipient.id) {
                if (tileOwner === null) {
                    args.player_name2 = _("Knight-errant");
                    args.tokenIcon2 = `tile,${question.tile_id}`;
                } else {
                    const owner = this.gamedatas.players[tileOwner];
                    args.player_name2 = `<span style="color: #${owner.color}">${owner.name}</span>`;
                    args.tokenIcon2 = `player,${owner.name}`
                }
            }

            this.addBubble(player, this.format_string_recursive(message, args), timeout);
        }
    },

    animatePlayerPlace(ownerId, item) {
        const source = document.getElementById(`player_board_${ownerId}`);

        const from = getElementCenter(source);
        const to = getElementCenter(item);
        const dX = from.x - to.x;
        const dY = from.y - to.y;

        item.style.setProperty("--x", `${dX}px`);
        item.style.setProperty("--y", `${dY}px`);
        item.classList.add("mur-animated");

        item.addEventListener("animationend",
            () => item.classList.remove("mur-animated"),
            {once: true});
    },

    animateTokens(playerId, tokens) {
        for (const token of tokens) {
            const ownerId = this.tokenTiles[token].player_id;
            const place = document.querySelector(`#mur-player-${playerId} .mur-token-container`);
            this.animatePlayerPlace(ownerId, createElement(place, createToken(token)));
        }
    },

    animateArrow(playerId, tileId) {
        const place = document.getElementById(`mur-tile-arrows-${tileId}`);
        const arrow = createElement(place,
            createArrow(this.gamedatas.players[playerId].token, place.childElementCount));
        this.animatePlayerPlace(playerId, arrow);
    },

    animatePlayerRemove(ownerId, item) {
        const destination = document.getElementById(`player_board_${ownerId}`);

        const from = getElementCenter(item);
        const to = getElementCenter(destination);
        const dX = to.x - from.x;
        const dY = to.y - from.y;

        item.style.setProperty("--x", `${dX}px`);
        item.style.setProperty("--y", `${dY}px`);
        item.classList.add("mur-animated-back");

        item.addEventListener("animationend", () => {
            item.classList.remove("mur-animated-back");
            if (item.parentElement) {
                item.parentElement.removeChild(item);
            }
        }, {
            once: true
        });
    },

    animateTiles(moves) {
        for (const move of moves) {
            const from = getElementCenter(move.tile);
            const to = getElementCenter(move.place);
            move.dX = from.x - to.x;
            move.dY = from.y - to.y;
        }

        for (const {place, tile, dX, dY} of moves) {
            const delay = 100;
            tile.style.animationDelay = `${delay}ms`;
            tile.classList.add("mur-animated");

            setTimeout(() => {
                place.appendChild(tile);
                tile.style.setProperty("--x", `${dX}px`);
                tile.style.setProperty("--y", `${dY}px`);
                tile.addEventListener("animationend",
                    () => {
                        tile.style.setProperty("--x", "0px");
                        tile.style.setProperty("--y", "0px");
                        tile.classList.remove("mur-animated")
                    },
                    {once: true});
            }, delay);
        }
    },

    revealCharacter(tileId, character) {
        const tile = document.querySelector(`#mur-tile-${tileId}`);
        tile.style.setProperty("--character", character);
        if (!tile.classList.contains("mur-flipped")) {
            tile.classList.add("mur-flipped");
            return true;
        }
        return false;
    },

    animateSword(playerId) {
        const firstPlayer = document.getElementById(`mur-player-${playerId}`);
        const sword = document.getElementById("mur-sword");

        if (sword.parentElement === firstPlayer) {
            return
        }

        const from = getElementCenter(sword);

        firstPlayer.appendChild(sword);

        const to = getElementCenter(sword);
        const dX = from.x - to.x;
        const dY = from.y - to.y;

        sword.style.setProperty("--x", `${dX}px`);
        sword.style.setProperty("--y", `${dY}px`);
        sword.style.setProperty("--direction", Math.sign(dX).toString());
        sword.classList.add("mur-animated");

        sword.addEventListener("animationend",
            () => sword.classList.remove("mur-animated"),
            {once: true});
    },

    roundCleanup() {
        const deployedTiles = document.querySelectorAll(".mur-placeholder:not(.mur-inactive) .mur-tile");
        const moves = [];
        for (const tile of deployedTiles) {
            const place = document.getElementById(`mur-tile-placeholder-${tile.dataset.id}`);
            moves.push({tile, place});
        }
        if (moves.length > 0) {
            this.animateTiles(moves);
        }

        setTimeout(() => {
            const tiles = document.querySelectorAll(".mur-tile:not(.mur-leftover)");
            for (const tile of tiles) {
                tile.classList.remove("mur-flipped");
            }
        }, 0);

        for (const arrow of document.querySelectorAll(".mur-arrow")) {
            this.animatePlayerRemove(
                this.tokenTiles[parseInt(arrow.dataset.token)].player_id,
                arrow);
        }
    },

    onPlayerClick(player) {
        const element = document.getElementById(`mur-player-${player.id}`);

        if (this.checkAction("ask", true)) {
            if (this.selectPlayer(element)) {
                this.setClientState("clientSelectTiles", {
                    descriptionmyturn: _("${you} must select tile(s) to ask ${player_name} about"),
                    possibleactions: ["clientAsk"],
                    args: {
                        player_name: `<span style="color: #${player.color}; -webkit-text-stroke: 0.5px black">${player.name}</span>`
                    }
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
            if (!this.questionTiles.has(tile)) {
                this.questionTiles.add(tile);
            } else {
                this.questionTiles.delete(tile);
            }

            tile.classList.toggle("mur-selected", this.questionTiles.has(tile));
            document.getElementById("mur-ask").classList.toggle("disabled", this.questionTiles.size === 0);
        } else if (this.checkAction("deploy", true)) {
            this.selectTile(tile);
            this.setClientState("clientDeploy", {
                descriptionmyturn: _("You must select a space for the tile"),
                possibleactions: ["clientDeploy"]
            });
        }
    },

    onPlaceholderClick(place) {
        if (this.checkAction("clientDeploy", true)) {
            if (place.classList.contains("mur-selectable")) {
                this.request("deploy", {
                    tileId: this.selectedTile.dataset.id,
                    position: place.dataset.number
                });
            }
        }
    },

    setupNotifications() {
        console.log("notifications subscriptions setup");
        dojo.subscribe("round", this, () => this.roundCleanup());
        this.notifqueue.setSynchronous('round', 800);

        dojo.subscribe("inspect", this, data => {
            console.log(data);
            this.animateArrow(data.args.playerId, data.args.tileId);
        });

        dojo.subscribe("reveal", this, data => {
            console.log(data);
            const delay = this.revealCharacter(data.args.tileId, data.args.character) ? 1000 : 0;
            this.notifqueue.setSynchronousDuration(delay);
        });
        this.notifqueue.setSynchronous("reveal");

        dojo.subscribe("question", this, data => {
            console.log(data);
            if (this.isCoop) {
                this.displayQuestion(data.args.question, 3000);
            }
        });
        if (this.isCoop) {
            this.notifqueue.setSynchronous("question", 2000);
        }

        dojo.subscribe("answer", this, data => {
            const text = data.args.answer ? _("Yes") : _("No");
            const player = document.getElementById(`mur-player-${data.args.playerId}`);
            this.addBubble(player, text, 2000);
        });
        this.notifqueue.setSynchronous("answer", 1000);

        dojo.subscribe("vote", this, data => {
            console.log(data);
            const tokens = [];
            let voters = parseInt(data.args.voters);

            while (voters !== 0) {
                const token = 31 - Math.clz32(voters);
                tokens.push(token);
                voters &= ~(1 << token);
            }

            this.animateTokens(data.args.playerId, tokens);
        });
        this.notifqueue.setSynchronous("vote", 1000);

        dojo.subscribe("appoint", this, () => {
            for (const token of document.querySelectorAll(".mur-token-container .mur-token")) {
                this.animatePlayerRemove(
                    this.tokenTiles[parseInt(token.dataset.token)].player_id,
                    token);
            }
        });

        dojo.subscribe("move", this, data => {
            const tile = document.getElementById(`mur-tile-${data.args.tileId}`);
            const place = document.getElementById(`mur-placeholder-${data.args.position}`);
            const moves = [{tile, place}];
            const oldTile = place.firstElementChild;
            if (oldTile) {
                const returnPlace = tile.parentElement.classList.contains("mur-inactive") ?
                    document.getElementById(`mur-tile-placeholder-${oldTile.dataset.id}`) :
                    tile.parentElement;
                moves.push({
                    tile: oldTile,
                    place: returnPlace
                })
            }
            this.animateTiles(moves);
        });

        this.notifqueue.setSynchronous("order", 1000);

        dojo.subscribe("score", this, data => {
            console.log(data);
            const score = parseInt(data.args.score);
            for (const playerId of data.args.players) {
                this.scoreCtrl[parseInt(playerId)].incValue(score);
            }
        });

        this.notifqueue.setSynchronous("score", 1000);

        dojo.subscribe("round", this, data => {
            console.log(data);
            this.animateSword(data.args.firstPlayer);
            const activeRoundMarker = document.querySelector(".mur-round-marker.mur-current");
            if (activeRoundMarker) {
                activeRoundMarker.classList.remove("mur-current");
                activeRoundMarker.nextElementSibling.classList.add("mur-current");
            }
        });
    },

    formatList(...numbers) {
        return numbers
            .map(n => parseInt(n) > 0 ?
                `<div class="mur-single-number mur-icon" data-number="${n}"></div>` :
                `<div class="mur-icon mur-witch"></div>`)
            .join("");
    },

    formatNumbers(bitmask) {
        bitmask = parseInt(bitmask);
        if (bitmask === 0) {
            return this.formatList(0);
        } else {
            const values = [];
            for (let i = 0; i < 7; ++i) {
                if (bitmask & (1 << i)) {
                    values.push(i + 1);
                }
            }
            return this.formatList(...values);
        }
    },

    formatTokens(type, content) {
        switch (type) {
            case "player": {
                const players =
                    Object.keys(this.gamedatas.players).map(id => this.gamedatas.players[id]);
                const player = players.filter(player => player.name === content)[0];
                return player ? createToken(player.token) : "";
            }
            case "tile": {
                const tile = this.gamedatas.tiles.filter(tile => tile.id === content)[0];
                const token = this.tokenTiles.indexOf(tile);
                return token >= 0 ? createToken(token) : "";
            }
            case "bitset": {
                const bits = parseInt(content);
                const players = this.gamedatas.players;
                return Object.keys(players)
                    .map(id => players[id])
                    .filter(player => bits & (1 << parseInt(player.token)))
                    .map(player => createToken(player.token))
                    .join("");
            }
        }
        return "";
    },

    formatPosition(number) {
        const text = this.gameMode === GameMode.standard ? number : `${number}+`;
        return `<div class="mur-icon mur-position" data-position="${text}"></div>`;
    },

    formatExpression(expression, ...tiles) {
        const tokens = tiles.map(id => this.tokenTiles.indexOf(this.gamedatas.tiles.find(tile => tile.id === id)));
        console.log("tokens", tokens);
        const operators = {"p": "+", "e": "=", "l": "<"};
        const nodes = [];

        for (const c of expression) {
            if ("a" <= c && c <= "c") {
                const index = c.charCodeAt(0) - "a".charCodeAt(0);
                nodes.push({type: "token", token: tokens[index], index});
            } else if ("0"<= c && c <= "9") {
                const number = c.charCodeAt(0) - "0".charCodeAt(0);
                nodes.push({type: "number", number});
            } else if (c in operators) {
                const [left, right] = nodes.splice(nodes.length - 2, 2);
                nodes.push({type: "operator", operator: operators[c], left, right});
            }
        }

        function generate(node) {
            if (node.type === "token") {
                return NodeGen.wrap(NodeGen.token(node.token, node.index));
            } else if (node.type === "number") {
                return NodeGen.wrap(NodeGen.number(node.number));
            } else {
                const parts = [
                    generate(node.left),
                    NodeGen.wrap(NodeGen.operator(node.operator)),
                    generate(node.right)
                ];
                return parts.join("");
            }
        }
        const result = nodes.length > 0 ? generate(nodes[0]) : "";
        return `<div class="mur-expression mur-icon">${result}</div>`;
    },

    format_string_recursive(log, args) {
        if (args && !("substitutionComplete" in args)) {
            args.substitutionComplete = true;
            const formatters = {
                list: this.formatList,
                number: this.formatNumbers,
                token: this.formatTokens,
                position: this.formatPosition,
                expression: this.formatExpression
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