import { useState } from 'react';

interface LobbyProps {
  onCreateRoom: () => Promise<string>;
  onJoinRoom: (code: string, playerName: string) => Promise<void>;
}

export function Lobby({ onCreateRoom, onJoinRoom }: LobbyProps) {
  const [playerName, setPlayerName] = useState('');
  const [roomCode, setRoomCode] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleCreate = async () => {
    if (!playerName.trim()) {
      setError('Please enter your name');
      return;
    }

    setIsLoading(true);
    setError(null);

    try {
      const code = await onCreateRoom();
      await onJoinRoom(code, playerName.trim());
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to create room');
    } finally {
      setIsLoading(false);
    }
  };

  const handleJoin = async () => {
    if (!playerName.trim()) {
      setError('Please enter your name');
      return;
    }
    if (!roomCode.trim()) {
      setError('Please enter a room code');
      return;
    }

    setIsLoading(true);
    setError(null);

    try {
      await onJoinRoom(roomCode.trim().toUpperCase(), playerName.trim());
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to join room');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="lobby">
      <h1>Spies!</h1>
      <div className="lobby-actions">
        <input
          type="text"
          placeholder="Enter your name"
          value={playerName}
          onChange={(e) => setPlayerName(e.target.value)}
          disabled={isLoading}
        />

        <button onClick={handleCreate} disabled={isLoading || !playerName.trim()}>
          Create New Game
        </button>

        <div className="divider">- or -</div>

        <div className="join-section">
          <input
            type="text"
            placeholder="Room code"
            value={roomCode}
            onChange={(e) => setRoomCode(e.target.value.toUpperCase())}
            maxLength={6}
            disabled={isLoading}
          />
          <button onClick={handleJoin} disabled={isLoading || !playerName.trim() || !roomCode.trim()}>
            Join
          </button>
        </div>

        {error && <div className="error-message">{error}</div>}
      </div>
    </div>
  );
}
