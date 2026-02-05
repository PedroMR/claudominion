import { useSocket } from './useSocket';
import { Lobby } from './Lobby';
import { WaitingRoom } from './WaitingRoom';
import { GameBoard } from './GameBoard';

function App() {
  const {
    connected,
    roomState,
    gameState,
    error,
    createRoom,
    joinRoom,
    startGame,
    playCard,
    buyCard,
    endPhase,
    spyChoice,
  } = useSocket();

  if (!connected) {
    return (
      <div className="app">
        <div className="lobby">
          <h1>Connecting...</h1>
        </div>
      </div>
    );
  }

  return (
    <div className="app">
      {error && <div className="error-message">{error}</div>}

      {!roomState && (
        <Lobby
          onCreateRoom={createRoom}
          onJoinRoom={(code, name) => joinRoom({ code, playerName: name })}
        />
      )}

      {roomState && !gameState && (
        <WaitingRoom
          roomState={roomState}
          onStartGame={startGame}
        />
      )}

      {roomState && gameState && (
        <GameBoard
          gameState={gameState}
          playerId={roomState.playerId}
          onPlayCard={(cardIndex) => playCard({ cardIndex })}
          onBuyCard={(cardName) => buyCard({ cardName })}
          onEndPhase={endPhase}
          onSpyChoice={(discard) => spyChoice({ discard })}
        />
      )}
    </div>
  );
}

export default App;
