/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license gpl-2.0-only
 */

import { usePermissionsContext } from "@library/features/users/PermissionsContext";
import { t } from "@vanilla/i18n";
import { RecordID, guessOperatingSystem, OS } from "@vanilla/utils";
import React, { useContext } from "react";

export interface IThreadItemContext {
    recordType: "discussion" | "comment";
    recordID: RecordID;
    recordUrl: string;
    timestamp: string;
    name: string;
    handleCopyUrl: () => Promise<void>;
    handleNativeShare?: () => Promise<void>;
    emailUrl: string;
    shareInMessageUrl?: string;
}

const ThreadItemContext = React.createContext<IThreadItemContext>({
    recordType: "discussion",
    recordID: 0,
    recordUrl: "",
    timestamp: "",
    name: "",
    handleCopyUrl: async () => {},
    emailUrl: "",
    shareInMessageUrl: undefined,
});

export function ThreadItemContextProvider(
    props: { children: React.ReactNode } & Omit<
        IThreadItemContext,
        "handleCopyUrl" | "handleNativeShare" | "emailUrl" | "shareInMessageUrl"
    >,
) {
    const { children, ...contextProps } = props;
    const { recordUrl, name } = contextProps;

    const { hasPermission } = usePermissionsContext();
    const canShareInMessage = hasPermission("conversations.add");

    const url = new URL(recordUrl);

    url.searchParams.set("utm_source", "community-share");

    const shareData = {
        url: url.toString(),
        text: t("Check out this post:"),
    };

    const emailSubject = `${t("Check it out:")} ${name}`;
    const emailUrl = `mailto:?subject=${emailSubject}&body=${shareData.text} ${encodeURIComponent(shareData.url)}`;

    const os = guessOperatingSystem();
    const nativeShareAvailable = navigator.share !== undefined && navigator.canShare?.(shareData);
    const useNativeShare = nativeShareAvailable && [OS.ANDROID, OS.IOS].includes(os);

    const handleNativeShare = useNativeShare
        ? async function () {
              await navigator.share(shareData);
          }
        : undefined;

    async function handleCopyUrl() {
        await navigator.clipboard.writeText(shareData.url);
    }

    const shareInMessageUrl = `/messages/add?content=${encodeURIComponent(
        t("Check out this post:") + " " + shareData.url,
    )}`;

    const contextValue = {
        ...contextProps,
        handleCopyUrl,
        handleNativeShare,
        emailUrl,
        shareInMessageUrl: canShareInMessage ? shareInMessageUrl : undefined,
    };

    return <ThreadItemContext.Provider value={contextValue}>{props.children}</ThreadItemContext.Provider>;
}

export function useThreadItemContext() {
    return useContext(ThreadItemContext);
}
