export const exchangeReferenceAmounts = (exchange, sourceCurrency, targetCurrency) => {
    const legs = [exchange?.expense, exchange?.income].filter(Boolean);
    const source = Number(legs.find((leg) => leg.currency === sourceCurrency)?.amount);
    const target = Number(legs.find((leg) => leg.currency === targetCurrency)?.amount);

    return source > 0 && target > 0 ? { source, target } : null;
};

export const suggestedExchangeAmount = (amount, fromAmount, toAmount) => {
    const entered = Number(amount);
    if (!(entered > 0) || !(fromAmount > 0) || !(toAmount > 0)) {
        return null;
    }

    return (Math.round(entered * toAmount / fromAmount * 100) / 100).toFixed(2);
};
