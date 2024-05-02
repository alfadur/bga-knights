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

function createScore(playerId, token, round) {
    function createScoreCard(index) {
        const data = index < round - 1 ? `data-team=""` : "";
        const extraClass = index >= 2 ? "mur-last-round" : "";
        return `<div class="mur-score-card ${extraClass}" style="z-index: ${2 - index}" ${data}></div>`;
    }

    token = parseInt(token);
    const style = `
        --sprite-x: ${Math.floor(token / 4)}; --sprite-y: ${token % 4}`;

    return `<div id="mur-player-score-${playerId}" class="mur-player-score">
        <div class="mur-player-card" style="${style}"></div>
        ${[0, 1, 2].map(createScoreCard).join("")}
    </div>`
}

function createPlayer(index, angle, player) {
    let style;
    if (angle !== null) {
        const radians = angle / 180 * Math.PI;
        const coords = [-Math.cos(radians), -Math.sin(radians)];
        style = `--index: ${index}; --cx: ${coords[0]}; --cy: ${coords[1]}`;
    } else {
        style = `--cx: -0.75; --cy: -0.85`;
    }

    const id = player ? player.id : "none";
    const token = player ? player.token : "none";
    const captainClass = (player && player.isCaptain) ? "mur-star mur-active" : "mur-star";
    const name = player ?
        `<div class="mur-player-name-container">
            <div class="mur-player-name" style="color: #${player.color}">
                <div class="${captainClass} fa6-solid fa6-star"></div>
                ${player.name}
            </div>
        </div>` :
        "";

    return `<div id="mur-player-${id}" class="mur-player" style="${style}" data-player="${id}" data-token="${token}">
        ${name}
    </div>`;
}

function createTile(tile, token) {
    const settings = [];
    const data = [];

    if (token !== null) {
        token = parseInt(token);
        settings.push(`--token-x: ${token % 4}; --token-y: ${Math.floor(token / 4)}`);
        data.push(`data-token="${token}"`);
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
        data.push(`data-character="${tile.character}"`);
    }

    return `<div id="mur-tile-${tile.id}" 
                class="mur-tile ${classes.join(" ")}" 
                style="${style}" 
                data-id="${tile.id}" ${data.join(" ")}>
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

function createExpression(expression, tokens, inline) {
    const operators = {"p": "+", "e": "=", "l": "<", "o": "|", "n": "&"};
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
        } else {
            nodes.push({type: "wildcard"});
        }
    }

    function order(node, index) {
        node.index = index;
        return node.type === "operator" ?
            order(node.right, order(node.left, index + 1) + 1) :
            index;
    }

    order(nodes[0], 0);

    function generate(node) {
        if (node.type === "token") {
            return NodeGen.wrap(NodeGen.token(node.token), node.index);
        } else if (node.type === "number") {
            return NodeGen.wrap(NodeGen.number(node.number), node.index);
        } else if (node.type === "operator") {
            const parts = [
                generate(node.left),
                NodeGen.wrap(NodeGen.operator(node.operator), node.index),
                generate(node.right)
            ];
            return parts.join("");
        } else {
            return NodeGen.wrap(NodeGen.wildcard(), node.index);
        }
    }

    const result = nodes.length > 0 ? generate(nodes[0]) : "";
    return {
        nodes,
        html: inline ? result : `<div class="mur-expression mur-icon">${result}</div>`
    }
}

function createNotesDialog(numberCount, isCoop, tokens, questions, inspections, votes) {
    function createToken(token) {
        return NodeGen.token(token, 0);
    }

    const numbers = [1, 2, 3, 4, 5, 6, 7].slice(0, numberCount);
    if (!isCoop) {
        numbers.push("&#x1F496;");
    }

    const header = numbers.map(n =>
        `<div class="mur-single-number" data-number="${n}"></div>`);
    const rows = tokens.map(token => {
        const squares = numbers.map(n =>
            `<div class="mur-notes-square" data-number="${typeof n === "number" ? n : 0}"></div>`);

        return `<div class="mur-notes-row" data-token="${token}">
            ${createToken(token)}
            ${squares.join("")}
        </div>`;
    });

    const toolIcons = [
        "fa6-solid fa6-arrows-spin mur-selected",
        "fa6-solid fa6-eraser",
        "fa6-regular fa6-circle-check",
        "fa6-solid fa6-square-xmark",
        "fa6-solid fa6-question"
    ];
    const tools = toolIcons.map((icon, index) =>
        `<div class="mur-notes-tool ${icon}" data-mode="${index - 1}"></div>`);

    const questionRows = questions.map(question => {
        const questionContent = "expression" in question ?
            createExpression(question.expression, question.tokens, true).html :
            `${createToken(question.tile)}
             ${NodeGen.wrap(NodeGen.operator("="))}
             ${question.numbers
                .map(n => `<div class="mur-single-number" data-number="${n}"></div>`)
                .join("")}`;

        const answer = question.answer == null ? "" : parseInt(question.answer) ?
            `<i class="fa6-solid fa6-check"></i>` : `<i class="fa6-solid fa6-xmark"></i>`;

        return `<div class="mur-notes-question">
            ${createToken(question.player)}
            <i class="fa6-regular fa6-hand-point-right"></i>            
            ${createToken(question.recipient)}
            <i class="fa6-regular fa6-comment-dots"></i>
            &nbsp;
            ${questionContent}       
            <i class="fa6-solid fa6-question"></i>
            &nbsp;
            ${answer}
        </div>`;
    });

    const diagramTokens = tokens.map((token, index) => {
        const angle = index * 2 * Math.PI / tokens.length;
        const style = `--cx: ${Math.sin(angle)}; --cy: ${-Math.cos(angle)}`;
        return `<div class="mur-diagram-token" style="${style}">${createToken(token)}</div>`;
    });

    function createArrows(arrows) {
        return arrows.map(points => {
            const [from, to] = points.map(p => {
                const angle = tokens.findIndex(t => parseInt(t) === parseInt(p)) * 2 * Math.PI / tokens.length;
                return {x: Math.sin(angle), y: -Math.cos(angle)};
            });
            const length = Math.sqrt(Math.pow(to.x - from.x, 2) + Math.pow(to.y - from.y, 2));
            const dir = {
                x: 0.25 * (to.x - from.x) / length,
                y: 0.25 * (to.y - from.y) / length
            }
            const arrow = 0.3;
            const path = `M${from.x + dir.x},${from.y + dir.y}L${to.x - dir.x},${to.y - dir.y}M${to.x - (1 + arrow) * dir.x - arrow * dir.y},${to.y - (1 + arrow) * dir.y + arrow * dir.x}L${to.x - dir.x},${to.y - dir.y}L${to.x - (1 + arrow) * dir.x + arrow * dir.y},${to.y - (1 + arrow) * dir.y - arrow * dir.x}`;
            return {
                back: `<path class="mur-path-back" d="${path}"></path>`,
                front: `<path d="${path}"></path>`
            };
        });
    }

    const inspectionArrows = createArrows(inspections);
    const votingArrows = createArrows(votes);

    return `<div>
        <div class="mur-notes">
            <div class="mur-notes-editor">
                <div class="mur-notes-table">
                    <div class="mur-notes-row">${header.join("")}</div>
                    ${rows.join("")}
                </div>          
                <div class="mur-notes-tools">
                    ${tools.join("")}
                </div>
            </div>
            <div class="mur-notes-reference">
                <div class="mur-notes-questions">
                    ${questionRows.join("")}
                </div>     
                <div class="mur-notes-inspections">
                    <div class="mur-diagram-icon">
                        <i class="fa6-solid fa6-eye"></i>
                    </div>
                    <svg class="mur-diagram-svg" viewBox="-1 -1 2 2">
                        <circle r="0.95"></circle>
                        ${inspectionArrows.map(a => a.back).join("")}
                        ${inspectionArrows.map(a => a.front).join("")}
                    </svg>
                    ${diagramTokens.join("")}                    
                </div>
                <div class="mur-notes-votes hidden">
                    <div class="mur-diagram-icon">
                        <i class="fa6-solid fa6-star"></i>
                    </div>
                    <svg class="mur-diagram-svg" viewBox="-1 -1 2 2">
                        <circle r="0.95"></circle>
                        ${votingArrows.map(a => a.back).join("")}
                        ${votingArrows.map(a => a.front).join("")}
                    </svg>
                    ${diagramTokens.join("")}                    
                </div>
            </div>                     
        </div>      
    </div>`;
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

function createMultiQuestionDialog(header, questions) {
    questions = questions.map((question, index) =>
        `<div class="mur-common-question" data-index="${index}">${question}</div>`);
    return `<div id="mur-question-dialog-content">
        <div>
            ${questions.join("")}
        </div>
        <div class="hidden">
            <div class="mur-question-text">${header}</div>
            <div class ="mur-expression"></div>
            <div id="mur-question-dialog-buttons"></div>
        </div>
    </div>`;
}

const NodeGen = {
    wrap(content, nodeIndex) {
        const nodeData = nodeIndex === undefined ? "" : ` data-node="${nodeIndex}"`;
        return `<div class="mur-expression-slot"${nodeData}>${content}</div>`;
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
        this.selectedTile = null;
        this.selectedPlayer = null;
        this.questionTiles = new Set();
        this.tokenTiles = Array(8).fill(null);
        this.activeDialog = null;

        try {
            this.useOffsetAnimation = CSS.supports("offset-path", "path('M 0 0')");
        } catch (e) {
            this.useOffsetAnimation = false;
        }
    },

    setup(data) {
        this.gameMode = parseInt(data.mode);
        this.isCoop = parseInt(data.coop);
        this.round = parseInt(data.round);

        const status = document.getElementById("mur-status");
        status.firstElementChild.innerText = _("Round");
        status.lastElementChild.innerText = this.isCoop ? _("Cooperative") : _("Competitive");
        const activeRoundMarker = status.querySelector(`:nth-child(${this.round + 1})`);
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
                    this.onPlaceholderClick(place);
                });
            }
        }

        const players = data.players;
        const playerIds = Object.keys(players);
        const playerCount = playerIds.length;

        const captain = players[data.captain];
        if (captain) {
            captain.isCaptain = true;
        }

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
                    this.onPlayerClick(player);
                });
            }

            createElement(playerArea, `<div class="mur-tile-container"></div>`);
            createElement(playerArea, `<div class="mur-token-container"></div>`);
        }

        const wins = parseInt(data.wins);
        for (const playerId of playerIds) {
            const player = players[playerId];
            const index = sortKey(player);

            createPlayerArea.call(this, player, index);
            const panel = document.getElementById(`player_board_${playerId}`);
            const score = createElement(panel, createScore(playerId, player.token, this.round));

            const cardsCount = data.gamestate.name === "gameEnd" ? 3 : this.round -1;
            for (let i = 0; i < cardsCount; ++i) {
                const card = score.children[i + 1];
                const record = wins >> i * 9;
                const roundWon = (record & 1 << parseInt(player.token) + 1) !== 0;

                card.classList.toggle("mur-loss", !roundWon);
                card.dataset.team = (record & 1) ^ roundWon ? "witch" : "knights";
            }

            if (playerId === this.getCurrentPlayerId().toString()) {
                const notesButton = createElement(panel,
                    `<div id="mur-notes-button" class="fa6-regular fa6-clipboard"></div>`);
                notesButton.addEventListener("mousedown", () => this.notesDialog());
            }
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

            tile.addEventListener("click", event => {
                event.stopPropagation();
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

        for (const playerId of playerIds) {
            const player = players[playerId];
            if (player.voted !== null) {
                const place = document.querySelector(`#mur-player-${player.voted} .mur-token-container`);
                createElement(place, createToken(player.token));
            }
        }

        this.setupNotifications();
    },

    onEnteringState(stateName, state) {
        if (this.isCurrentPlayerActive()) {
            switch (stateName) {
                case "inspect": {
                    const stage = "stage" in state.args ? state.args.stage : null;
                    const playerId = this.getCurrentPlayerId();
                    const constraint =
                        stage === 0 ? `:not(#mur-player-none):not(#mur-player-${playerId})` :
                        stage === 1 ? `#mur-player-none` :
                                      `:not(#mur-player-${playerId})`;
                    for (const tile of document.querySelectorAll(`.mur-player${constraint} .mur-tile`)) {
                        tile.classList.add("mur-selectable");
                    }
                    for (const tile of document.querySelectorAll(`.mur-player .mur-tile`)) {
                        if (!tile.classList.contains("mur-selectable")) {
                            tile.classList.add("mur-non-selectable");
                        }
                    }

                    break;
                }
                case "question": {
                    for (const player of document.querySelectorAll(".mur-player")) {
                        if (player.dataset.player !== "none"
                            && player.dataset.player !== this.getCurrentPlayerId().toString())
                        {
                            player.classList.add("mur-selectable");
                        }
                    }
                    break;
                }
                case "clientSelectTiles": {
                    const inspectedTiles = this.gamedatas.inspections
                        .filter(inspection => inspection.player_id === this.selectedPlayer.dataset.player)
                        .map(inspection => inspection.tile_id);

                    for (const tile of document.querySelectorAll(".mur-player .mur-tile")) {
                        const isValidTile = !tile.classList.contains("mur-flipped")
                            && (this.selectedPlayer.contains(tile)
                                || inspectedTiles.indexOf(tile.dataset.id) >= 0);
                        tile.classList.add(isValidTile ? "mur-selectable" : "mur-non-selectable");
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
                this.displayQuestion(state.args.question);
                break;
            }
        }
    },

    onLeavingState(stateName) {
        if (this.isCurrentPlayerActive()) {
            switch (stateName) {
                case "clientSelectTiles":
                    clearTag("mur-selected");
                    this.questionTiles.clear();
                //fallthrough
                case "inspect":
                    clearTag("mur-non-selectable");
                //fallthrough
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
                this.removeBubble(document.querySelector(".mur-bubble"));
                break;
            }
        }
    },

    onUpdateActionButtons(stateName, args) {
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
                        this.questionDispatch();
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
                    const witchTile =
                        document.querySelector(`.mur-tile.mur-flipped[data-character="0"]`);
                    const witchPlayer =
                        witchTile && witchTile.closest(".mur-player:not(#mur-player-none)");
                    const currentPlayer =
                        document.getElementById(`mur-player-${this.getCurrentPlayerId()}`);
                    const currentTile =
                        currentPlayer.querySelector(".mur-tile");

                    for (const player of document.querySelectorAll(".mur-player")) {
                        const isValidPlayer =
                            player.dataset.player !== "none"
                            && player !== witchPlayer
                            && (currentPlayer !== witchPlayer
                                || this.gamedatas.inspections.every(inspection =>
                                    inspection.player_id !== player.dataset.player
                                    || inspection.tile_id !== currentTile.dataset.id));

                        if (player === currentPlayer || isValidPlayer) {
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
        args ||= {};
        if (!("lock" in args)) {
            args.lock = true;
        } else {
            delete args.lock;
        }
        this.ajaxcall(`/${gameName}/${gameName}/${action}.html`, args, () => {
            if (typeof onSuccess === "function") {
                onSuccess();
            }
        });
    },

    selectItem(item, property) {
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

    notesDialog() {
        const numbersCount = this.gameMode === GameMode.standard ?
            this.gamedatas.tiles.length + this.isCoop - 1 : 7;
        const tokens = [];

        const players = Object.keys(this.gamedatas.players).map(id => this.gamedatas.players[id]);
        const firstPlayer = this.gamedatas.players[this.gamedatas.firstPlayer];
        const currentPlayer = this.gamedatas.players[this.getCurrentPlayerId()];

        if (firstPlayer) {
            function sortKey(player) {
                return (parseInt(player.no) - firstPlayer.no + players.length) % players.length;
            }
            players.sort((p1, p2) => sortKey(p1) - sortKey(p2));
            tokens.push(...players.map(player => player.token));
            this.tokenTiles.forEach((tile, token) => {
                if (tile !== null && tile.player_id === null) {
                    tokens.push(token);
                }
            });
        }

        const questions = this.gamedatas.questions.map(question => {
            const player = this.gamedatas.players[question.player_id].token;
            const recipient = this.gamedatas.players[question.recipient_id].token;
            if ("expression" in question && question.expression !== null) {
                const tiles = Array.isArray(question.expression_tiles) ?
                    question.expression_tiles :
                    question.expression_tiles.split(",");
                return {
                    player,
                    recipient,
                    expression: question.expression,
                    tokens: tiles.map(id =>
                        this.tokenTiles.indexOf(
                            this.gamedatas.tiles.find(tile => tile.id === id))),
                    answer: question.answer
                }
            } else {
                let bitset = parseInt(question.question);
                const numbers = [];
                for (let i = 0; i < 7; ++i) {
                    if (bitset & 1 << i) {
                        numbers.push(i + 1);
                    }
                }

                return {
                    player,
                    recipient,
                    numbers,
                    tile: this.tokenTiles.indexOf(
                        this.gamedatas.tiles.find(tile => parseInt(tile.id) === parseInt(question.tile_id))),
                    answer: question.answer
                }
            }
        });

        const inspections = this.gamedatas.inspections.map(inspection => {
            const tileId = parseInt(inspection.tile_id);
            const from = this.gamedatas.players[inspection.player_id].token;
            const to = this.tokenTiles.indexOf(
                this.gamedatas.tiles.find(tile => parseInt(tile.id) === tileId));
            return [from, to];
        });

        const votes = Object.keys(this.gamedatas.players).map(playerId => {
            const player = this.gamedatas.players[playerId];
            if (player.voted) {
                return [player.token, this.gamedatas.players[player.voted].token];
            }
        }).filter(vote => vote);

        const dialog = new ebg.popindialog();
        dialog.create("mur-notes-dialog");
        dialog.setTitle(_("Notes"));
        dialog.setContent(createNotesDialog(numbersCount, this.isCoop, tokens, questions, inspections, votes));
        dialog.bCloseIsHiding = true;
        dialog.onHide = () => {
            const values = [];

            for (const row of document.querySelectorAll(".mur-notes-row")) {
                let value = 0;
                for (const square of row.querySelectorAll(".mur-notes-square")) {
                    const mark = square.dataset.mark || 0;
                    value = value << 2 | (mark & 0b11);
                }
                values.push(value);
            }

            const notes = values.join(",");
            if (notes !== this.gamedatas.players[this.getCurrentPlayerId()].notes) {
                this.request("updateNotes", {notes, lock: false});
            }

            dialog.destroy(1000);
            this.activeDialog = null;
        };
        dialog.show();
        this.activeDialog = dialog;

        const notes = (this.gamedatas.players[this.getCurrentPlayerId()].notes || "").split(",");
        const rows = Array.from(document.querySelectorAll(".mur-notes-row"));

        rows.forEach((row, i) => {
            const value = notes[i] || 0;
            const squares = Array.from(row.querySelectorAll(".mur-notes-square"));
            squares.forEach((square, j) => {
                const fixed = row.dataset.token === currentPlayer.token &&
                    this.tokenTiles[currentPlayer.token].character === square.dataset.number
                    || inspections.some(([from, to]) =>
                        from === currentPlayer.token
                            && to === parseInt(row.dataset.token)
                            && this.tokenTiles[to].character === square.dataset.number);

                if (fixed) {
                    square.dataset.mark = "1";
                    square.classList.add("mur-inactive");
                } else {
                    square.dataset.mark = (value >> (squares.length - j - 1) * 2 & 0b11).toString();
                    square.addEventListener("mousedown", () => {
                        const toolMode = parseInt(document.querySelector(".mur-notes-tool.mur-selected").dataset.mode);
                        if (toolMode >= 0) {
                            const value = parseInt(square.dataset.mark) === toolMode ? 0 : toolMode;
                            square.dataset.mark = value.toString();
                        } else {
                            const mark = parseInt(square.dataset.mark) || 0;
                            square.dataset.mark = ((mark + 1) % 4).toString();
                        }
                    });
                }
            });
        });

        const tools = Array.from(document.querySelectorAll(".mur-notes-tool"));
        for (const tool of tools) {
            tool.addEventListener("mousedown", () => {
                tools.forEach(other => other.classList.remove("mur-selected"));
                tool.classList.add("mur-selected");
            })
        }

        const diagrams = document.querySelectorAll(".mur-notes-inspections, .mur-notes-votes");
        for (const diagram of diagrams) {
            diagram.style.setProperty("--player-color", `#${currentPlayer.color}`);
            diagram.addEventListener("mousedown", () => {
                for (const diagram of diagrams) {
                    diagram.classList.toggle("hidden");
                }
            })
        }
    },

    questionDispatch() {
        if (this.questionTiles.size === 1) {
            this.questionDialog(this.selectedPlayer, this.questionTiles.keys().next().value);
        } else {
            this.multiQuestionDialog(this.selectedPlayer, Array.from(this.questionTiles));
        }
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
        const nodes = [];

        const icons = {};
        tiles.forEach((tile, index) =>
            icons[`tokenIcon${index + 1}`] = `tile,${tile.dataset.id}`);

        const questions = tiles.length === 2 ? [{
                text: _("Is tile ${tokenIcon1} less than tile ${tokenIcon2}?"),
                expression: "abl"
            }, {
                text: _("Is the sum of tiles ${tokenIcon1} and ${tokenIcon2} less than...?"),
                expression: "abp?l"
            }, {
                text: _("Are tiles ${tokenIcon1} and ${tokenIcon2} next to each other?"),
                expression: "ab1peba1peo"
            }, {
                text: _("Custom Question")
            }
        ] : [{
                text: _("Is the sum of tiles ${tokenIcon1}, ${tokenIcon2} and ${tokenIcon3} greater than...?"),
                expression: "?abcppl"
            }, {
                text: _("Custom Question")
            }
        ];

        const dialog = new ebg.popindialog();
        dialog.create("mur-question-dialog");
        dialog.setTitle(_("Choose a question"));
        dialog.setContent(createMultiQuestionDialog(_("Is the following true..."),
            questions.map(question => this.format_string_recursive(question.text, icons))));
        dialog.show();

        const content = document.getElementById("mur-question-dialog-content");

        for (const question of content.querySelectorAll(".mur-common-question")) {
            question.addEventListener("mousedown", () => {
                content.children[0].classList.add("hidden");
                content.children[1].classList.remove("hidden");
                reset(questions[parseInt(question.dataset.index)].expression);
            });
        }

        function reset(expression) {
            expression ||= "?";
            const {nodes: newNodes, html} = createExpression(expression, tokens, true);

            const linearNodes = [];

            function linearize(node) {
                linearNodes.push(node);
                if (node.type === "operator") {
                    node.left = linearize(node.left);
                    node.right = linearize(node.right);
                } else if (node.type === "token"){
                    node.token = tokens.indexOf(node.token);
                }
                return node.index;
            }
            linearize(newNodes[0]);

            nodes.splice(0, nodes.length, ...linearNodes);

            const element = document.querySelector(".mur-expression:not(.mur-icon)");
            element.innerHTML = html;

            const wildcards = element.querySelectorAll(".mur-expression-wildcard");
            for (const wildcard of wildcards) {
                initWildcard(wildcard,nodes.length > 1 ? 1 : 0);
            }
            askButton.classList.toggle("disabled", wildcards.length > 0);
        }

        function ask() {
            const expression = [];
            const operators = {
                "<": "l",
                "=": "e",
                "+": "p",
                "&": "n",
                "|": "o"
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
        this.addActionButton("mur-question-dialog-reset", _("Reset"), () => reset(), "mur-question-dialog-buttons", null, "gray");

        const askButton = document.getElementById("mur-question-dialog-ask");
        askButton.classList.add("disabled");

        function generate(level, operator) {
            const result = [];

            if (level === 0) {
                result.push(NodeGen.operator('&'));
                result.push(NodeGen.operator('|'));
            }

            if (level === 0 || ["&", "|"].indexOf(operator) >= 0 ) {
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

                    const nodeIndex = parseInt(element.parentElement.dataset.node);
                    const operatorNode = nodes.find(node =>
                        node.type === "operator" && (node.left === nodeIndex || node.right === nodeIndex));
                    const operator = operatorNode && operatorNode.operator;

                    const slot = event.target.parentElement;
                    const selector = createElement(slot,
                        `<div class="mur-expression-selector">${generate(level, operator)}</div>`);

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
        const playerSize = 200;
        const px = playerSize * cos;
        const py = playerSize * sin;
        const size = 15;
        const path = `M${width / 2 + px / 4},${height / 2 + py / 4} L${width / 2 + px +  sin * size},${height / 2 + py - cos * size} L${width / 2 + px - sin * size},${height / 2 + py + cos * size} Z`;
        svg.firstElementChild.setAttribute("d", path);

        setTimeout(() => bubble.classList.add("mur-animated"), 0);
        if (timeout !== undefined) {
            setTimeout(() => this.removeBubble(bubble), timeout);
        }
    },

    removeBubble(bubble) {
        if (bubble) {
            bubble.classList.remove("mur-animated");
            setTimeout(() => bubble.parentElement.removeChild(bubble), 250);
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
                    tileOwner === null ?
                        _("${tokenIcon1}${player_name1}, is ${tokenIcon2}Knight-errant's tile ${numberIcon}?") :
                        _("${tokenIcon1}${player_name1}, is ${tokenIcon2}${player_name2}'s tile ${numberIcon}?")) :
                (tileOwner === recipient.id ?
                    _("${tokenIcon1}${player_name1}, is your tile one of ${numberIcon}?") :
                    tileOwner === null ?
                        _("${tokenIcon1}${player_name1}, is ${tokenIcon2}Knight-errant's tile one of ${numberIcon}?") :
                        _("${tokenIcon1}${player_name1}, is ${tokenIcon2}${player_name2}'s tile one of ${numberIcon}?"));
            const args = {
                player_name1: `<span style="color: #${recipient.color}">${recipient.name}</span>`,
                tokenIcon1: `player,${recipient.name}`,
                numberIcon: question.question
            };

            if (tileOwner !== recipient.id) {
                if (tileOwner === null) {
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

    animateJump(item, delayMs) {
        item.style.setProperty("--jump-delay", `${delayMs}ms`);
        item.classList.add("mur-jump");

        const handler = event => {
            if (event.animationName === "fall-path") {
                item.removeEventListener("animationend", handler);
                item.classList.remove("mur-jump");
                item.style.setProperty("--jump-delay", `0ms`);
            }
        };
        item.addEventListener("animationend", handler);
    },

    revealCharacter(tileId, character) {
        const tile = document.querySelector(`#mur-tile-${tileId}`);
        tile.style.setProperty("--character", character);

        const descriptor = this.gamedatas.tiles.find(tile => tile.id === tileId.toString());
        descriptor.character = character.toString();

        if (!tile.classList.contains("mur-flipped")) {
            tile.classList.add("mur-flipped");
            tile.dataset.character = character.toString();

            const player = tile.closest(".mur-player");

            if (player) {
                for (const token of player.querySelectorAll(".mur-token-container .mur-token")) {
                    this.animateJump(token, 150);
                }
            }
            return true;
        }
        return false;
    },

    castBewitching() {
        const tile = document.querySelector(`#mur-player-${this.getCurrentPlayerId()} .mur-tile`);
        if (tile) {
            const flash = createElement(tile, `<div class="mur-flash"></div>`);
            setTimeout(() => flash.classList.add("mur-animated"), 800);

            let sparks = [];
            if (this.useOffsetAnimation) {
                setTimeout(() => {
                    sparks = Array(12).fill(null).map(() => {
                        const size = 8 * (1 + Math.random());
                        const shiftX = (1 + 0.4 * Math.random()) * (Math.random() >= 0.5 ? size : -size);
                        const shiftY = (1 + 0.4 * Math.random()) * -size;
                        const path = `path('M0,0q${shiftX},${shiftY} 0,${shiftY * 2} t0,${shiftY * 2}')`;
                        const style = `--x: ${Math.random()}; --y: ${Math.random()}; --size: ${size}; --path: ${path}`;
                        const html = `<div class="mur-spark fa6-solid fa6-heart" style="${style}"></div>`;
                        return createElement(tile, html);
                    });
                }, 850);
            }

            setTimeout(() => {
                tile.removeChild(flash);
                for (const spark of sparks) {
                    tile.removeChild(spark);
                }
            }, 3500);
        }
    },

    moveTile(tileId, position) {
        const tile = document.getElementById(`mur-tile-${tileId}`);
        const place = document.getElementById(`mur-placeholder-${position}`);
        const moves = [{tile, place}];

        const player = tile.closest(".mur-player");

        if (player) {
            for (const token of player.querySelectorAll(".mur-token-container .mur-token")) {
                this.animateJump(token, 150);
            }
        }

        const oldTile = place.firstElementChild;

        if (oldTile) {
            const returnPlace = tile.parentElement.classList.contains("mur-inactive") ?
                document.getElementById(`mur-tile-placeholder-${oldTile.dataset.id}`) :
                tile.parentElement;

            moves.push({
                tile: oldTile,
                place: returnPlace
            });

            const returnPlayer = returnPlace.closest(".mur-player");

            if (returnPlayer) {
                for (const token of returnPlayer.querySelectorAll(".mur-token-container .mur-token")) {
                    this.animateJump(token, 700);
                }
            }
        }
        this.animateTiles(moves);
    },

    animateSword(playerId) {
        const firstPlayer = document.getElementById(`mur-player-${playerId}`);
        const sword = document.getElementById("mur-sword");

        if (sword.parentElement === firstPlayer) {
            return;
        }
        this.gamedatas.firstPlayer = playerId;

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
        this.gamedatas.inspections = [];
        this.gamedatas.questions = [];
        for (const playerId of Object.keys(this.gamedatas.players)) {
            this.gamedatas.players[playerId].voted = null;
        }

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

        for (const token of document.querySelectorAll(".mur-token-container .mur-token")) {
            this.animatePlayerRemove(
                this.tokenTiles[parseInt(token.dataset.token)].player_id,
                token);
        }

        const star = document.querySelector(".mur-star.mur-active");
        if (star) {
            star.classList.remove("mur-active");
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

            const button = document.getElementById("mur-ask");
            button.classList.toggle("disabled", this.questionTiles.size === 0);
            button.innerText = this.questionTiles.size === 0 ?
                _("Ask") :
                this.format_string_recursive(_("Ask about ${count} tile(s)"), {count: this.questionTiles.size});

            if (document.querySelectorAll(".mur-tile.mur-selectable").length === this.questionTiles.size) {
                this.questionDispatch();
            }
        } else if (this.checkAction("deploy", true)) {
            this.selectTile(tile);
            this.setClientState("clientDeploy", {
                descriptionmyturn: _("${you} must select a space for the tile"),
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
        dojo.subscribe("round", this, () => this.roundCleanup());
        this.notifqueue.setSynchronous('round', 800);

        dojo.subscribe("inspect", this, data => {
            this.gamedatas.inspections.push({
                player_id: data.args.playerId,
                tile_id: data.args.tileId,
            });
            this.animateArrow(data.args.playerId, data.args.tileId);
        });

        dojo.subscribe("reveal", this, data => {
            const delay = this.revealCharacter(data.args.tileId, data.args.character) ? 1000 : 0;
            this.notifqueue.setSynchronousDuration(delay);

            if (data.args.bewitch) {
                this.castBewitching();
            }
        });
        this.notifqueue.setSynchronous("reveal");

        dojo.subscribe("question", this, data => {
            this.gamedatas.questions.push(data.args.question);
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
            this.gamedatas.questions[this.gamedatas.questions.length - 1].answer = data.args.answer;
            this.addBubble(player, text, 2000);
        });
        this.notifqueue.setSynchronous("answer", 1000);

        dojo.subscribe("vote", this, data => {
            const tokens = [];
            let voters = parseInt(data.args.voters);

            while (voters !== 0) {
                const token = 31 - Math.clz32(voters);
                tokens.push(token);
                this.gamedatas.players[this.tokenTiles[token].player_id].voted = data.args.playerId;
                voters &= ~(1 << token);
            }

            this.animateTokens(data.args.playerId, tokens);
        });
        this.notifqueue.setSynchronous("vote", 1000);

        dojo.subscribe("appoint", this, data => {
            const star = document.querySelector(`#mur-player-${data.args.playerId} .mur-star`);
            if (star) {
                star.classList.add("mur-active");
            }
        });

        dojo.subscribe("move", this, data => {
            this.moveTile(data.args.tileId, data.args.position);
        });

        this.notifqueue.setSynchronous("order", 1000);

        dojo.subscribe("score", this, data => {
            const args = data.args;

            if ("score" in args) {
                const score = parseInt(args.score);
                for (const playerId of args.players) {
                    this.scoreCtrl[parseInt(playerId)].incValue(score);
                }
            }

            for (const playerId of Object.keys(this.gamedatas.players)) {
                const scoreCards = document.getElementById(`mur-player-score-${playerId}`);
                const card = scoreCards.children[this.round];
                const roundWon = args.players && args.players.indexOf(playerId) >= 0;
                card.classList.toggle("mur-loss", !roundWon);
                card.dataset.team = roundWon ^ args.knightsWin ? "witch" : "knights";
            }
        });

        this.notifqueue.setSynchronous("score", 1000);

        dojo.subscribe("round", this, data => {
            ++this.round;
            const activeRoundMarker = document.querySelector(".mur-round-marker.mur-current");

            if (activeRoundMarker) {
                activeRoundMarker.classList.remove("mur-current");
                activeRoundMarker.nextElementSibling.classList.add("mur-current");
            }

            this.animateSword(data.args.firstPlayer);
        });

        dojo.subscribe("notes", this, data => {
            this.gamedatas.players[this.getCurrentPlayerId()].notes = data.args.notes;

            if (this.activeDialog !== null) {
                const notes = (data.args.notes || "").split(",");
                const rows = Array.from(document.querySelectorAll(".mur-notes-row"));

                rows.forEach((row, i) => {
                    const value = notes[i] || 0;
                    const squares = Array.from(row.querySelectorAll(".mur-notes-square"));
                    squares.forEach((square, j) =>
                        square.dataset.mark = (value >> (squares.length - j - 1) * 2 & 0b11).toString());
                });
            }
        });
    },

    formatName(id) {
        const player = this.gamedatas.players[id];
        return player ?
            `<span class="mur-name-icon" style="color: #${player.color};">${player.name}</span>` :
            `<span style="color: #199c97;">Knight-errant</span>`;
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
        return createExpression(expression, tokens).html;
    },

    format_string_recursive(log, args) {
        if (args && !("substitutionComplete" in args)) {
            args.substitutionComplete = true;
            const formatters = {
                name: this.formatName,
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