import { Room } from '../shared/types.js';

const rooms = new Map<string, Room>();

function generateRoomCode(): string {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  let code = '';
  for (let i = 0; i < 6; i++) {
    code += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  return code;
}

export function createRoom(): Room {
  let code: string;
  do {
    code = generateRoomCode();
  } while (rooms.has(code));

  const room: Room = {
    code,
    players: [],
    gameStarted: false,
  };

  rooms.set(code, room);
  return room;
}

export function getRoom(code: string): Room | undefined {
  return rooms.get(code.toUpperCase());
}

export function addPlayerToRoom(code: string, playerId: string, playerName: string): Room | undefined {
  const room = rooms.get(code.toUpperCase());
  if (!room) return undefined;
  if (room.gameStarted) return undefined;
  if (room.players.length >= 4) return undefined;

  room.players.push({ id: playerId, name: playerName });
  return room;
}

export function removePlayerFromRoom(code: string, playerId: string): Room | undefined {
  const room = rooms.get(code.toUpperCase());
  if (!room) return undefined;

  room.players = room.players.filter((p) => p.id !== playerId);

  if (room.players.length === 0 && !room.gameStarted) {
    rooms.delete(code);
    return undefined;
  }

  return room;
}

export function setGameStarted(code: string): void {
  const room = rooms.get(code.toUpperCase());
  if (room) {
    room.gameStarted = true;
  }
}
