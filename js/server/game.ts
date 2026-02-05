import {
  GameState,
  ClientGameState,
} from '../shared/types.js';
import {
  createGameState,
  playCard,
  buyCard,
  endActionPhase,
  endBuyPhase,
  handleSpyChoice,
  toClientGameState,
} from '../shared/game.js';

const games = new Map<string, GameState>();

export function createGame(roomCode: string, players: { id: string; name: string }[]): GameState {
  const gameState = createGameState(roomCode, players);
  games.set(roomCode, gameState);
  return gameState;
}

export function getGame(roomCode: string): GameState | undefined {
  return games.get(roomCode);
}

export function getClientGameState(roomCode: string, playerId: string): ClientGameState | undefined {
  const game = games.get(roomCode);
  if (!game) return undefined;
  return toClientGameState(game, playerId);
}

export function handlePlayCard(roomCode: string, playerId: string, cardIndex: number): GameState | undefined {
  const game = games.get(roomCode);
  if (!game) return undefined;

  const playerIndex = game.players.findIndex((p) => p.id === playerId);
  if (playerIndex === -1) return undefined;

  const newState = playCard(game, playerIndex, cardIndex);
  games.set(roomCode, newState);
  return newState;
}

export function handleBuyCard(roomCode: string, playerId: string, cardName: string): GameState | undefined {
  const game = games.get(roomCode);
  if (!game) return undefined;

  const playerIndex = game.players.findIndex((p) => p.id === playerId);
  if (playerIndex === -1) return undefined;

  const newState = buyCard(game, playerIndex, cardName);
  games.set(roomCode, newState);
  return newState;
}

export function handleEndPhase(roomCode: string, playerId: string): GameState | undefined {
  const game = games.get(roomCode);
  if (!game) return undefined;

  const playerIndex = game.players.findIndex((p) => p.id === playerId);
  if (playerIndex === -1) return undefined;

  let newState: GameState;
  if (game.phase === 'action') {
    newState = endActionPhase(game, playerIndex);
  } else if (game.phase === 'buy') {
    newState = endBuyPhase(game, playerIndex);
  } else {
    return undefined;
  }

  games.set(roomCode, newState);
  return newState;
}

export function handleSpyDecision(roomCode: string, playerId: string, discard: boolean): GameState | undefined {
  const game = games.get(roomCode);
  if (!game) return undefined;

  const playerIndex = game.players.findIndex((p) => p.id === playerId);
  if (playerIndex === -1) return undefined;
  if (game.currentPlayer !== playerIndex) return undefined;
  if (!game.spyPending) return undefined;

  const newState = handleSpyChoice(game, discard);
  games.set(roomCode, newState);
  return newState;
}

export function setPlayerConnected(roomCode: string, playerId: string, connected: boolean): void {
  const game = games.get(roomCode);
  if (!game) return;

  const player = game.players.find((p) => p.id === playerId);
  if (player) {
    player.connected = connected;
  }
}
