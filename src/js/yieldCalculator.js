// SPDX-FileCopyrightText: 2026 Nextcloud cookbook contributors
//
// SPDX-License-Identifier: AGPL-3.0-only OR AGPL-3.0-or-later

/*
    The ingredientAmountRegExp is used both to validate an ingredient and to recalculate it:
    an amount, an optional unit, a whitespace and further text.
*/
// ½, ¾, ⅛, …
const unicodeFractionPattern = String.raw`[\u00BC-\u00BE\u2150-\u215E]`;
// "1 " in "1 ½" or "1 1/2", "1" in "1½"
const wholeNumberPattern = String.raw`(?<wholeNumber>\d+)(?:\s+|(?=${unicodeFractionPattern}))`;
// 1/2, 1 / 2
const asciiFractionPattern = String.raw`(?<numerator>\d+)\s*/\s*(?<denominator>\d+)`;
// ½, 1 ½, 1½, 1/2, 1 1/2
const fractionPattern = String.raw`(?:${wholeNumberPattern})?(?:(?<unicodeFraction>${unicodeFractionPattern})|${asciiFractionPattern})`;
// 200, 1.5
const decimalPattern = String.raw`(?<decimal>\d+(?:\.\d+)?)`;
// rejects "1 ¹⁄₂" or "¼ ²"
const notFollowedByUnicodeNumberPattern = String.raw`(?!\s*\p{No})`;
// "g" in "200g"
const unitPattern = String.raw`[a-zA-z]*`;

const ingredientAmountRegExp = new RegExp(
    String.raw`^(?<amount>${fractionPattern}|${decimalPattern})${notFollowedByUnicodeNumberPattern}${unitPattern}\s.*$`,
    'u',
);

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
