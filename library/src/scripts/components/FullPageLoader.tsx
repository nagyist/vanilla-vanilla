/**
 * @author Stéphane LaFlèche <stephane.l@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

import * as React from "react";
import { t } from "@library/application";
import DocumentTitle from "@library/components/DocumentTitle";

/**
 * A smart loading component. Takes up the full page and only displays in certain scenarios.
 */
export default class FullPageLoader extends React.Component {
    public render() {
        return (
            <div className="fullPageLoader">
                <DocumentTitle title={t("Loading")} />
            </div>
        );
    }
}
