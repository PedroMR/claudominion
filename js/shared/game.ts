import { Card, GameState, Player, TurnState, ClientGameState, ClientPlayer } from './types.js';
import { getCardDefinition } from './cards.js';

let cardIdCounter = 0;

export function createCard(name: string): Card {
  return {
    id: `${name}-${++cardIdCounter}`,
    name,
  };
}

export function shuffle<T>(array: T[]): T[] {
  const result = [...array];
  for (let i = result.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [result[i], result[j]] = [result[j], result[i]];
  }
  return result;
}

export function createInitialDeck(): Card[] {
  const deck: Card[] = [];
  for (let i = 0; i < 7; i++) {
    deck.push(createCard('Copper'));
  }
  for (let i = 0; i < 3; i++) {
    deck.push(createCard('Estate'));
  }
  return shuffle(deck);
}

export function createPlayer(id: string, name: string): Player {
  const deck = createInitialDeck();
  return {
    id,
    name,
    deck: deck.slice(5),
    hand: deck.slice(0, 5),
    discard: [],
    inPlay: [],
    connected: true,
  };
}

export function createInitialSupply(playerCount: number): Record<string, number> {
  const victoryCards = playerCount === 2 ? 8 : 12;
  return {
    Copper: 60 - (playerCount * 7),
    Silver: 40,
    Gold: 30,
    Estate: victoryCards,
    Duchy: victoryCards,
    Province: victoryCards,
    Village: 10,
    Smithy: 10,
    Market: 10,
    Spy: 10,
  };
}

export function createInitialTurnState(): TurnState {
  return {
    actions: 1,
    buys: 1,
    coins: 0,
  };
}

function hasPlayableActions(player: Player, turnState: TurnState): boolean {
  if (turnState.actions <= 0) return false;
  return player.hand.some((card) => {
    const def = getCardDefinition(card.name);
    return def?.type === 'action';
  });
}

function maybeSkipActionPhase(state: GameState): GameState {
  if (state.phase !== 'action') return state;
  if (state.spyPending) return state;

  const player = state.players[state.currentPlayer];
  if (!hasPlayableActions(player, state.turnState)) {
    return playAllTreasures({ ...state, phase: 'buy' }, state.currentPlayer);
  }
  return state;
}

export function createGameState(id: string, players: { id: string; name: string }[]): GameState {
  const state: GameState = {
    id,
    phase: 'action',
    currentPlayer: 0,
    players: players.map((p) => createPlayer(p.id, p.name)),
    supply: createInitialSupply(players.length),
    trash: [],
    turnState: createInitialTurnState(),
    log: [`Game started with ${players.length} players`],
  };
  return maybeSkipActionPhase(state);
}

export function drawCards(player: Player, count: number): Card[] {
  const drawn: Card[] = [];
  for (let i = 0; i < count; i++) {
    if (player.deck.length === 0) {
      if (player.discard.length === 0) {
        break;
      }
      player.deck = shuffle(player.discard);
      player.discard = [];
    }
    const card = player.deck.pop();
    if (card) {
      drawn.push(card);
      player.hand.push(card);
    }
  }
  return drawn;
}

export function canPlayCard(state: GameState, playerIndex: number, cardIndex: number): boolean {
  if (state.phase !== 'action') return false;
  if (state.currentPlayer !== playerIndex) return false;
  if (state.spyPending) return false;

  const player = state.players[playerIndex];
  if (cardIndex < 0 || cardIndex >= player.hand.length) return false;

  const card = player.hand[cardIndex];
  const def = getCardDefinition(card.name);
  if (!def) return false;
  if (def.type !== 'action') return false;
  if (state.turnState.actions <= 0) return false;

  return true;
}

export function playCard(state: GameState, playerIndex: number, cardIndex: number): GameState {
  if (!canPlayCard(state, playerIndex, cardIndex)) {
    return state;
  }

  const newState = JSON.parse(JSON.stringify(state)) as GameState;
  const player = newState.players[playerIndex];
  const card = player.hand.splice(cardIndex, 1)[0];
  player.inPlay.push(card);

  const def = getCardDefinition(card.name)!;

  newState.turnState.actions -= 1;

  if (def.effect.actions) {
    newState.turnState.actions += def.effect.actions;
  }
  if (def.effect.buys) {
    newState.turnState.buys += def.effect.buys;
  }
  if (def.effect.coins) {
    newState.turnState.coins += def.effect.coins;
  }
  if (def.effect.cards) {
    drawCards(player, def.effect.cards);
  }

  newState.log.push(`${player.name} played ${card.name}`);

  if (def.effect.special === 'spy') {
    for (let i = 0; i < newState.players.length; i++) {
      const targetIndex = (playerIndex + i) % newState.players.length;
      const target = newState.players[targetIndex];

      if (target.deck.length === 0 && target.discard.length > 0) {
        target.deck = shuffle(target.discard);
        target.discard = [];
      }

      if (target.deck.length > 0) {
        const revealedCard = target.deck[target.deck.length - 1];
        newState.spyPending = {
          targetPlayerId: target.id,
          revealedCard,
        };
        newState.log.push(`${target.name} reveals ${revealedCard.name}`);
        break;
      }
    }
  }

  return maybeSkipActionPhase(newState);
}

export function handleSpyChoice(state: GameState, discard: boolean): GameState {
  if (!state.spyPending) return state;

  const newState = JSON.parse(JSON.stringify(state)) as GameState;
  const target = newState.players.find((p) => p.id === newState.spyPending!.targetPlayerId)!;
  const currentPlayer = newState.players[newState.currentPlayer];

  if (discard) {
    const card = target.deck.pop()!;
    target.discard.push(card);
    newState.log.push(`${currentPlayer.name} chose to discard ${target.name}'s ${card.name}`);
  } else {
    newState.log.push(`${currentPlayer.name} chose to keep ${target.name}'s card on top`);
  }

  const currentTargetIndex = newState.players.findIndex((p) => p.id === state.spyPending!.targetPlayerId);
  delete newState.spyPending;

  for (let i = currentTargetIndex + 1; i < newState.players.length; i++) {
    const nextTarget = newState.players[i];

    if (nextTarget.deck.length === 0 && nextTarget.discard.length > 0) {
      nextTarget.deck = shuffle(nextTarget.discard);
      nextTarget.discard = [];
    }

    if (nextTarget.deck.length > 0) {
      const revealedCard = nextTarget.deck[nextTarget.deck.length - 1];
      newState.spyPending = {
        targetPlayerId: nextTarget.id,
        revealedCard,
      };
      newState.log.push(`${nextTarget.name} reveals ${revealedCard.name}`);
      break;
    }
  }

  return maybeSkipActionPhase(newState);
}

export function playAllTreasures(state: GameState, playerIndex: number): GameState {
  if (state.currentPlayer !== playerIndex) return state;
  if (state.phase !== 'action' && state.phase !== 'buy') return state;

  const newState = JSON.parse(JSON.stringify(state)) as GameState;
  const player = newState.players[playerIndex];

  const treasureIndices: number[] = [];
  player.hand.forEach((card, index) => {
    const def = getCardDefinition(card.name);
    if (def?.type === 'treasure') {
      treasureIndices.push(index);
    }
  });

  treasureIndices.reverse().forEach((index) => {
    const card = player.hand.splice(index, 1)[0];
    player.inPlay.push(card);
    const def = getCardDefinition(card.name)!;
    if (def.effect.coins) {
      newState.turnState.coins += def.effect.coins;
    }
  });

  if (treasureIndices.length > 0) {
    newState.log.push(`${player.name} played ${treasureIndices.length} treasure(s)`);
  }

  return newState;
}

export function canBuyCard(state: GameState, playerIndex: number, cardName: string): boolean {
  if (state.phase !== 'buy') return false;
  if (state.currentPlayer !== playerIndex) return false;
  if (state.turnState.buys <= 0) return false;

  const def = getCardDefinition(cardName);
  if (!def) return false;

  if (state.supply[cardName] === undefined || state.supply[cardName] <= 0) return false;
  if (state.turnState.coins < def.cost) return false;

  return true;
}

export function buyCard(state: GameState, playerIndex: number, cardName: string): GameState {
  if (!canBuyCard(state, playerIndex, cardName)) {
    return state;
  }

  const newState = JSON.parse(JSON.stringify(state)) as GameState;
  const player = newState.players[playerIndex];
  const def = getCardDefinition(cardName)!;

  newState.supply[cardName]--;
  player.discard.push(createCard(cardName));
  newState.turnState.coins -= def.cost;
  newState.turnState.buys--;

  newState.log.push(`${player.name} bought ${cardName}`);

  return newState;
}

export function endActionPhase(state: GameState, playerIndex: number): GameState {
  if (state.phase !== 'action') return state;
  if (state.currentPlayer !== playerIndex) return state;
  if (state.spyPending) return state;

  const newState = JSON.parse(JSON.stringify(state)) as GameState;
  newState.phase = 'buy';

  return playAllTreasures(newState, playerIndex);
}

export function endBuyPhase(state: GameState, playerIndex: number): GameState {
  if (state.phase !== 'buy') return state;
  if (state.currentPlayer !== playerIndex) return state;

  return cleanup(state, playerIndex);
}

function cleanup(state: GameState, playerIndex: number): GameState {
  const newState = JSON.parse(JSON.stringify(state)) as GameState;
  const player = newState.players[playerIndex];

  player.discard.push(...player.hand, ...player.inPlay);
  player.hand = [];
  player.inPlay = [];

  drawCards(player, 5);

  if (isGameOver(newState)) {
    newState.phase = 'ended';
    const scores = calculateScores(newState);
    const maxScore = Math.max(...scores.map((s) => s.score));
    const winners = scores.filter((s) => s.score === maxScore);
    newState.winner = winners.map((w) => w.name).join(', ');
    newState.log.push(`Game over! Winner: ${newState.winner}`);
    scores.forEach((s) => newState.log.push(`${s.name}: ${s.score} VP`));
    return newState;
  }

  newState.currentPlayer = (playerIndex + 1) % newState.players.length;
  newState.phase = 'action';
  newState.turnState = createInitialTurnState();

  newState.log.push(`${newState.players[newState.currentPlayer].name}'s turn`);

  return maybeSkipActionPhase(newState);
}

export function isGameOver(state: GameState): boolean {
  if (state.supply['Province'] === 0) return true;
  const emptyPiles = Object.values(state.supply).filter((count) => count === 0).length;
  return emptyPiles >= 3;
}

export function calculateScores(state: GameState): { name: string; score: number }[] {
  return state.players.map((player) => {
    const allCards = [...player.deck, ...player.hand, ...player.discard, ...player.inPlay];
    const score = allCards.reduce((total, card) => {
      const def = getCardDefinition(card.name);
      return total + (def?.effect.vp || 0);
    }, 0);
    return { name: player.name, score };
  });
}

export function toClientGameState(state: GameState, forPlayerId: string): ClientGameState {
  return {
    id: state.id,
    phase: state.phase,
    currentPlayer: state.currentPlayer,
    players: state.players.map((player): ClientPlayer => ({
      id: player.id,
      name: player.name,
      handCount: player.hand.length,
      deckCount: player.deck.length,
      discardCount: player.discard.length,
      inPlay: player.inPlay,
      connected: player.connected,
      hand: player.id === forPlayerId ? player.hand : undefined,
    })),
    supply: state.supply,
    trashCount: state.trash.length,
    turnState: state.turnState,
    spyPending: state.spyPending,
    winner: state.winner,
    log: state.log.slice(-20),
  };
}
