/**
 * @author Stéphane LaFlèche <stephane.l@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

import { useBannerContext } from "@library/banner/BannerContext";
import Hamburger from "@library/flyouts/Hamburger";
import MeBox from "@library/headers/mebox/MeBox";
import CompactMeBox from "@library/headers/mebox/pieces/CompactMeBox";
import CompactSearch from "@library/headers/mebox/pieces/CompactSearch";
import HeaderLogo from "@library/headers/mebox/pieces/HeaderLogo";
import { meBoxClasses } from "@library/headers/mebox/pieces/meBoxStyles";
import TitleBarNav from "@library/headers/mebox/pieces/TitleBarNav";
import { TitleBarNavItem } from "@library/headers/mebox/pieces/TitleBarNavItem";
import { titleBarClasses, titleBarLogoClasses } from "@library/headers/titleBarStyles";
import { titleBarVariables } from "@library/headers/TitleBar.variables";
import { Icon } from "@vanilla/icons";
import Container from "@library/layout/components/Container";
import ConditionalWrap from "@library/layout/ConditionalWrap";
import FlexSpacer from "@library/layout/FlexSpacer";
import { HashOffsetReporter, useScrollOffset } from "@library/layout/ScrollOffsetContext";
import { TitleBarDevices, useTitleBarDevice } from "@library/layout/TitleBarContext";
import BackLink from "@library/routing/links/BackLink";
import SmartLink from "@library/routing/links/SmartLink";
import { LogoType } from "@library/theming/ThemeLogo";
import { t } from "@library/utility/appUtils";
import classNames from "classnames";
import React, { useDebugValue, useEffect, useRef, useState } from "react";
import ReactDOM from "react-dom";
import { animated, useSpring } from "react-spring";
import { useCollisionDetector } from "@vanilla/react-utils";
import { useSelector } from "react-redux";
import { ICoreStoreState } from "@library/redux/reducerRegistry";
import { SearchPageRoute } from "@library/search/SearchPageRoute";
import { useRegisterLink, useSignInLink } from "@library/contexts/EntryLinkContext";
import titleBarNavClasses from "@library/headers/titleBarNavStyles";
import { ISearchScopeNoCompact } from "@library/features/search/SearchScopeContext";
import { SkipNavLink, SkipNavContent } from "@reach/skip-nav";
import { LogoAlignment } from "@library/headers/LogoAlignment";
import { useCurrentUser, useCurrentUserSignedIn } from "@library/features/users/userHooks";
import { useThemeForcedVariables } from "@library/theming/Theme.context";

interface IProps {
    container?: HTMLElement | null; // Element containing header. Should be the default most if not all of the time.
    wrapperComponent?: React.ComponentType<{ children: React.ReactNode }>;
    className?: string;
    title?: string; // Needed for mobile flyouts
    mobileDropDownContent?: React.ReactNode; // Needed for mobile flyouts, does NOT work with hamburger
    isFixed?: boolean;
    useMobileBackButton?: boolean;
    overwriteLogo?: string; // overwrite logo, used for storybook
    hasSubNav?: boolean;
    backgroundColorForMobileDropdown?: boolean; // If the left panel has a background color, we also need it here when the mobile menu's open.
    extraBurgerNavigation?: React.ReactNode;
    forceVisibility?: boolean; // For storybook, as it will disable closing the search
    scope?: ISearchScopeNoCompact;
    forceMenuOpen?: boolean; // For storybook, will force nested menu open
    onlyLogo?: boolean; // display only the logo
}

/**
 * Implements Vanilla Header component. Note that this component uses a react portal.
 * That means the exact location in the page is not that important, since it will
 * render in a specific div in the default-master.
 */
export default function TitleBar(_props: IProps) {
    const props = {
        mobileDropDownContent: null,
        isFixed: true,
        useMobileBackButton: true,
        forceVisibility: false,
        ..._props,
    };

    const containerOptions = titleBarVariables().titleBarContainer;
    const meboxVars = titleBarVariables().meBox;

    const { bgProps, bg2Props, logoProps } = useScrollTransition();
    const { collisionSourceRef, hBoundary1Ref, hBoundary2Ref, hasCollision } = useCollisionDetector();

    const device = useTitleBarDevice();
    const [isSearchOpen, setIsSearchOpen] = useState(props.forceVisibility);
    const [isShowingSuggestions, setIsShowingSuggestions] = useState(false);
    const isCompact = hasCollision || device === TitleBarDevices.COMPACT;
    const showMobileDropDown = isCompact && !isSearchOpen && !!props.title;
    const classesMeBox = meBoxClasses();
    const currentUserIsSignedIn = useCurrentUserSignedIn();
    const isGuest = !currentUserIsSignedIn;
    const vars = titleBarVariables();
    const classes = titleBarClasses();
    const logoClasses = titleBarLogoClasses();
    const showSubNav = device === TitleBarDevices.COMPACT && props.hasSubNav;
    const meBox = isCompact ? !isSearchOpen && <MobileMeBox /> : <DesktopMeBox />;
    const isMobileLogoCentered = vars.mobileLogo.justifyContent === LogoAlignment.CENTER;
    const isDesktopLogoCentered = vars.logo.justifyContent === LogoAlignment.CENTER;
    // When previewing and updating the colors live, there can be flickering of some components.
    // As a result we want to hide them on first render for these cases.

    const isPreviewing = !!useThemeForcedVariables();
    const [isPreviewFirstRender, setIsPreviewFirstRender] = useState(isPreviewing);
    useEffect(() => {
        if (isPreviewFirstRender) {
            setIsPreviewFirstRender(false);
        }
    }, [isPreviewFirstRender]);

    let headerContent = (
        <>
            <HashOffsetReporter className={classes.container}>
                <div className={classes.bgContainer}>
                    <animated.div
                        {...bgProps}
                        className={classNames(classes.bg1, { [classes.swoop]: vars.swoop.amount > 0 })}
                    />
                    {!isPreviewFirstRender && (
                        <animated.div
                            {...bg2Props}
                            className={classNames(classes.bg2, { [classes.swoop]: vars.swoop.amount > 0 })}
                        >
                            {/* Cannot be a background image there will be flickering. */}
                            {vars.colors.bgImage && (
                                <img
                                    src={vars.colors.bgImage}
                                    className={classes.bgImage}
                                    alt={"titleBarImage"}
                                    aria-hidden={true}
                                />
                            )}
                            {vars.overlay && <div className={classes.overlay} />}
                        </animated.div>
                    )}
                </div>
                <Container
                    fullGutter
                    gutterSpacing={containerOptions.gutterSpacing}
                    maxWidth={containerOptions.maxWidth}
                >
                    <div className={classes.titleBarContainer}>
                        <div className={classNames(classes.bar, { isHome: showSubNav })}>
                            {props.onlyLogo ? (
                                <>
                                    {isCompact ? (
                                        <>
                                            {isMobileLogoCentered && <FlexSpacer actualSpacer />}
                                            <div
                                                className={classNames(
                                                    isMobileLogoCentered && classes.logoCenterer,
                                                    logoClasses.mobileLogo,
                                                )}
                                            >
                                                <animated.span className={classes.logoAnimationWrap} {...logoProps}>
                                                    <HeaderLogo
                                                        className={classes.logoContainer}
                                                        logoClassName="titleBar-logo"
                                                        logoType={LogoType.MOBILE}
                                                        overwriteLogo={props.overwriteLogo}
                                                    />
                                                </animated.span>
                                            </div>
                                        </>
                                    ) : (
                                        <animated.div className={classNames(classes.logoAnimationWrap)} {...logoProps}>
                                            <span
                                                className={classNames("logoAlignment", {
                                                    [classes.logoCenterer]: isDesktopLogoCentered,
                                                    [classes.logoLeftAligned]: !isDesktopLogoCentered,
                                                })}
                                            >
                                                <>
                                                    <SkipNavLink className={classes.skipNav}>
                                                        {t("Skip to content")}
                                                    </SkipNavLink>

                                                    <HeaderLogo
                                                        className={classNames(
                                                            "titleBar-logoContainer",
                                                            classes.logoContainer,
                                                        )}
                                                        logoClassName="titleBar-logo"
                                                        logoType={LogoType.DESKTOP}
                                                        overwriteLogo={props.overwriteLogo}
                                                    />
                                                </>
                                            </span>
                                        </animated.div>
                                    )}
                                </>
                            ) : (
                                <>
                                    {isCompact &&
                                        (props.useMobileBackButton ? (
                                            <BackLink
                                                hideIfNoHistory={true}
                                                className={classes.leftFlexBasis}
                                                linkClassName={classes.button}
                                            />
                                        ) : (
                                            <FlexSpacer className="pageHeading-leftSpacer" />
                                        ))}
                                    {!isCompact && (isDesktopLogoCentered ? !isSearchOpen : true) && (
                                        <animated.div className={classNames(classes.logoAnimationWrap)} {...logoProps}>
                                            <span
                                                className={classNames("logoAlignment", {
                                                    [classes.logoCenterer]: isDesktopLogoCentered,
                                                    [classes.logoLeftAligned]: !isDesktopLogoCentered,
                                                })}
                                            >
                                                <>
                                                    <SkipNavLink className={classes.skipNav}>
                                                        {t("Skip to content")}
                                                    </SkipNavLink>

                                                    <HeaderLogo
                                                        className={classNames(
                                                            "titleBar-logoContainer",
                                                            classes.logoContainer,
                                                        )}
                                                        logoClassName="titleBar-logo"
                                                        logoType={LogoType.DESKTOP}
                                                        overwriteLogo={props.overwriteLogo}
                                                    />
                                                </>
                                            </span>
                                        </animated.div>
                                    )}
                                    {!isCompact && !isDesktopLogoCentered && (
                                        <div ref={hBoundary1Ref} style={{ width: 1, height: 1 }} />
                                    )}
                                    {!isSearchOpen && !isCompact && (
                                        <TitleBarNav
                                            forceOpen={props.forceMenuOpen}
                                            isCentered={vars.navAlignment.alignment === "center"}
                                            containerRef={
                                                vars.navAlignment.alignment === "center" && !isDesktopLogoCentered
                                                    ? collisionSourceRef
                                                    : undefined
                                            }
                                            className={classes.nav}
                                            linkClassName={classes.topElement}
                                            afterNode={
                                                !isCompact &&
                                                isDesktopLogoCentered && (
                                                    <div ref={hBoundary2Ref} style={{ width: 1, height: 20 }} />
                                                )
                                            }
                                        />
                                    )}
                                    {isCompact && (
                                        <>
                                            {!isSearchOpen && (
                                                <>
                                                    <Hamburger
                                                        className={classes.hamburger}
                                                        extraNavTop={props.extraBurgerNavigation}
                                                        showCloseIcon={false}
                                                    />
                                                    {isMobileLogoCentered && <FlexSpacer actualSpacer />}
                                                    <div
                                                        className={classNames(
                                                            isMobileLogoCentered && classes.logoCenterer,
                                                            logoClasses.mobileLogo,
                                                        )}
                                                    >
                                                        <animated.span
                                                            className={classes.logoAnimationWrap}
                                                            {...logoProps}
                                                        >
                                                            <HeaderLogo
                                                                className={classes.logoContainer}
                                                                logoClassName="titleBar-logo"
                                                                logoType={LogoType.MOBILE}
                                                                overwriteLogo={props.overwriteLogo}
                                                            />
                                                        </animated.span>
                                                    </div>
                                                </>
                                            )}
                                        </>
                                    )}
                                    {!isCompact && !isDesktopLogoCentered && (
                                        <div ref={hBoundary2Ref} style={{ width: 1, height: 1 }} />
                                    )}
                                    <ConditionalWrap
                                        className={classes.rightFlexBasis}
                                        condition={!!showMobileDropDown}
                                    >
                                        {!isSearchOpen && (
                                            <div className={classes.extraMeBoxIcons}>
                                                {TitleBar.extraMeBoxComponents.map((ComponentName, index) => {
                                                    return <ComponentName key={index} />;
                                                })}
                                            </div>
                                        )}
                                        <CompactSearch
                                            className={classNames(classes.compactSearch, {
                                                isCentered: isSearchOpen,
                                            })}
                                            focusOnMount
                                            placeholder={t("Search")}
                                            open={isSearchOpen}
                                            onSearchButtonClick={() => {
                                                SearchPageRoute.preload();
                                                setIsSearchOpen(true);
                                            }}
                                            onCloseSearch={() => {
                                                setIsSearchOpen(props.forceVisibility); // will be false if not used
                                            }}
                                            cancelButtonClassName={classNames(
                                                classes.topElement,
                                                classes.searchCancel,
                                                titleBarNavClasses().link,
                                            )}
                                            cancelContentClassName="meBox-buttonContent"
                                            buttonClass={classNames(classes.button, {
                                                [classes.buttonOffset]: !isCompact && isGuest,
                                            })}
                                            showingSuggestions={isShowingSuggestions}
                                            onOpenSuggestions={() => setIsShowingSuggestions(true)}
                                            onCloseSuggestions={() => setIsShowingSuggestions(false)}
                                            buttonContentClassName={classNames(
                                                classesMeBox.buttonContent,
                                                "meBox-buttonContent",
                                            )}
                                            clearButtonClass={classes.clearButtonClass}
                                            scope={
                                                props.scope
                                                    ? {
                                                          ...props.scope,
                                                      }
                                                    : undefined
                                            }
                                            searchCloseOverwrites={{
                                                source: "fromTitleBar",
                                                ...vars.stateColors,
                                            }}
                                            overwriteSearchBar={{
                                                compact: isCompact,
                                            }}
                                            withLabel={meboxVars.withLabel}
                                        />
                                        {meBox}
                                    </ConditionalWrap>
                                </>
                            )}
                        </div>
                    </div>
                </Container>
            </HashOffsetReporter>
            <SkipNavContent />
        </>
    );

    const { resetScrollOffset, setScrollOffset, offsetClass } = useScrollOffset();
    const containerElement = props.container !== null ? props.container || document.getElementById("titleBar") : null;
    if (props.wrapperComponent) {
        headerContent = <props.wrapperComponent>{headerContent}</props.wrapperComponent>;
    }

    const containerClasses = classNames(
        "titleBar",
        classes.root,
        props.className,
        { [classes.isSticky]: props.isFixed },
        offsetClass,
    );
    useEffect(() => {
        setScrollOffset(titleBarVariables().sizing.height);
        containerElement?.setAttribute("class", containerClasses);

        return () => {
            resetScrollOffset();
        };
    }, [setScrollOffset, resetScrollOffset, containerElement, containerClasses]);

    if (containerElement) {
        return ReactDOM.createPortal(headerContent, containerElement);
    } else {
        return <header className={containerClasses}>{headerContent}</header>;
    }
}

/**
 * Hook for the scroll transition of the titleBar.
 *
 * The following should happen on scroll if
 * - There is a splash.
 * - We are configured to overlay the splash.
 *
 * - Starts at transparent.
 * - Transitions the background color in over the height of the splash.
 * - Once we pass the splash, transition in the bg image of the splash.
 */
function useScrollTransition() {
    const bgRef = useRef<HTMLDivElement | null>(null);
    const bg2Ref = useRef<HTMLDivElement | null>(null);
    const logoRef = useRef<HTMLDivElement | null>(null);
    const { bannerExists, bannerRect } = useBannerContext();
    const [scrollPos, setScrollPos] = useState(0);
    const fullBleedOptions = titleBarVariables().fullBleed;

    const { doubleLogoStrategy } = titleBarVariables().logo;
    const shouldOverlay = fullBleedOptions.enabled && bannerExists;
    const { topOffset } = useScrollOffset();

    // Scroll handler to pass to the form element.
    useEffect(() => {
        const handler = () => {
            requestAnimationFrame(() => {
                setScrollPos(Math.max(0, window.scrollY));
            });
        };
        if (shouldOverlay || doubleLogoStrategy === "fade-in") {
            window.addEventListener("scroll", handler);
            return () => {
                window.removeEventListener("scroll", handler);
            };
        }
    }, [doubleLogoStrategy, setScrollPos, shouldOverlay]);

    // Calculate some dimensions.
    let bgStart = 0;
    let bgEnd = 0;
    let bg2Start = 0;
    let bg2End = 0;
    if (bannerExists && bannerRect && bg2Ref.current) {
        const bannerEnd = bannerRect.bottom;
        const titleBarHeight = bg2Ref.current.getBoundingClientRect().height;
        bgStart = bannerRect.top;
        bgEnd = bgStart + titleBarHeight;
        bg2Start = bannerEnd - titleBarHeight * 2;
        bg2End = bannerEnd - titleBarHeight;
    }

    const clientHeaderStart = topOffset === 0 ? -1 : 0; // Fix to ensure an empty topOffset starts us at 100% opacity.
    const clientHeaderEnd = topOffset;

    const { bgSpring, bg2Spring, clientHeaderSpring } = useSpring({
        bgSpring: Math.max(bgStart, Math.min(bgEnd, scrollPos)),
        bg2Spring: Math.max(bg2Start, Math.min(bg2End, scrollPos)),
        clientHeaderSpring: Math.max(clientHeaderStart, Math.min(clientHeaderEnd, scrollPos)),
        tension: 100,
    });

    // Fades in first.
    const bgOpacity = bgSpring.interpolate({
        range: [bgStart, bgEnd],
        output: [fullBleedOptions.startingOpacity, fullBleedOptions.endingOpacity],
    });

    // Fades in second.
    const bg2Opacity = bg2Spring.interpolate({
        range: [bg2Start, bg2End],
        output: [0, 1],
    });

    const logoOpacity = clientHeaderSpring.interpolate({
        range: [clientHeaderStart, clientHeaderEnd],
        output: [0, 1],
    });

    const bgProps = shouldOverlay
        ? {
              style: { opacity: bgOpacity },
              ref: bgRef,
          }
        : {};

    const bg2Props = shouldOverlay
        ? {
              style: { opacity: bg2Opacity },
              ref: bg2Ref,
          }
        : {};

    const actualOpacity = logoOpacity.payload?.[0]?.value ?? 0;
    const logoProps =
        doubleLogoStrategy === "fade-in"
            ? {
                  style: {
                      opacity: logoOpacity,
                      pointerEvents: actualOpacity <= 0.15 ? "none" : "initial",
                  },
                  ref: logoRef,
              }
            : {};

    useDebugValue({
        bgProps,
        bg2Props,
        logoProps,
    });
    return {
        bgProps,
        bg2Props,
        logoProps,
    };
}

function DesktopMeBox() {
    const classes = titleBarClasses();
    const currentUser = useCurrentUser();
    const currentUserIsSignedIn = useCurrentUserSignedIn();
    const isGuest = !currentUserIsSignedIn;
    const registerLink = useRegisterLink();
    const signinLink = useSignInLink();
    const guestVars = titleBarVariables().guest;
    const meboxVars = titleBarVariables().meBox;

    if (isGuest) {
        return (
            <div className={classNames("titleBar-nav titleBar-guestNav", classes.nav)}>
                <TitleBarNavItem
                    buttonType={guestVars.signInButtonType}
                    linkClassName={classNames(classes.signIn, classes.guestButton)}
                    to={signinLink}
                >
                    {t("Sign In")}
                </TitleBarNavItem>
                {registerLink && (
                    <TitleBarNavItem
                        buttonType={guestVars.registerButtonType}
                        linkClassName={classNames(classes.register, classes.guestButton)}
                        to={registerLink}
                    >
                        {t("Register")}
                    </TitleBarNavItem>
                )}
            </div>
        );
    } else {
        return (
            <MeBox
                currentUser={currentUser}
                className={classNames("titleBar-meBox", classes.meBox)}
                buttonClassName={classes.button}
                contentClassName={classNames("titleBar-dropDownContents", classes.dropDownContents)}
                withSeparator={meboxVars.withSeparator}
                withLabel={meboxVars.withLabel}
            />
        );
    }
}

// For backwards compatibility
export { TitleBar };

function MobileMeBox() {
    const currentUser = useCurrentUser();
    const currentUserIsSignedIn = useCurrentUserSignedIn();
    const isGuest = !currentUserIsSignedIn;
    const classes = titleBarClasses();
    const signinLink = useSignInLink();
    if (isGuest) {
        return (
            <SmartLink
                className={classNames(classes.centeredButton, classes.button, classes.signInIconOffset)}
                title={t("Sign In")}
                to={signinLink}
            >
                <Icon icon="me-sign-in" />
            </SmartLink>
        );
    } else {
        return <CompactMeBox className={classNames("titleBar-button", classes.button)} currentUser={currentUser} />;
    }
}

/** Hold the extra mebox components before rendering. */
TitleBar.extraMeBoxComponents = [] as React.ComponentType[];

/**
 * Register an extra component to be rendered before the mebox.
 * This will only affect larger screen sizes.
 *
 * @param component The component class to be render.
 */
TitleBar.registerBeforeMeBox = (component: React.ComponentType) => {
    TitleBar.extraMeBoxComponents.push(component);
};
