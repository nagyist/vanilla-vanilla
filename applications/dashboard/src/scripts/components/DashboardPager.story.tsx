/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license gpl-2.0-only
 */

import { DashboardPager } from "@dashboard/components/DashboardPager";
import { dashboardCssDecorator } from "@dashboard/__tests__/dashboardCssDecorator";
import { StoryContent } from "@library/storybook/StoryContent";
import { StoryHeading } from "@library/storybook/StoryHeading";
import { StoryParagraph } from "@library/storybook/StoryParagraph";
import { StoryTileAndTextCompact } from "@library/storybook/StoryTileAndTextCompact";
import { StoryTiles } from "@library/storybook/StoryTiles";

export default {
    title: "Dashboard/Legacy",
    decorators: [dashboardCssDecorator],
};

export function Pagers() {
    return (
        <StoryContent>
            <StoryHeading depth={1}>Dashboard Pagers</StoryHeading>
            <StoryParagraph>
                The pager is a dumb component that just takes properties describing paging information. It fires events
                when page buttons are clicked. You can specify a <code>pageCount</code> for the pager. If you don&apos;t
                have a count then use the <code>hasNext</code> prop instead.
            </StoryParagraph>
            <StoryTiles>
                <StoryTileAndTextCompact text="With Page Count">
                    <DashboardPager page={2} pageCount={3} />
                </StoryTileAndTextCompact>
                <StoryTileAndTextCompact text="Without Page Count">
                    <DashboardPager page={2} hasNext={true} />
                </StoryTileAndTextCompact>
                <StoryTileAndTextCompact text="First Page">
                    <DashboardPager page={1} pageCount={3} />
                </StoryTileAndTextCompact>
                <StoryTileAndTextCompact text="Last Page">
                    <DashboardPager page={5} hasNext={false} />
                </StoryTileAndTextCompact>
                <StoryTileAndTextCompact text="Last Page With Count">
                    <DashboardPager page={10} pageCount={10} />
                </StoryTileAndTextCompact>
                <StoryTileAndTextCompact text="One Page">
                    <DashboardPager page={1} pageCount={1} />
                </StoryTileAndTextCompact>
            </StoryTiles>
        </StoryContent>
    );
}
