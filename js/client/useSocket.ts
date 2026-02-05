import { useEffect, useRef, useState, useCallback } from 'react';
import { io, Socket } from 'socket.io-client';
import {
  ClientGameState,
  JoinRoomPayload,
  PlayCardPayload,
  BuyCardPayload,
  SpyChoicePayload,
} from '../shared/types.js';

const SERVER_URL = 'http://localhost:3001';

export interface RoomState {
  code: string;
  players: { id: string; name: string }[];
  playerId: string;
}

export function useSocket() {
  const socketRef = useRef<Socket | null>(null);
  const [connected, setConnected] = useState(false);
  const [roomState, setRoomState] = useState<RoomState | null>(null);
  const [gameState, setGameState] = useState<ClientGameState | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const socket = io(SERVER_URL);
    socketRef.current = socket;

    socket.on('connect', () => {
      setConnected(true);
    });

    socket.on('disconnect', () => {
      setConnected(false);
    });

    socket.on('room-joined', (data: { roomCode: string; players: { id: string; name: string }[]; playerId: string }) => {
      setRoomState({
        code: data.roomCode,
        players: data.players,
        playerId: data.playerId,
      });
    });

    socket.on('player-joined', (data: { player: { id: string; name: string } }) => {
      setRoomState((prev) => {
        if (!prev) return prev;
        return {
          ...prev,
          players: [...prev.players, data.player],
        };
      });
    });

    socket.on('player-left', (data: { playerId: string }) => {
      setRoomState((prev) => {
        if (!prev) return prev;
        return {
          ...prev,
          players: prev.players.filter((p) => p.id !== data.playerId),
        };
      });
    });

    socket.on('game-started', (data: { gameState: ClientGameState }) => {
      setGameState(data.gameState);
    });

    socket.on('game-update', (data: { gameState: ClientGameState }) => {
      setGameState(data.gameState);
    });

    socket.on('error', (data: { message: string }) => {
      setError(data.message);
      setTimeout(() => setError(null), 3000);
    });

    return () => {
      socket.disconnect();
    };
  }, []);

  const createRoom = useCallback((): Promise<string> => {
    return new Promise((resolve, reject) => {
      if (!socketRef.current) {
        reject(new Error('Not connected'));
        return;
      }
      socketRef.current.emit('create-room', (response: { code: string }) => {
        resolve(response.code);
      });
    });
  }, []);

  const joinRoom = useCallback((payload: JoinRoomPayload): Promise<void> => {
    return new Promise((resolve, reject) => {
      if (!socketRef.current) {
        reject(new Error('Not connected'));
        return;
      }
      socketRef.current.emit('join-room', payload, (response: { success: boolean; error?: string }) => {
        if (response.success) {
          resolve();
        } else {
          reject(new Error(response.error || 'Failed to join room'));
        }
      });
    });
  }, []);

  const startGame = useCallback(() => {
    socketRef.current?.emit('start-game');
  }, []);

  const playCard = useCallback((payload: PlayCardPayload) => {
    socketRef.current?.emit('play-card', payload);
  }, []);

  const buyCard = useCallback((payload: BuyCardPayload) => {
    socketRef.current?.emit('buy-card', payload);
  }, []);

  const endPhase = useCallback(() => {
    socketRef.current?.emit('end-phase');
  }, []);

  const spyChoice = useCallback((payload: SpyChoicePayload) => {
    socketRef.current?.emit('spy-choice', payload);
  }, []);

  return {
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
  };
}
