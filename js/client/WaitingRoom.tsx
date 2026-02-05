import { RoomState } from './useSocket';

interface WaitingRoomProps {
  roomState: RoomState;
  onStartGame: () => void;
}

export function WaitingRoom({ roomState, onStartGame }: WaitingRoomProps) {
  const isHost = roomState.players[0]?.id === roomState.playerId;
  const canStart = roomState.players.length >= 2;

  return (
    <div className="waiting-room">
      <h2>Waiting for Players</h2>
      <div className="room-code">{roomState.code}</div>
      <p>Share this code with your friends!</p>

      <div className="players-list">
        <h3>Players ({roomState.players.length}/4)</h3>
        <ul>
          {roomState.players.map((player, index) => (
            <li key={player.id}>
              {player.name}
              {index === 0 && ' (Host)'}
              {player.id === roomState.playerId && ' (You)'}
            </li>
          ))}
        </ul>
      </div>

      {isHost ? (
        <button onClick={onStartGame} disabled={!canStart}>
          {canStart ? 'Start Game' : 'Need at least 2 players'}
        </button>
      ) : (
        <p>Waiting for host to start the game...</p>
      )}
    </div>
  );
}
