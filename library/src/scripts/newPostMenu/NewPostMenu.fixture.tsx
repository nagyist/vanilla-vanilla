/**
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license gpl-2.0-only
 */

import { PostTypes, IAddPost } from "@library/newPostMenu/NewPostMenu";
import { logDebug } from "@vanilla/utils";

export const newPostItems: IAddPost[] = [
    {
        id: "1",
        type: PostTypes.BUTTON,
        action: () => {
            logDebug("Some Action");
        },
        icon: "create-poll",
        label: "New Poll",
    },
    {
        id: "2",
        type: PostTypes.BUTTON,
        action: () => {
            logDebug("Some Action");
        },
        icon: "create-idea",
        label: "New Idea",
    },
    {
        id: "3",
        type: PostTypes.BUTTON,
        action: () => {
            logDebug("Some Action");
        },
        icon: "create-discussion",
        label: "New Discussion",
    },
    {
        id: "4",
        type: PostTypes.LINK,
        action: () => {
            logDebug("Some Action");
        },
        icon: "create-question",
        label: "New Question",
    },
    {
        id: "5",
        type: PostTypes.LINK,
        action: () => {
            logDebug("Some Action");
        },
        icon: "create-event",
        label: "New Event",
    },
];
