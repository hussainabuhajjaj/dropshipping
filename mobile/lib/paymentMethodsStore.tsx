import React, { createContext, useContext, useMemo, useReducer } from 'react';

export type PaymentCard = {
  id: string;
  brand: 'mastercard' | 'visa';
  last4: string;
  name: string;
  expiry: string;
};

type PaymentMethodsState = {
  cards: PaymentCard[];
  selectedCardId: string | null;
};

type PaymentMethodsAction =
  | { type: 'add'; card: PaymentCard }
  | { type: 'update'; id: string; patch: Partial<PaymentCard> }
  | { type: 'remove'; id: string }
  | { type: 'select'; id: string }
  | { type: 'reset' };

const defaultCards: PaymentCard[] = [
  {
    id: 'card-1',
    brand: 'mastercard',
    last4: '1579',
    name: 'AMANDA MORGAN',
    expiry: '12/22',
  },
  {
    id: 'card-2',
    brand: 'visa',
    last4: '2210',
    name: 'AMANDA MORGAN',
    expiry: '12/22',
  },
];

const PaymentMethodsContext = createContext<{
  cards: PaymentCard[];
  selectedCard: PaymentCard | null;
  selectCard: (id: string) => void;
  addCard: (input: { name: string; number: string; expiry: string }) => PaymentCard;
  updateCard: (id: string, patch: Partial<PaymentCard>) => void;
  removeCard: (id: string) => void;
  reset: () => void;
} | null>(null);

const guessBrand = (cardNumber: string): PaymentCard['brand'] => {
  const trimmed = cardNumber.replace(/\s+/g, '');
  if (trimmed.startsWith('4')) return 'visa';
  if (trimmed.startsWith('5')) return 'mastercard';
  return 'visa';
};

const reducer = (state: PaymentMethodsState, action: PaymentMethodsAction): PaymentMethodsState => {
  switch (action.type) {
    case 'add':
      return {
        cards: [action.card, ...state.cards],
        selectedCardId: action.card.id,
      };
    case 'update':
      return {
        cards: state.cards.map((card) => (card.id === action.id ? { ...card, ...action.patch } : card)),
        selectedCardId: state.selectedCardId,
      };
    case 'remove': {
      const nextCards = state.cards.filter((card) => card.id !== action.id);
      const nextSelected =
        state.selectedCardId === action.id ? nextCards[0]?.id ?? null : state.selectedCardId;
      return { cards: nextCards, selectedCardId: nextSelected };
    }
    case 'select':
      return { cards: state.cards, selectedCardId: action.id };
    case 'reset':
      return {
        cards: defaultCards,
        selectedCardId: defaultCards[0]?.id ?? null,
      };
    default:
      return state;
  }
};

export const PaymentMethodsProvider = ({ children }: { children: React.ReactNode }) => {
  const [state, dispatch] = useReducer(reducer, {
    cards: defaultCards,
    selectedCardId: defaultCards[0]?.id ?? null,
  });

  const value = useMemo(() => {
    const selectedCard = state.selectedCardId
      ? state.cards.find((card) => card.id === state.selectedCardId) ?? null
      : null;

    const selectCard = (id: string) => dispatch({ type: 'select', id });

    const addCard = (input: { name: string; number: string; expiry: string }) => {
      const digits = input.number.replace(/\D+/g, '');
      const last4 = digits.slice(-4) || '0000';
      const card: PaymentCard = {
        id: `card-${Date.now()}`,
        brand: guessBrand(digits),
        last4,
        name: input.name.trim() || 'CARD HOLDER',
        expiry: input.expiry.trim() || '12/29',
      };
      dispatch({ type: 'add', card });
      return card;
    };

    const updateCard = (id: string, patch: Partial<PaymentCard>) => dispatch({ type: 'update', id, patch });

    const removeCard = (id: string) => dispatch({ type: 'remove', id });

    const reset = () => dispatch({ type: 'reset' });

    return {
      cards: state.cards,
      selectedCard,
      selectCard,
      addCard,
      updateCard,
      removeCard,
      reset,
    };
  }, [state.cards, state.selectedCardId]);

  return <PaymentMethodsContext.Provider value={value}>{children}</PaymentMethodsContext.Provider>;
};

export const usePaymentMethods = () => {
  const ctx = useContext(PaymentMethodsContext);
  if (!ctx) throw new Error('usePaymentMethods must be used within PaymentMethodsProvider');
  return ctx;
};
