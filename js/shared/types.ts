export type CardType = 'treasure' | 'victory' | 'action';

export interface CardEffect {
  cards?: number;
  actions?: number;
  buys?: number;
  coins?: number;
  vp?: number;
  special?: 'spy';
}

export interface CardDefinition {
  name: string;
  cost: number;
  type: CardType;
  effect: CardEffect;
  description: string;
}

export interface Card {
  id: string;
  name: string;
}

export type GamePhase = 'waiting' | 'action' | 'buy' | 'cleanup' | 'ended';

export interface TurnState {
  actions: number;
  buys: number;
  coins: number;
}

export interface Player {
  id: string;
  name: string;
  deck: Card[];
  hand: Card[];
  discard: Card[];
  inPlay: Card[];
  connected: boolean;
}

export interface SpyPendingChoice {
  targetPlayerId: string;
  revealedCard: Card;
  remainingPlayerIds: string[];  // Players still to be revealed
}

export interface GameState {
  id: string;
  phase: GamePhase;
  currentPlayer: number;
  players: Player[];
  supply: Record<string, number>;
  trash: Card[];
  turnState: TurnState;
  spyPending?: SpyPendingChoice;
  winner?: string;
  log: string[];
}

export interface Room {
  code: string;
  players: { id: string; name: string }[];
  gameStarted: boolean;
}

// Socket event types
export interface JoinRoomPayload {
  code: string;
  playerName: string;
}

export interface RoomJoinedResponse {
  roomCode: string;
  players: { id: string; name: string }[];
  playerId: string;
}

export interface PlayCardPayload {
  cardIndex: number;
}

export interface BuyCardPayload {
  cardName: string;
}

export interface SpyChoicePayload {
  discard: boolean;
}

// Client-safe game state (hides other players' hands/decks)
export interface ClientGameState {
  id: string;
  phase: GamePhase;
  currentPlayer: number;
  players: ClientPlayer[];
  supply: Record<string, number>;
  trashCount: number;
  turnState: TurnState;
  spyPending?: SpyPendingChoice;
  winner?: string;
  log: string[];
}

export interface ClientPlayer {
  id: string;
  name: string;
  handCount: number;
  deckCount: number;
  discardCount: number;
  inPlay: Card[];
  connected: boolean;
  hand?: Card[];
}
