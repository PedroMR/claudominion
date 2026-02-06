<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClauDominion - Deck Building Game</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #1a1a2e;
            color: #eee;
            min-height: 100vh;
        }
        .hidden { display: none !important; }

        /* Lobby */
        .lobby, .waiting-room {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
        }
        .lobby h1, .waiting-room h2 { font-size: 2.5rem; margin-bottom: 1.5rem; color: #e94560; }
        .lobby-actions { display: flex; flex-direction: column; gap: 1rem; width: 100%; max-width: 400px; }
        .lobby input, .waiting-room input {
            padding: 0.75rem 1rem; font-size: 1rem; border: 2px solid #333;
            border-radius: 8px; background: #16213e; color: #eee;
        }
        .lobby input:focus { outline: none; border-color: #e94560; }
        .lobby button, .waiting-room button {
            padding: 0.75rem 1rem; font-size: 1rem; border: none; border-radius: 8px;
            background: #e94560; color: white; cursor: pointer;
        }
        .lobby button:hover { background: #c73e54; }
        .lobby button:disabled, .waiting-room button:disabled { background: #666; cursor: not-allowed; }
        .join-section { display: flex; gap: 0.5rem; }
        .join-section input { flex: 1; }
        .divider { text-align: center; color: #666; margin: 0.5rem 0; }
        .room-code {
            font-size: 2.5rem; font-weight: bold; color: #e94560; background: #16213e;
            padding: 1rem 2rem; border-radius: 8px; margin-bottom: 1.5rem; letter-spacing: 0.5rem;
        }
        .players-list { margin-bottom: 1.5rem; }
        .players-list h3 { margin-bottom: 0.5rem; }
        .players-list ul { list-style: none; }
        .players-list li { padding: 0.5rem; background: #16213e; margin-bottom: 0.25rem; border-radius: 4px; }

        /* Game Board */
        .game-board { display: grid; grid-template-rows: auto 1fr auto; min-height: 100vh; padding: 1rem; gap: 1rem; }
        .game-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 0.5rem 1rem; background: #16213e; border-radius: 8px;
        }
        .turn-info { display: flex; gap: 1.5rem; align-items: center; }
        .turn-info .current-player { font-weight: bold; color: #e94560; }
        .turn-info .phase { background: #0f3460; padding: 0.25rem 0.75rem; border-radius: 4px; }
        .turn-state { display: flex; gap: 1rem; }
        .turn-state span { background: #0f3460; padding: 0.25rem 0.75rem; border-radius: 4px; }
        .game-main { display: grid; grid-template-columns: 1fr 250px; gap: 1rem; }
        .supply-area { display: flex; flex-direction: column; gap: 1rem; }
        .supply-section h3 { margin-bottom: 0.5rem; color: #888; font-size: 0.875rem; text-transform: uppercase; }
        .supply-cards { display: flex; flex-wrap: wrap; gap: 0.5rem; }
        .supply-card {
            padding: 0.75rem 1rem; background: #16213e; border: 2px solid #333;
            border-radius: 8px; cursor: pointer; transition: all 0.2s; min-width: 120px;
        }
        .supply-card:hover:not(.disabled) { border-color: #e94560; transform: translateY(-2px); }
        .supply-card.disabled { opacity: 0.5; cursor: not-allowed; }
        .supply-card .card-name { font-weight: bold; margin-bottom: 0.25rem; }
        .supply-card .card-cost { font-size: 0.875rem; color: #ffd700; }
        .supply-card .card-count { font-size: 0.75rem; color: #888; }
        .supply-card.treasure { border-left: 4px solid #ffd700; }
        .supply-card.victory { border-left: 4px solid #4caf50; }
        .supply-card.action { border-left: 4px solid #2196f3; }
        .sidebar { display: flex; flex-direction: column; gap: 1rem; }
        .players-info, .game-log { background: #16213e; border-radius: 8px; padding: 1rem; }
        .players-info h3, .game-log h3 { margin-bottom: 0.5rem; color: #888; font-size: 0.875rem; text-transform: uppercase; }
        .player-info { padding: 0.5rem; background: #0f3460; border-radius: 4px; margin-bottom: 0.5rem; }
        .player-info.current { border-left: 3px solid #e94560; }
        .player-info .player-name { font-weight: bold; margin-bottom: 0.25rem; }
        .player-info .player-stats { font-size: 0.75rem; color: #888; }
        .game-log { flex: 1; overflow-y: auto; max-height: 300px; }
        .game-log .log-entry { font-size: 0.75rem; padding: 0.25rem 0; border-bottom: 1px solid #0f3460; }
        .player-area { background: #16213e; border-radius: 8px; padding: 1rem; }
        .card-section { margin-bottom: 1rem; }
        .card-section h4 { color: #888; font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.5rem; }
        .card-row { display: flex; gap: 0.5rem; min-height: 60px; overflow-x: auto; }
        .card {
            padding: 0.75rem 1rem; background: #0f3460; border: 2px solid #333;
            border-radius: 8px; cursor: pointer; transition: all 0.2s; min-width: 100px;
        }
        .card:hover:not(.disabled) { border-color: #e94560; transform: translateY(-4px); }
        .card.disabled { opacity: 0.5; cursor: not-allowed; }
        .card .card-name { font-weight: bold; font-size: 0.875rem; }
        .card .card-desc { font-size: 0.625rem; color: #888; margin-top: 0.25rem; }
        .card.treasure { border-left: 4px solid #ffd700; }
        .card.victory { border-left: 4px solid #4caf50; }
        .card.action { border-left: 4px solid #2196f3; }
        .card.in-play-card { cursor: default; }
        .action-buttons { display: flex; gap: 0.5rem; margin-top: 1rem; }
        .action-buttons button {
            padding: 0.5rem 1rem; border: none; border-radius: 4px;
            background: #e94560; color: white; cursor: pointer; font-size: 0.875rem;
        }
        .action-buttons button:hover { background: #c73e54; }
        .action-buttons button:disabled { background: #666; cursor: not-allowed; }

        /* Modals */
        .modal {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.8); display: flex; align-items: center;
            justify-content: center; z-index: 100;
        }
        .modal-content { background: #16213e; padding: 2rem; border-radius: 8px; text-align: center; }
        .modal-content h3 { margin-bottom: 1rem; }
        .modal-content .revealed-card { padding: 1rem; background: #0f3460; border-radius: 8px; margin-bottom: 1rem; font-size: 1.25rem; }
        .modal-content .buttons { display: flex; gap: 1rem; justify-content: center; }
        .modal-content button { padding: 0.75rem 1.5rem; border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; }
        .modal-content .discard-btn { background: #e94560; color: white; }
        .modal-content .keep-btn { background: #4caf50; color: white; }
        .modal-content .newGame-btn { background: #e94560; color: white; }
        .game-over-content h2 { font-size: 2rem; margin-bottom: 1rem; color: #e94560; }
        .game-over-content .winner { font-size: 1.5rem; }

        .error-message {
            position: fixed; top: 1rem; right: 1rem; background: #e94560; color: white;
            padding: 1rem; border-radius: 8px; z-index: 200;
        }
    </style>
</head>
<body>
    <!-- Lobby Screen -->
    <div id="lobby" class="lobby">
        <h1>ClauDominion</h1>
        <div class="lobby-actions">
            <input type="text" id="playerName" placeholder="Enter your name" maxlength="20">
            <br/><br/>  
            <button onclick="createRoom()">Create New Game</button>
            <div class="divider">- or -</div>
            <div class="join-section">
                <input type="text" id="roomCode" placeholder="Room code" maxlength="6" style="text-transform: uppercase;">
                <button onclick="joinRoom()">Join</button>
            </div>
        </div>
    </div>

    <!-- Waiting Room -->
    <div id="waitingRoom" class="waiting-room hidden">
        <h2>Waiting for Players</h2>
        <div id="displayRoomCode" class="room-code"></div>
        <p>Share this code with your friends!</p>
        <div class="players-list">
            <h3>Players (<span id="playerCount">0</span>/4)</h3>
            <ul id="playersList"></ul>
        </div>
        <button id="startGameBtn" onclick="startGame()" disabled>Need at least 2 players</button>
    </div>

    <!-- Game Board -->
    <div id="gameBoard" class="game-board hidden">
        <div class="game-header">
            <div class="turn-info">
                <span class="current-player" id="currentPlayerName"></span>
                <span class="phase" id="currentPhase"></span>
            </div>
            <div class="turn-state">
                <span>Actions: <span id="actionsCount">0</span></span>
                <span>Buys: <span id="buysCount">0</span></span>
                <span>Coins: <span id="coinsCount">0</span></span>
            </div>
        </div>

        <div class="game-main">
            <div class="supply-area">
                <div class="supply-section">
                    <h3>Treasure Cards</h3>
                    <div class="supply-cards" id="treasureSupply"></div>
                </div>
                <div class="supply-section">
                    <h3>Victory Cards</h3>
                    <div class="supply-cards" id="victorySupply"></div>
                </div>
                <div class="supply-section">
                    <h3>Action Cards</h3>
                    <div class="supply-cards" id="actionSupply"></div>
                </div>
            </div>

            <div class="sidebar">
                <div class="players-info">
                    <h3>Players</h3>
                    <div id="gamePlayers"></div>
                </div>
                <div class="game-log">
                    <h3>Game Log</h3>
                    <div id="gameLog"></div>
                </div>
            </div>
        </div>

        <div class="player-area">
            <div class="card-section">
                <h4>In Play</h4>
                <div class="card-row" id="inPlayCards"></div>
            </div>
            <div class="card-section">
                <h4>Your Hand</h4>
                <div class="card-row" id="handCards"></div>
            </div>
            <div class="action-buttons" id="actionButtons"></div>
        </div>
    </div>

    <!-- Spy Modal -->
    <div id="spyModal" class="modal hidden">
        <div class="modal-content">
            <h3 id="spyTargetText"></h3>
            <div class="revealed-card" id="spyRevealedCard"></div>
            <div class="buttons">
                <button class="discard-btn" onclick="spyChoice(true)">Discard It</button>
                <button class="keep-btn" onclick="spyChoice(false)">Keep on Top</button>
            </div>
        </div>
    </div>

    <!-- Game Over Modal -->
    <div id="gameOverModal" class="modal hidden">
        <div class="modal-content game-over-content">
            <h2>Game Over!</h2>
            <div class="winner" id="winnerText"></div>
            <div class="scores" id="scoresText"></div>
            <div class="buttons">
                <button class="newGame-btn" onclick="newGame()">New Game</button>
            </div>
        </div>
    </div>

    <!-- Error Message -->
    <div id="errorMessage" class="error-message hidden"></div>

    <script>
        // Generate unique player ID per tab (sessionStorage is tab-specific)
        function getOrCreatePlayerId() {
            let id = sessionStorage.getItem('spies_playerId');
            if (!id) {
                id = 'p_' + Math.random().toString(36).substr(2, 16) + Date.now().toString(36);
                sessionStorage.setItem('spies_playerId', id);
            }
            return id;
        }

        let state = {
            playerId: getOrCreatePlayerId(),
            roomCode: sessionStorage.getItem('spies_roomCode') || null,
            version: 0,
            gameState: null,
            cards: {}
        };
        let pollInterval = null;

        // Helper to build request body with player/room info
        function buildBody(params = {}) {
            const data = new URLSearchParams();
            data.append('playerId', state.playerId);
            if (state.roomCode) data.append('roomCode', state.roomCode);
            for (const [key, value] of Object.entries(params)) {
                data.append(key, value);
            }
            return data;
        }

        // Load card definitions
        fetch('api.php?action=get-cards')
            .then(r => r.json())
            .then(data => { if (data.success) state.cards = data.cards; });

        // Check for existing session
        if (state.roomCode) {
            fetch(`api.php?action=get-state&playerId=${state.playerId}&roomCode=${state.roomCode}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        state.version = data.version;
                        if (data.gameState) {
                            state.gameState = data.gameState;
                            showGame();
                        } else {
                            showWaitingRoom(data.players);
                        }
                        startPolling();
                    } else {
                        // Room no longer exists
                        sessionStorage.removeItem('spies_roomCode');
                        state.roomCode = null;
                    }
                });
        }

        function showError(msg) {
            const el = document.getElementById('errorMessage');
            el.textContent = msg;
            el.classList.remove('hidden');
            setTimeout(() => el.classList.add('hidden'), 3000);
        }

        async function createRoom() {
            const name = document.getElementById('playerName').value.trim();
            if (!name) { showError('Please enter your name'); return; }

            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: buildBody({ action: 'create-room' })
            }).then(r => r.json());

            if (!res.success) { showError(res.error); return; }

            await joinRoom(res.code, name);
        }

        async function joinRoom(code, name) {
            code = code || document.getElementById('roomCode').value.trim().toUpperCase();
            name = name || document.getElementById('playerName').value.trim();

            if (!name) { showError('Please enter your name'); return; }
            if (!code) { showError('Please enter a room code'); return; }

            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: buildBody({ action: 'join-room', code: code, playerName: name })
            }).then(r => r.json());

            if (!res.success) { showError(res.error); return; }

            state.roomCode = res.roomCode;
            state.version = res.version;
            sessionStorage.setItem('spies_roomCode', res.roomCode);
            showWaitingRoom(res.players);
            startPolling();
        }

        function showWaitingRoom(players) {
            document.getElementById('lobby').classList.add('hidden');
            document.getElementById('gameBoard').classList.add('hidden');
            document.getElementById('waitingRoom').classList.remove('hidden');
            document.getElementById('displayRoomCode').textContent = state.roomCode;
            updatePlayersList(players);
        }

        function updatePlayersList(players) {
            const list = document.getElementById('playersList');
            const count = document.getElementById('playerCount');
            const btn = document.getElementById('startGameBtn');

            count.textContent = players.length;
            list.innerHTML = players.map((p, i) =>
                `<li>${p.name}${i === 0 ? ' (Host)' : ''}${p.id === state.playerId ? ' (You)' : ''}</li>`
            ).join('');

            const isHost = players[0]?.id === state.playerId;
            btn.disabled = players.length < 2;
            btn.textContent = players.length < 2 ? 'Need at least 2 players' : (isHost ? 'Start Game' : 'Waiting for host...');
            btn.onclick = isHost ? startGame : null;
        }

        async function startGame() {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: buildBody({ action: 'start-game' })
            }).then(r => r.json());

            if (!res.success) { showError(res.error); return; }

            state.version = res.version;
            state.gameState = res.gameState;
            console.log(state.gameState);
            showGame();
        }

        function showGame() {
            document.getElementById('lobby').classList.add('hidden');
            document.getElementById('waitingRoom').classList.add('hidden');
            document.getElementById('gameBoard').classList.remove('hidden');
            renderGame();
        }

        function renderGame() {
            const gs = state.gameState;
            console.log("Game State: ", state.gameState);

            if (!gs) return;

            const currentPlayer = gs.players[gs.currentPlayer];
            const myPlayer = gs.players.find(p => p.id === state.playerId);
            const isMyTurn = currentPlayer.id === state.playerId;

            document.getElementById('currentPlayerName').textContent = currentPlayer.name + "'s Turn";
            document.getElementById('currentPhase').textContent = gs.phase.toUpperCase() + ' Phase';
            document.getElementById('actionsCount').textContent = gs.turnState.actions;
            document.getElementById('buysCount').textContent = gs.turnState.buys;
            document.getElementById('coinsCount').textContent = gs.turnState.coins;

            // Supply
            renderSupply('treasureSupply', ['Copper', 'Silver', 'Gold'], gs, isMyTurn);
            renderSupply('victorySupply', ['Estate', 'Duchy', 'Province'], gs, isMyTurn);
            renderSupply('actionSupply', ['Village', 'Smithy', 'Market', 'Spy'], gs, isMyTurn);

            // Players
            document.getElementById('gamePlayers').innerHTML = gs.players.map((p, i) => `
                <div class="player-info ${i === gs.currentPlayer ? 'current' : ''}">
                    <div class="player-name">${p.name}${p.id === state.playerId ? ' (You)' : ''}</div>
                    <div class="player-stats">Score: ${p.score} | Hand: ${p.handCount} | Deck: ${p.deckCount} | Discard: ${p.discardCount}</div>
                </div>
            `).join('');

            // Log
            document.getElementById('gameLog').innerHTML = gs.log.slice().reverse()
                .map(e => `<div class="log-entry">${e}</div>`).join('');

            // In Play
            document.getElementById('inPlayCards').innerHTML = (myPlayer.inPlay || [])
                .map(c => renderCard(c, 'in-play-card', false)).join('');

            // Hand
            document.getElementById('handCards').innerHTML = (myPlayer.hand || [])
                .map((c, i) => {
                    const def = state.cards[c.name];
                    const canPlay = isMyTurn && gs.phase === 'action' && !gs.spyPending &&
                        def?.type === 'action' && gs.turnState.actions > 0;
                    return renderCard(c, canPlay ? '' : 'disabled', canPlay, i);
                }).join('');

            // Action buttons
            const btns = document.getElementById('actionButtons');
            if (isMyTurn && !gs.spyPending) {
                if (gs.phase === 'action') {
                    btns.innerHTML = '<button onclick="endPhase()">End Action Phase</button>';
                } else if (gs.phase === 'buy') {
                    btns.innerHTML = '<button onclick="endPhase()">End Turn</button>';
                } else {
                    btns.innerHTML = '';
                }
            } else {
                btns.innerHTML = '';
            }

            // Spy modal
            if (gs.spyPending && isMyTurn) {
                const target = gs.players.find(p => p.id === gs.spyPending.targetPlayerId);
                document.getElementById('spyTargetText').textContent = `Spy reveals ${target?.name}'s top card:`;
                document.getElementById('spyRevealedCard').textContent = gs.spyPending.revealedCard.name;
                document.getElementById('spyModal').classList.remove('hidden');
            } else {
                document.getElementById('spyModal').classList.add('hidden');
            }

            // Game over
            if (gs.phase === 'ended') {
                document.getElementById('winnerText').textContent = 'Winner: ' + gs.winner;
                let scoreText = '<br/>';
                for(s of gs.scores) {
                    scoreText += s.name+': '+s.score+' VP<br/>';
                }
                scoreText += '<br/>';
                console.log(scoreText);
                document.getElementById('scoresText').innerHTML = scoreText;
                document.getElementById('gameOverModal').classList.remove('hidden');

                console.log("Score: ", gs.scores);
                
            } else {
                document.getElementById('gameOverModal').classList.add('hidden');
            }
        }

        function renderSupply(containerId, cardNames, gs, isMyTurn) {
            document.getElementById(containerId).innerHTML = cardNames.map(name => {
                const def = state.cards[name];
                if (!def) return '';
                const count = gs.supply[name];
                const canBuy = isMyTurn && gs.phase === 'buy' && gs.turnState.buys > 0 &&
                    count > 0 && gs.turnState.coins >= def.cost;
                return `
                    <div class="supply-card ${def.type} ${canBuy ? '' : 'disabled'}"
                         onclick="${canBuy ? `buyCard('${name}')` : ''}"
                         title="${name} (${def.cost} coins)\n${def.description}">
                        <div class="card-name">${name}</div>
                        <div class="card-cost">${def.cost} coins</div>
                        <div class="card-count">${count} left</div>
                    </div>
                `;
            }).join('');
        }

        function renderCard(card, extraClass, clickable, index) {
            const def = state.cards[card.name] || {};
            return `
                <div class="card ${def.type || ''} ${extraClass}"
                     ${clickable ? `onclick="playCard(${index})"` : ''}
                     title="${card.name} (${def.cost || 0} coins)\n${def.description || ''}">
                    <div class="card-name">${card.name}</div>
                    <div class="card-desc">${def.description || ''}</div>
                </div>
            `;
        }

        async function playCard(index) {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: buildBody({ action: 'play-card', cardIndex: index })
            }).then(r => r.json());

            if (!res.success) { showError(res.error); return; }
            state.version = res.version;
            state.gameState = res.gameState;
            renderGame();
        }

        async function buyCard(name) {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: buildBody({ action: 'buy-card', cardName: name })
            }).then(r => r.json());

            if (!res.success) { showError(res.error); return; }
            state.version = res.version;
            state.gameState = res.gameState;
            renderGame();
        }

        async function endPhase() {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: buildBody({ action: 'end-phase' })
            }).then(r => r.json());

            if (!res.success) { showError(res.error); return; }
            state.version = res.version;
            state.gameState = res.gameState;
            renderGame();
        }

        async function spyChoice(discard) {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: buildBody({ action: 'spy-choice', discard: discard })
            }).then(r => r.json());

            if (!res.success) { showError(res.error); return; }
            state.version = res.version;
            state.gameState = res.gameState;
            renderGame();
        }

        async function newGame() {
            document.getElementById('lobby').classList.remove('hidden');
            document.getElementById('waitingRoom').classList.add('hidden');
            document.getElementById('gameBoard').classList.add('hidden');
            document.getElementById('gameOverModal').classList.add('hidden');

            sessionStorage.removeItem('spies_roomCode');
            state.roomCode = null;
            document.getElementById('roomCode').value = '';
        }

        function startPolling() {
            if (pollInterval) clearInterval(pollInterval);
            pollInterval = setInterval(poll, 1500);
        }

        async function poll() {
            if (!state.roomCode) return;

            try {
                const res = await fetch(`api.php?action=poll&version=${state.version}&playerId=${state.playerId}&roomCode=${state.roomCode}`).then(r => r.json());

                if (res.noRoom) {
                    clearInterval(pollInterval);
                    sessionStorage.removeItem('spies_roomCode');
                    state.roomCode = null;
                    document.getElementById('waitingRoom').classList.add('hidden');
                    document.getElementById('gameBoard').classList.add('hidden');
                    document.getElementById('lobby').classList.remove('hidden');
                    return;
                }

                if (res.changed) {
                    state.version = res.version;
                    if (res.gameState) {
                        state.gameState = res.gameState;
                        if (!document.getElementById('gameBoard').classList.contains('hidden')) {
                            renderGame();
                        } else {
                            showGame();
                        }
                    } else if (res.players) {
                        updatePlayersList(res.players);
                    }
                }
            } catch (e) {
                console.error('Poll error:', e);
            }
        }
    </script>
</body>
</html>
