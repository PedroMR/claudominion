import { ClientGameState, Card } from '../shared/types.js';
import { getCardDefinition, CARD_DEFINITIONS } from '../shared/cards.js';

interface GameBoardProps {
  gameState: ClientGameState;
  playerId: string;
  onPlayCard: (cardIndex: number) => void;
  onBuyCard: (cardName: string) => void;
  onEndPhase: () => void;
  onSpyChoice: (discard: boolean) => void;
}

export function GameBoard({
  gameState,
  playerId,
  onPlayCard,
  onBuyCard,
  onEndPhase,
  onSpyChoice,
}: GameBoardProps) {
  const currentPlayer = gameState.players[gameState.currentPlayer];
  const myPlayer = gameState.players.find((p) => p.id === playerId)!;
  const isMyTurn = currentPlayer.id === playerId;

  const treasureCards = ['Copper', 'Silver', 'Gold'];
  const victoryCards = ['Estate', 'Duchy', 'Province'];
  const actionCards = ['Village', 'Smithy', 'Market', 'Spy'];

  const canBuy = (cardName: string): boolean => {
    if (!isMyTurn || gameState.phase !== 'buy') return false;
    if (gameState.turnState.buys <= 0) return false;
    const def = getCardDefinition(cardName);
    if (!def) return false;
    if (gameState.supply[cardName] <= 0) return false;
    if (gameState.turnState.coins < def.cost) return false;
    return true;
  };

  const canPlay = (card: Card): boolean => {
    if (!isMyTurn || gameState.phase !== 'action') return false;
    if (gameState.spyPending) return false;
    const def = getCardDefinition(card.name);
    if (!def || def.type !== 'action') return false;
    if (gameState.turnState.actions <= 0) return false;
    return true;
  };

  const renderSupplyCard = (cardName: string) => {
    const def = CARD_DEFINITIONS[cardName];
    const count = gameState.supply[cardName];
    const buyable = canBuy(cardName);

    return (
      <div
        key={cardName}
        className={`supply-card ${def.type} ${!buyable ? 'disabled' : ''}`}
        onClick={() => buyable && onBuyCard(cardName)}
        title={`${cardName} (${def.cost} coins)\n${def.description}`}
      >
        <div className="card-name">{cardName}</div>
        <div className="card-cost">{def.cost} coins</div>
        <div className="card-count">{count} left</div>
      </div>
    );
  };

  const renderHandCard = (card: Card, index: number) => {
    const def = getCardDefinition(card.name);
    if (!def) return null;

    const playable = canPlay(card);

    return (
      <div
        key={card.id}
        className={`card ${def.type} ${!playable ? 'disabled' : ''}`}
        onClick={() => playable && onPlayCard(index)}
        title={`${card.name} (${def.cost} coins)\n${def.description}`}
      >
        <div className="card-name">{card.name}</div>
        <div className="card-desc">{def.description}</div>
      </div>
    );
  };

  const renderInPlayCard = (card: Card) => {
    const def = getCardDefinition(card.name);
    if (!def) return null;

    return (
      <div
        key={card.id}
        className={`card in-play-card ${def.type}`}
        title={`${card.name} (${def.cost} coins)\n${def.description}`}
      >
        <div className="card-name">{card.name}</div>
      </div>
    );
  };

  return (
    <div className="game-board">
      <div className="game-header">
        <div className="turn-info">
          <span className="current-player">{currentPlayer.name}'s Turn</span>
          <span className="phase">{gameState.phase.toUpperCase()} Phase</span>
        </div>
        <div className="turn-state">
          <span>Actions: {gameState.turnState.actions}</span>
          <span>Buys: {gameState.turnState.buys}</span>
          <span>Coins: {gameState.turnState.coins}</span>
        </div>
      </div>

      <div className="game-main">
        <div className="supply-area">
          <div className="supply-section">
            <h3>Treasure Cards</h3>
            <div className="supply-cards">
              {treasureCards.map(renderSupplyCard)}
            </div>
          </div>
          <div className="supply-section">
            <h3>Victory Cards</h3>
            <div className="supply-cards">
              {victoryCards.map(renderSupplyCard)}
            </div>
          </div>
          <div className="supply-section">
            <h3>Action Cards</h3>
            <div className="supply-cards">
              {actionCards.map(renderSupplyCard)}
            </div>
          </div>
        </div>

        <div className="sidebar">
          <div className="players-info">
            <h3>Players</h3>
            {gameState.players.map((player, index) => (
              <div
                key={player.id}
                className={`player-info ${index === gameState.currentPlayer ? 'current' : ''} ${!player.connected ? 'disconnected' : ''}`}
              >
                <div className="player-name">
                  {player.name}
                  {player.id === playerId && ' (You)'}
                </div>
                <div className="player-stats">
                  Hand: {player.handCount} | Deck: {player.deckCount} | Discard: {player.discardCount}
                </div>
              </div>
            ))}
          </div>

          <div className="game-log">
            <h3>Game Log</h3>
            {gameState.log.slice().reverse().map((entry, i) => (
              <div key={i} className="log-entry">{entry}</div>
            ))}
          </div>
        </div>
      </div>

      <div className="player-area">
        <div className="in-play">
          <h4>In Play</h4>
          {myPlayer.inPlay.map(renderInPlayCard)}
        </div>

        <div className="hand">
          <h4>Your Hand</h4>
          {myPlayer.hand?.map((card, index) => renderHandCard(card, index))}
        </div>

        {isMyTurn && !gameState.spyPending && (
          <div className="action-buttons">
            {gameState.phase === 'action' && (
              <button onClick={onEndPhase}>End Action Phase</button>
            )}
            {gameState.phase === 'buy' && (
              <button onClick={onEndPhase}>End Turn</button>
            )}
          </div>
        )}
      </div>

      {gameState.spyPending && isMyTurn && (
        <div className="spy-modal">
          <div className="spy-modal-content">
            <h3>Spy reveals {gameState.players.find(p => p.id === gameState.spyPending!.targetPlayerId)?.name}'s top card:</h3>
            <div className="revealed-card">{gameState.spyPending.revealedCard.name}</div>
            <div className="spy-buttons">
              <button className="discard-btn" onClick={() => onSpyChoice(true)}>
                Discard It
              </button>
              <button className="keep-btn" onClick={() => onSpyChoice(false)}>
                Keep on Top
              </button>
            </div>
          </div>
        </div>
      )}

      {gameState.phase === 'ended' && (
        <div className="game-over">
          <div className="game-over-content">
            <h2>Game Over!</h2>
            <div className="winner">Winner: {gameState.winner}</div>
          </div>
        </div>
      )}
    </div>
  );
}
