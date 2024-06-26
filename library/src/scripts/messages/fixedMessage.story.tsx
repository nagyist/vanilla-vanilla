/**
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

import React, { useState } from "react";
import { StoryContent } from "@library/storybook/StoryContent";
import Message from "@library/messages/Message";
import { messagesClasses } from "@library/messages/messageStyles";
import Translate from "@library/content/Translate";
import classNames from "classnames";
import { t } from "@library/utility/appUtils";
import { titleBarVariables } from "@library/headers/TitleBar.variables";
import { negative, negativeUnit } from "@library/styles/styleHelpers";
import { styleUnit } from "@library/styles/styleUnit";
import { ErrorIcon } from "@library/icons/common";

export default {
    title: "Alerts",
};

const message = `Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce blandit lorem ac dui porta, scelerisque placerat felis finibus.`;

function MessageStory() {
    const classesMessages = messagesClasses();
    const [fixedMessageFlag, setFixedMessageFlag] = useState(true);

    const _fixedMessage = fixedMessageFlag && (
        <div
            style={{
                height: styleUnit(titleBarVariables().sizing.height),
                position: "relative",
                marginTop: negativeUnit(titleBarVariables().sizing.height),
            }}
        >
            <Message
                isFixed={true}
                icon={<ErrorIcon className={classNames(classesMessages.errorIcon)} />}
                contents={
                    <div className={classesMessages.content}>
                        <Translate source={message} />
                    </div>
                }
                onConfirm={() => {
                    setFixedMessageFlag(false);
                }}
                stringContents={t(message)}
            />
        </div>
    );
    return (
        <>
            <StoryContent>
                {_fixedMessage}
                <div
                    style={{
                        paddingTop: styleUnit(70),
                    }}
                >
                    <div>
                        <h2> Title </h2>
                    </div>
                    <div>
                        <p>
                            Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce blandit lorem ac dui porta,
                            scelerisque placerat felis finibus.Lorem ipsum dolor sit amet, consectetur adipiscing elit.
                            Fusce blandit lorem ac dui porta, scelerisque placerat felis finibus.Lorem ipsum dolor sit
                            amet, consectetur adipiscing elit. Fusce blandit lorem ac dui porta, scelerisque placerat
                            felis finibus. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce blandit lorem
                            ac dui porta, scelerisque placerat felis finibus.Lorem ipsum dolor sit amet, consectetur
                            adipiscing elit. Fusce blandit lorem ac dui porta, scelerisque placerat felis finibus. Lorem
                            ipsum dolor sit amet, consectetur adipiscing elit. Fusce blandit lorem ac dui porta,
                            scelerisque placerat felis finibus.Lorem ipsum dolor sit amet, consectetur adipiscing elit.
                            Fusce blandit lorem ac dui porta, scelerisque placerat felis finibus.Lorem ipsum dolor sit
                            amet, consectetur adipiscing elit. Fusce blandit lorem ac dui porta, scelerisque placerat
                            felis finibus. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce blandit lorem
                            ac dui porta, scelerisque placerat felis finibus. Lorem ipsum dolor sit amet, consectetur
                            adipiscing elit. Fusce blandit lorem ac dui porta, scelerisque placerat felis finibus. Lorem
                            ipsum dolor sit amet, consectetur adipiscing elit. Fusce blandit lorem ac dui porta,
                            scelerisque placerat felis finibus. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
                            Fusce blandit lorem ac dui porta, scelerisque placerat felis finibus. Lorem ipsum dolor sit
                            amet, consectetur adipiscing elit. Fusce blandit lorem ac dui porta, scelerisque placerat
                            felis finibus. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce blandit lorem
                            ac dui porta, scelerisque placerat felis finibus. Lorem ipsum dolor sit amet, consectetur
                            adipiscing elit. Fusce blandit lorem ac dui porta, scelerisque placerat felis finibus. Lorem
                            ipsum dolor sit amet, consectetur adipiscing elit. Fusce blandit lorem ac dui porta,
                            scelerisque placerat felis finibus. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
                            Fusce blandit lorem ac dui porta, scelerisque placerat felis finibus. Lorem ipsum dolor sit
                            amet, consectetur adipiscing elit. Fusce blandit lorem ac dui porta, scelerisque placerat
                            felis finibus. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce blandit lorem
                            ac dui porta, scelerisque placerat felis finibus. Lorem ipsum dolor sit amet, consectetur
                            adipiscing elit. Fusce blandit lorem ac dui porta, scelerisque placerat felis finibus.
                        </p>
                    </div>
                </div>
            </StoryContent>
        </>
    );
}

export function MessageFixedPosition() {
    return <MessageStory />;
}
