import { Server, Socket } from 'socket.io';
import {
  JoinRoomPayload,
  PlayCardPayload,
  BuyCardPayload,
  SpyChoicePayload,
} from '../shared/types.js';
import {
  createRoom,
  getRoom,
  addPlayerToRoom,
  removePlayerFromRoom,
  setGameStarted,
} from './rooms.js';
import {
  createGame,
  getGame,
  getClientGameState,
  handlePlayCard,
  handleBuyCard,
  handleEndPhase,
  handleSpyDecision,
  setPlayerConnected,
} from './game.js';

interface SocketData {
  roomCode?: string;
  playerId?: string;
  playerName?: string;
}

export function setupSocketHandlers(io: Server): void {
  io.on('connection', (socket: Socket) => {
    const socketData: SocketData = {};

    console.log(`Client connected: ${socket.id}`);

    socket.on('create-room', (callback: (response: { code: string }) => void) => {
      const room = createRoom();
      console.log(`Room created: ${room.code}`);
      callback({ code: room.code });
    });

    socket.on('join-room', (payload: JoinRoomPayload, callback: (response: { success: boolean; error?: string }) => void) => {
      const { code, playerName } = payload;
      const room = addPlayerToRoom(code, socket.id, playerName);

      if (!room) {
        callback({ success: false, error: 'Room not found or full or game already started' });
        return;
      }

      socketData.roomCode = room.code;
      socketData.playerId = socket.id;
      socketData.playerName = playerName;

      socket.join(room.code);

      socket.emit('room-joined', {
        roomCode: room.code,
        players: room.players,
        playerId: socket.id,
      });

      socket.to(room.code).emit('player-joined', {
        player: { id: socket.id, name: playerName },
      });

      callback({ success: true });
    });

    socket.on('start-game', () => {
      if (!socketData.roomCode) {
        socket.emit('error', { message: 'Not in a room' });
        return;
      }

      const room = getRoom(socketData.roomCode);
      if (!room) {
        socket.emit('error', { message: 'Room not found' });
        return;
      }

      if (room.players.length < 2) {
        socket.emit('error', { message: 'Need at least 2 players' });
        return;
      }

      if (room.gameStarted) {
        socket.emit('error', { message: 'Game already started' });
        return;
      }

      setGameStarted(socketData.roomCode);
      createGame(socketData.roomCode, room.players);

      room.players.forEach((player) => {
        const clientState = getClientGameState(socketData.roomCode!, player.id);
        io.to(player.id).emit('game-started', { gameState: clientState });
      });
    });

    socket.on('play-card', (payload: PlayCardPayload) => {
      if (!socketData.roomCode || !socketData.playerId) {
        socket.emit('error', { message: 'Not in a game' });
        return;
      }

      const newState = handlePlayCard(socketData.roomCode, socketData.playerId, payload.cardIndex);
      if (!newState) {
        socket.emit('error', { message: 'Invalid action' });
        return;
      }

      broadcastGameUpdate(io, socketData.roomCode, newState);
    });

    socket.on('buy-card', (payload: BuyCardPayload) => {
      if (!socketData.roomCode || !socketData.playerId) {
        socket.emit('error', { message: 'Not in a game' });
        return;
      }

      const newState = handleBuyCard(socketData.roomCode, socketData.playerId, payload.cardName);
      if (!newState) {
        socket.emit('error', { message: 'Invalid action' });
        return;
      }

      broadcastGameUpdate(io, socketData.roomCode, newState);
    });

    socket.on('end-phase', () => {
      if (!socketData.roomCode || !socketData.playerId) {
        socket.emit('error', { message: 'Not in a game' });
        return;
      }

      const newState = handleEndPhase(socketData.roomCode, socketData.playerId);
      if (!newState) {
        socket.emit('error', { message: 'Invalid action' });
        return;
      }

      broadcastGameUpdate(io, socketData.roomCode, newState);
    });

    socket.on('spy-choice', (payload: SpyChoicePayload) => {
      if (!socketData.roomCode || !socketData.playerId) {
        socket.emit('error', { message: 'Not in a game' });
        return;
      }

      const newState = handleSpyDecision(socketData.roomCode, socketData.playerId, payload.discard);
      if (!newState) {
        socket.emit('error', { message: 'Invalid action' });
        return;
      }

      broadcastGameUpdate(io, socketData.roomCode, newState);
    });

    socket.on('disconnect', () => {
      console.log(`Client disconnected: ${socket.id}`);

      if (socketData.roomCode) {
        const game = getGame(socketData.roomCode);
        if (game) {
          setPlayerConnected(socketData.roomCode, socket.id, false);
          broadcastGameUpdate(io, socketData.roomCode, game);
        } else {
          const room = removePlayerFromRoom(socketData.roomCode, socket.id);
          if (room) {
            io.to(socketData.roomCode).emit('player-left', { playerId: socket.id });
          }
        }
      }
    });
  });
}

function broadcastGameUpdate(io: Server, roomCode: string, gameState: ReturnType<typeof getGame>): void {
  if (!gameState) return;

  gameState.players.forEach((player) => {
    const clientState = getClientGameState(roomCode, player.id);
    io.to(player.id).emit('game-update', { gameState: clientState });
  });
}
