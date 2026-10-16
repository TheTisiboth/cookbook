// SPDX-FileCopyrightText: 2026 Nextcloud cookbook contributors
//
// SPDX-License-Identifier: AGPL-3.0-only OR AGPL-3.0-or-later

/*
    The ingredientAmountRegExp is used both to validate an ingredient and to recalculate it.
    The amount is a fraction (optionally with a whole part) or a decimal, must not be followed
    by another unicode number, and may have a unit. It must be followed by a whitespace and further text.
*/
const ingredientAmountRegExp =
    /^(?<amount>(?:(?<wholeNumber>\d+)(?:\s+|(?=[\u00BC-\u00BE\u2150-\u215E])))?(?:(?<unicodeFraction>[\u00BC-\u00BE\u2150-\u215E])|(?<numerator>\d+)\s*\/\s*(?<denominator>\d+))|(?<decimal>\d+(?:\.\d+)?))(?!\s*\p{No})[a-zA-z]*\s.*$/u;

/*
    The ingredientMultipleSeperatorsRegExp is used to check whether the string contains
    more than one separator (.,) after a number. This is used to exclude strings that
    contain more than one separator from being valid.
*/
const ingredientMultipleSeparatorsRegExp = /^-?\d+(?:[.,]\d+){2,}.*/;

function parseIngredientAmount(ingredient) {
    if (ingredientMultipleSeparatorsRegExp.test(ingredient)) {
        return null;
    }
    return ingredient.match(ingredientAmountRegExp)?.groups ?? null;
}

function isValidIngredientSyntax(ingredient) {
    /*
        startsWithDoubleHashRegExp checks if the ingredient string begins with "## " followed by any characters.
    */
    const startsWithDoubleHashRegExp = /^## .+$/;

    return (
        startsWithDoubleHashRegExp.test(ingredient) ||
        parseIngredientAmount(ingredient) !== null
    );
}

function isIngredientsArrayValid(ingredients) {
    return ingredients.every(isValidIngredientSyntax);
}

function recalculateIngredients(ingredients, currentYield, originalYield) {
    return ingredients.map((ingredient) => {
        if (ingredient.startsWith('## ')) {
            return ingredient;
        }

        if (!Number.isInteger(originalYield) || originalYield < 1) {
            return ingredient;
        }

        const parsedAmount = parseIngredientAmount(ingredient);
        if (!parsedAmount) {
            return ingredient;
        }

        const {
            amount,
            wholeNumber,
            unicodeFraction,
            numerator,
            denominator,
            decimal,
        } = parsedAmount;
        const remainder = ingredient.slice(amount.length);

        // Decimal
        if (decimal) {
            let newAmount =
                (parseFloat(decimal) / originalYield) * currentYield;
            newAmount = newAmount.toFixed(2).replace(/[.]00$/, '');

            return `${newAmount}${remainder}`;
        }

        // Fraction
        const [fractionNumerator, fractionDenominator] = unicodeFraction
            ? unicodeFraction
                  .normalize('NFKD')
                  .split('\u2044')
                  .map((x) => parseInt(x, 10))
            : [parseInt(numerator, 10), parseInt(denominator, 10)];
        const wholeNumberPart = wholeNumber ? parseInt(wholeNumber, 10) : 0;

        const decimalAmount =
            wholeNumberPart + fractionNumerator / fractionDenominator;
        let newAmount = (decimalAmount / originalYield) * currentYield;
        const newWholeNumberPart = parseInt(newAmount, 10);
        let newNumerator = (newAmount - newWholeNumberPart) * 16;
        if (Number.isInteger(newNumerator)) {
            const gcd = (a, b) => (b ? gcd(b, a % b) : a);
            const div = gcd(newNumerator, 16);
            newNumerator /= div;
            const newDenominator = 16 / div;
            const prefix = newWholeNumberPart ? `${newWholeNumberPart} ` : '';
            newAmount = `${prefix}${newNumerator}/${newDenominator}`;
        } else {
            newAmount = newAmount.toFixed(2).replace(/[.]00$/, '');
        }

        return `${newAmount}${remainder}`;
    });
}

export default {
    isValidIngredientSyntax,
    isIngredientsArrayValid,
    recalculateIngredients,
};
