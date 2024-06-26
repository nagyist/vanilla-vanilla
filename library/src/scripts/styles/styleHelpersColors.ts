/*
 * @author Stéphane LaFlèche <stephane.l@vanillaforums.com>
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

import { color, ColorHelper } from "csx";
import { ColorsUtils } from "./ColorsUtils";

export const ensureColorHelper = (colorValue: string | ColorHelper) => {
    return typeof colorValue === "string" ? color(colorValue) : colorValue;
};

// Left in for backwards compatibility with custom themes.
export const colorOut = (...args) => {
    return ColorsUtils.colorOut(...args);
};
