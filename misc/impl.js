
class notifqueue {
    setSynchronous(name, timeMs) {}
    setSynchronousDuraction(timeMs) {}
}
class Game {
    isCurrentPlayerActive() { return false; }
    getCurrentPlayerId() { return 0; }
    checkAction(name, required) { return false; }
    addActionButton(id, text, handler, _1, _2, style) {}
    ajaxcall(url, args, onSuccess, onFailure) {}
    get notifqueue() {
        return new notifqueue();
    }
}


/*
function define(modules, impl) {
    const game = new Game();
    function declare(name, module, object) {
        Object.assign(game, object);
    }
    impl({}, declare)
}*/
