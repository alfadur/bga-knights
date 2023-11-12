
class Game {
    isCurrentPlayerActive() { return false; }
    checkAction(name, required) { return false; }
    addActionButton(id, text, handler, _1, _2, style) {}
    ajaxcall(url, args, onSuccess, onFailure) {}
}


/*
function define(modules, impl) {
    const game = new Game();
    function declare(name, module, object) {
        Object.assign(game, object);
    }
    impl({}, declare)
}*/
