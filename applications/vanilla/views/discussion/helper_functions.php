<?php
/**
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPLv2
 */

if (!defined("APPLICATION")) {
    exit();
}

use Vanilla\Forum\Models\CommentThreadModel;
use Vanilla\Theme\BoxThemeShim;
use Vanilla\Dashboard\Controllers\Api\ProfileFieldsApiController;
use Vanilla\Utility\HtmlUtils;
use Vanilla\Web\TwigStaticRenderer;
use Vanilla\Dashboard\Models\AttachmentService;

if (!function_exists("getCustomFields")):
    /**
     * Get the profile fields for a user to display next to their name.
     *
     * @param int $userID
     * @return string all input field values for the userId that has posts in their displayOptions.
     * @since 2.1
     */
    function getCustomFields($userID)
    {
        if (Gdn::config("Feature.CustomProfileFields.Enabled")) {
            $profileFields = Gdn::getContainer()
                ->get("UsersAPIController")
                ->get_profileFields($userID);
            $index = Gdn::getContainer()
                ->get(ProfileFieldsApiController::class)
                ->index();
            $string = "";
            foreach ($index as $fieldFromIndex) {
                if ($fieldFromIndex["displayOptions"]["posts"] && $profileFields[$fieldFromIndex["apiName"]]) {
                    $string .= htmlspecialchars($profileFields[$fieldFromIndex["apiName"]]) . " ";
                }
            }
            return $string;
        }
    }
endif;

if (!function_exists("getPostMeta")):
    function getPostMeta($discussionID)
    {
        if (\Vanilla\FeatureFlagHelper::featureEnabled(\Vanilla\Forum\Models\PostTypeModel::FEATURE_POST_TYPES_AND_POST_FIELDS)) {
            $internalClient = \Gdn::getContainer()->get(\Vanilla\Http\InternalClient::class);
            $discussion = $internalClient->get("/discussions/$discussionID?expand=postMeta")->getBody();
            $discussionPostMeta = $discussion["postMeta"];
            $postFieldConfigs = $internalClient->get("/post-fields", ["isActive" => true])->getBody();

            $postFields = [];
            foreach ($discussionPostMeta as $postFieldID => $value) {
                $postFieldConfig = array_column($postFieldConfigs, null, "postFieldID")[$postFieldID];
                $postFields[] = [
                    "postFieldID" => $postFieldID,
                    "label" => $postFieldConfig["label"],
                    "description" => $postFieldConfig["description"],
                    "dataType" => $postFieldConfig["dataType"],
                    "value" => $value,
                ];
            }

            return ["postFields" => $postFields];
        }
    }
endif;

if (!function_exists("formatBody")):
    /**
     * Format content of comment or discussion.
     *
     * Event argument for $object will be 'Comment' or 'Discussion'.
     *
     * @param Gdn_DataSet $object Comment or discussion.
     * @return string Parsed body.
     * @since 2.1
     */
    function formatBody($object)
    {
        Gdn::controller()->fireEvent("BeforeCommentBody");
        $object->FormatBody = Gdn_Format::to($object->Body, $object->Format);
        Gdn::controller()->fireEvent("AfterCommentFormat");

        $body = $object->FormatBody;

        if (isset($object->parentCommentID)) {
            $threadedModel = Gdn::getContainer()->get(CommentThreadModel::class);
            $quote = $threadedModel->renderParentCommentAsQuote((array) $object);
            $body = $quote . $body;
        }

        return $body;
    }
endif;

if (!function_exists("writeBookmarkLink")):
    /**
     * Output link to (un)boomark a discussion.
     */
    function writeBookmarkLink()
    {
        if (!Gdn::session()->isValid()) {
            return "";
        }

        $discussion = Gdn::controller()->data("Discussion");
        $isBookmarked = $discussion->Bookmarked == "1";

        // Bookmark link
        $title = t($isBookmarked ? "Unbookmark" : "Bookmark");

        $accessibleLabel = HtmlUtils::accessibleLabel('%s for discussion: "%s"', [
            t($isBookmarked ? "Unbookmark" : "Bookmark"),
            is_array($discussion) ? $discussion["Name"] : $discussion->Name,
        ]);

        echo anchor(
            $title,
            "/discussion/bookmark/" .
                $discussion->DiscussionID .
                "/" .
                Gdn::session()->transientKey() .
                "?Target=" .
                urlencode(Gdn::controller()->SelfUrl),
            "Hijack Bookmark" . ($isBookmarked ? " Bookmarked" : ""),
            ["title" => $title, "aria-label" => $accessibleLabel]
        );
    }
endif;

if (!function_exists("writeComment")):
    /**
     * Outputs a formatted comment.
     *
     * Prior to 2.1, this also output the discussion ("FirstComment") to the browser.
     * That has moved to the discussion.php view.
     *
     * @param Gdn_DataSet $comment .
     * @param Gdn_Controller $sender .
     * @param Gdn_Session $session .
     * @param int $CurrentOffet How many comments into the discussion we are (for anchors).
     */
    function writeComment($comment, $sender, $session, $currentOffset)
    {
        // Whether to order the name & photo with the latter first.
        static $userPhotoFirst = null;

        $comment = is_array($comment) ? (object) $comment : $comment;

        if ($userPhotoFirst === null) {
            $userPhotoFirst = c("Vanilla.Comment.UserPhotoFirst", true);
        }
        $isTrollComment = $comment->IsTroll ?? false;
        $author = Gdn::userModel()->getID($comment->InsertUserID);
        $permalink = val(
            "Url",
            $comment,
            "/discussion/comment/" . $comment->CommentID . "/#Comment_" . $comment->CommentID
        );

        // Set CanEditComments (whether to show checkboxes)
        if (!property_exists($sender, "CanEditComments")) {
            $sender->CanEditComments =
                $session->checkPermission("Vanilla.Comments.Edit", true, "Category", "any") &&
                c("Vanilla.AdminCheckboxes.Use");
        }
        // Prep event args
        $cssClass = cssClass($comment, false);
        $sender->EventArguments["Comment"] = &$comment;
        $sender->EventArguments["Author"] = &$author;
        $sender->EventArguments["CssClass"] = &$cssClass;
        $sender->EventArguments["CurrentOffset"] = $currentOffset;
        $sender->EventArguments["Permalink"] = $permalink;

        // Needed in writeCommentOptions()
        if ($sender->data("Discussion", null) === null) {
            $discussionModel = new DiscussionModel();
            $discussion = $discussionModel->getID($comment->DiscussionID);
            $sender->setData("Discussion", $discussion);
        }

        if ($sender->data("Discussion.InsertUserID") === $comment->InsertUserID) {
            $cssClass .= " isOriginalPoster";
        }

        $scoreCssClass = scoreCssClass($comment);
        if ($scoreCssClass) {
            $cssClass .= " " . $scoreCssClass;
            setValue("_CssClass", $comment, $scoreCssClass);
        }

        // DEPRECATED ARGUMENTS (as of 2.1)
        $sender->EventArguments["Object"] = &$comment;
        $sender->EventArguments["Type"] = "Comment";

        // First comment template event
        $sender->fireEvent("BeforeCommentDisplay");
        ?>
        <li class="<?php echo $cssClass; ?> pageBox" id="<?php echo "Comment_" . $comment->CommentID; ?>">
            <div class="Comment">

                <?php // Write a stub for the latest comment so it's easy to link to it from outside.

        if ($currentOffset == Gdn::controller()->data("_LatestItem") && Gdn::config("Vanilla.Comments.AutoOffset")) {
                    echo '<span id="latest"></span>';
                } ?>
                <?php if (!BoxThemeShim::isActive() && !$isTrollComment) { ?>
                    <div class="Options">
                        <?php writeCommentOptions($comment); ?>
                    </div>
                <?php } ?>
                <?php $sender->fireEvent("BeforeCommentMeta"); ?>
                <div class="Item-Header CommentHeader">
                    <?php BoxThemeShim::activeHtml(userPhoto($author)); ?>
                    <?php BoxThemeShim::activeHtml('<div class="Item-HeaderContent">'); ?>
                    <div class="AuthorWrap">
                        <span class="Author">
                                <?php
                                if ($userPhotoFirst) {
                                    BoxThemeShim::inactiveHtml(userPhoto($author));
                                    echo userAnchor($author, "Username");
                                } else {
                                    echo userAnchor($author, "Username");
                                    BoxThemeShim::inactiveHtml(userPhoto($author));
                                }
                                echo formatMeAction($comment);
                                $sender->fireEvent("AuthorPhoto");
                                ?>

                        </span>

                        <span class="AuthorInfo">
                            <?php if (!$isTrollComment) {
                                if (
                                    Gdn::config("Feature.CustomProfileFields.Enabled") &&
                                    function_exists("getCustomFields") &&
                                    is_object($author) &&
                                    $author->UserID &&
                                    !$author->Deleted
                                ) {
                                    echo getCustomFields($author->UserID);
                                } else {
                                    echo " " .
                                        wrapIf(htmlspecialchars(val("Title", $author) ?? ""), "span", [
                                            "class" => "MItem AuthorTitle",
                                        ]);
                                    echo " " .
                                        wrapIf(htmlspecialchars(val("Location", $author) ?? ""), "span", [
                                            "class" => "MItem AuthorLocation",
                                        ]);
                                }
                                $sender->fireEvent("AuthorInfo");
                            } ?>
                        </span>
                    </div>
                    <div class="Meta CommentMeta CommentInfo">
                        <span class="MItem DateCreated">
                           <?php echo anchor(
                               Gdn_Format::date($comment->DateInserted, "html"),
                               $permalink,
                               "Permalink",
                               ["name" => "Item_" . $currentOffset, "rel" => "nofollow"]
                           ); ?>
                        </span>
                        <?php echo dateUpdated($comment, ['<span class="MItem">', "</span>"]); ?>
                        <?php
                        // Include source if one was set
                        if ($source = val("Source", $comment)) {
                            echo wrap(sprintf(t("via %s"), t($source . " Source", $source)), "span", [
                                "class" => "MItem Source",
                            ]);
                        }

                        // Include IP Address if we have permission
                        if ($session->checkPermission("Garden.PersonalInfo.View")) {
                            echo wrap(ipAnchor($comment->InsertIPAddress), "span", ["class" => "MItem IPAddress"]);
                        }

                        $sender->fireEvent("CommentInfo");
                        $sender->fireEvent("InsideCommentMeta"); // DEPRECATED
                        $sender->fireEvent("AfterCommentMeta");
                        // DEPRECATED
        ?>
                    </div>
                    <?php BoxThemeShim::activeHtml("</div>"); ?>
                    <?php if (BoxThemeShim::isActive()) { ?>
                        <div class="Options">
                            <?php writeCommentOptions($comment); ?>
                        </div>
                    <?php } ?>
                </div>
                <div class="Item-BodyWrap">
                    <div class="Item-Body">
                        <div class="Message userContent">
                            <?php if ($isTrollComment && $session->checkPermission("Garden.Moderation.Manage")) {
                                echo TwigStaticRenderer::renderReactModule("TrollComment", [
                                    "comment" => formatBody($comment),
                                    "hideText" => Gdn::config(
                                        TrollManagementPlugin::REMOVED_CONTECT_KEY,
                                        "This content has been removed."
                                    ),
                                ]);
                            } else {
                                echo formatBody($comment);
                            } ?>
                        </div>
                        <?php if (!$isTrollComment) {
                            $sender->fireEvent("AfterCommentBody");
                            writeReactions($comment);
                            if (val("Attachments", $comment)) {
                                writeAttachments($comment->Attachments);
                            }
                        } ?>
                    </div>
                </div>
            </div>
        </li>
        <?php $sender->fireEvent("AfterComment");
    }
endif;

if (!function_exists("discussionOptionsToDropdown")):
    /**
     * @param array $options
     * @param DropdownModule|null $dropdown
     * @return DropdownModule
     */
    function discussionOptionsToDropdown(array $options, $dropdown = null)
    {
        if (is_null($dropdown)) {
            $dropdown = new DropdownModule("dropdown", "", "OptionsMenu");
        }

        if (!empty($options)) {
            foreach ($options as $option) {
                $dropdown->addLink(
                    $option["Label"] ?? "",
                    $option["Url"] ?? "",
                    NavModule::textToKey($option["Label"] ?? ""),
                    $option["Class"] ?? false
                );
            }
        }

        return $dropdown;
    }
endif;

if (!function_exists("getDiscussionOptions")):
    /**
     * Get options for the current discussion.
     *
     * @param DataSet $discussion .
     * @return array $options Each element must include keys 'Label' and 'Url'.
     * @since 2.1
     */
    function getDiscussionOptions($discussion = null)
    {
        $options = [];

        $sender = Gdn::controller();
        $session = Gdn::session();

        if ($discussion == null) {
            $discussion = $sender->data("Discussion");
        }
        $categoryID = val("CategoryID", $discussion);
        if (!$categoryID && property_exists($sender, "Discussion")) {
            $categoryID = val("CategoryID", $sender->Discussion);
        }

        // Build the $Options array based on current user's permission.
        // Can the user edit the discussion?
        $canEdit = DiscussionModel::canEdit($discussion, $timeLeft);
        if ($canEdit) {
            if ($timeLeft) {
                $timeLeft = " (" . Gdn_Format::seconds($timeLeft) . ")";
            }
            $options["EditDiscussion"] = [
                "Label" => t("Edit") . $timeLeft,
                "Url" => "/post/editdiscussion/" . $discussion->DiscussionID,
            ];
        }

        // Can the user announce?
        if (CategoryModel::checkPermission($categoryID, "Vanilla.Discussions.Announce")) {
            $options["AnnounceDiscussion"] = [
                "Label" => t("Announce"),
                "Url" =>
                    "/discussion/announce?discussionid=" .
                    $discussion->DiscussionID .
                    "&Target=" .
                    urlencode($sender->SelfUrl . "#Head"),
                "Class" => "AnnounceDiscussion Popup",
            ];
        }

        // Can the user sink?
        if (CategoryModel::checkPermission($categoryID, "Vanilla.Discussions.Sink")) {
            $newSink = (int) !$discussion->Sink;
            $options["SinkDiscussion"] = [
                "Label" => t($discussion->Sink ? "Unsink" : "Sink"),
                "Url" => "/discussion/sink?discussionid={$discussion->DiscussionID}&sink=$newSink",
                "Class" => "SinkDiscussion Hijack",
            ];
        }

        // Can the user close?
        if (CategoryModel::checkPermission($categoryID, "Vanilla.Discussions.Close")) {
            $newClosed = (int) !$discussion->Closed;
            $options["CloseDiscussion"] = [
                "Label" => t($discussion->Closed ? "Reopen" : "Close"),
                "Url" => "/discussion/close?discussionid={$discussion->DiscussionID}&close=$newClosed",
                "Class" => "CloseDiscussion Hijack",
            ];
        }

        if ($canEdit && valr("Attributes.ForeignUrl", $discussion)) {
            $options["RefetchPage"] = [
                "Label" => t("Refetch Page"),
                "Url" => "/discussion/refetchpageinfo.json?discussionid=" . $discussion->DiscussionID,
                "Class" => "RefetchPage Hijack",
            ];
        }

        // Can the user move?
        if ($canEdit && $session->checkPermission("Garden.Moderation.Manage")) {
            $options["MoveDiscussion"] = [
                "Label" => t("Move"),
                "Url" => "/moderation/confirmdiscussionmoves?discussionid=" . $discussion->DiscussionID,
                "Class" => "MoveDiscussion Popup",
            ];
        }

        // Can the user delete?
        if (CategoryModel::checkPermission($categoryID, "Vanilla.Discussions.Delete")) {
            $category = CategoryModel::categories($categoryID);
            $options["DeleteDiscussion"] = [
                "Label" => t("Delete Discussion"),
                "Url" =>
                    "/discussion/delete?discussionid=" .
                    $discussion->DiscussionID .
                    "&target=" .
                    urlencode(categoryUrl($category)),
                "Class" => "DeleteDiscussion Popup",
            ];
        }

        // DEPRECATED (as of 2.1)
        $sender->EventArguments["Type"] = "Discussion";

        // Allow plugins to add options.
        $sender->EventArguments["DiscussionOptions"] = &$options;
        $sender->EventArguments["Discussion"] = $discussion;
        $sender->fireEvent("DiscussionOptions");

        return $options;
    }
endif;

if (!function_exists("getDiscussionOptionsDropdown")):
    /**
     * Constructs an options dropdown menu for a discussion.
     *
     * @param object|array|null $discussion The discussion to get the dropdown options for.
     * @return DropdownModule A dropdown consisting of discussion options.
     * @throws Exception
     */
    function getDiscussionOptionsDropdown($discussion = null)
    {
        $dropdown = new DropdownModule("dropdown", "", "OptionsMenu");
        $sender = Gdn::controller();
        $session = Gdn::session();

        if ($discussion == null) {
            $discussion = $sender->data("Discussion");
        }

        $categoryID = val("CategoryID", $discussion);

        if (!$categoryID && property_exists($sender, "Discussion")) {
            trace("Getting category ID from controller Discussion property.");
            $categoryID = val("CategoryID", $sender->Discussion);
        }

        $discussionID = $discussion->DiscussionID;
        $categoryUrl = urlencode(categoryUrl(CategoryModel::categories($categoryID)));

        // Permissions
        $canEdit = DiscussionModel::canEdit($discussion, $timeLeft);
        $canAnnounce = CategoryModel::checkPermission($categoryID, "Vanilla.Discussions.Announce");
        $canSink = CategoryModel::checkPermission($categoryID, "Vanilla.Discussions.Sink");
        $canClose = DiscussionModel::canClose($discussion);
        $canDelete = CategoryModel::checkPermission($categoryID, "Vanilla.Discussions.Delete");
        $canMove = $canEdit && $session->checkPermission("Garden.Moderation.Manage");
        $canRefetch = $canEdit && valr("Attributes.ForeignUrl", $discussion);
        $canDismiss =
            c("Vanilla.Discussions.Dismiss", 1) &&
            $discussion->Announce &&
            !$discussion->Dismissed &&
            $session->isValid();
        $canTag =
            c("Tagging.Discussions.Enabled") &&
            checkPermission("Vanilla.Tagging.Add") &&
            in_array(strtolower($sender->ControllerName), ["discussionscontroller", "categoriescontroller"]);
        $canAddToCollection = $session->checkPermission("Garden.Community.Manage");

        if ($canEdit && $timeLeft) {
            $timeLeft = " (" . Gdn_Format::seconds($timeLeft) . ")";
        }

        $dropdown
            ->addLinkIf(
                $canDismiss,
                t("Dismiss"),
                "vanilla/discussion/dismissannouncement?discussionid={$discussionID}",
                "dismiss",
                "DismissAnnouncement Hijack"
            )
            ->addLinkIf($canEdit, t("Edit") . $timeLeft, "/post/editdiscussion/" . $discussionID, "edit")
            ->addLinkIf(
                $canTag,
                t("Tag"),
                "/discussion/tag?discussionid=" . $discussionID,
                "tag",
                "TagDiscussion Popup"
            );

        if (($canEdit && $canAnnounce) || $canAddToCollection) {
            $dropdown->addDivider();
        }

        $dropdown
            ->addLinkIf(
                $canAnnounce,
                t("Announce"),
                "/discussion/announce?discussionid=" . $discussionID,
                "announce",
                "AnnounceDiscussion Popup"
            )
            ->addLinkif(
                $canAddToCollection,
                t("Add to Collection"),
                "#",
                "discussionAddToCollection",
                "js-addDiscussionToCollection",
                [],
                [
                    "attributes" => [
                        "data-discussionID" => $discussion->DiscussionID,
                        "data-recordType" => "discussion",
                    ],
                ]
            )
            ->addLinkIf(
                $canSink,
                t($discussion->Sink ? "Unsink" : "Sink"),
                "/discussion/sink?discussionid=" . $discussionID . "&sink=" . (int) !$discussion->Sink,
                "sink",
                "SinkDiscussion Hijack"
            )
            ->addLinkIf(
                $canClose,
                t($discussion->Closed ? "Reopen" : "Close"),
                "/discussion/close?discussionid=" . $discussionID . "&close=" . (int) !$discussion->Closed,
                "close",
                "CloseDiscussion Hijack"
            )
            ->addLinkIf(
                $canRefetch,
                t("Refetch Page"),
                "/discussion/refetchpageinfo.json?discussionid=" . $discussionID,
                "refetch",
                "RefetchPage Hijack"
            )
            ->addLinkIf(
                $canMove,
                t("Move"),
                "/moderation/confirmdiscussionmoves?discussionid=" . $discussionID,
                "move",
                "MoveDiscussion Popup"
            );

        $hasDiv = false;
        if ($session->checkPermission("Garden.Moderation.Manage")) {
            if (!empty(val("DateUpdated", $discussion))) {
                $hasDiv = true;
                $dropdown
                    ->addDivider()
                    ->addLink(
                        t("Revision History"),
                        "/log/filter?" . http_build_query(["recordType" => "discussion", "recordID" => $discussionID]),
                        "discussionRevisionHistory",
                        "RevisionHistory"
                    );
            }
            $dropdown->addDividerIf(!$hasDiv)->addLink(
                t("Deleted Comments"),
                "/log/filter?" .
                    http_build_query([
                        "parentRecordID" => $discussionID,
                        "recordType" => "comment",
                        "operation" => "delete",
                    ]),
                "deletedComments",
                "DeletedComments"
            );
        }

        if ($canDelete) {
            $dropdown
                ->addDivider()
                ->addLink(
                    t("Delete Discussion"),
                    "/discussion/delete?discussionid=" . $discussionID . "&target=" . $categoryUrl,
                    "delete",
                    "DeleteDiscussion Popup"
                );
        }

        $integrationsCatalog = Gdn::getContainer()
            ->get(AttachmentService::class)
            ->getCatalog();
        if (count($integrationsCatalog) > 0) {
            $dropdown->addReact("integrations", "LegacyIntegrationsOptionsMenuItems", [
                "recordType" => "discussion",
                "recordID" => $discussionID,
                "redirectTarget" => discussionUrl($discussion, true),
                "isAuthor" => $session->UserID == $discussion->InsertUserID,
            ]);
        }

        // DEPRECATED
        $options = [];
        $sender->EventArguments["DiscussionOptions"] = &$options;
        $sender->EventArguments["Discussion"] = $discussion;
        $sender->fireEvent("DiscussionOptions");

        // Backwards compatibility
        $dropdown = discussionOptionsToDropdown($options, $dropdown);

        // Allow plugins to edit the dropdown.
        $sender->EventArguments["DiscussionOptionsDropdown"] = &$dropdown;
        $sender->EventArguments["Discussion"] = $discussion;
        $sender->fireEvent("DiscussionOptionsDropdown");

        return $dropdown;
    }
endif;

/**
 * Output moderation checkbox.
 *
 * @since 2.1
 */
if (!function_exists("WriteAdminCheck")):
    function writeAdminCheck($object = null)
    {
        if (!Gdn::controller()->CanEditComments || !c("Vanilla.AdminCheckboxes.Use")) {
            return;
        }
        echo '<span class="AdminCheck"><input type="checkbox" aria-label="' .
            t("Select Discussion") .
            '" name="Toggle"></span>';
    }
endif;

/**
 * Output discussion options.
 *
 * @since 2.1
 */
if (!function_exists("writeDiscussionOptions")):
    /**
     * Prints an options dropdown menu for a discussion.
     *
     * @param object|array|null $discussion The discussion to get the dropdown options for.
     * @deprecated
     */
    function writeDiscussionOptions($discussion = null)
    {
        $options = getDiscussionOptions($discussion);

        if (empty($options)) {
            return;
        }

        echo ' <span class="ToggleFlyout OptionsMenu">';
        echo '<span class="OptionsTitle" title="' . t("Options") . '">' . t("Options") . "</span>";
        echo sprite("SpFlyoutHandle", "Arrow");
        echo '<ul class="Flyout MenuItems" style="display: none;">';
        foreach ($options as $code => $option) {
            echo wrap(anchor($option["Label"], $option["Url"], val("Class", $option, $code)), "li");
        }
        echo "</ul>";
        echo "</span>";
    }
endif;

if (!function_exists("getCommentOptions")):
    /**
     * Get comment options.
     *
     * @param object $comment The comment to get the options for.
     * @return array $options Each element must include keys 'Label' and 'Url'.
     * @since 2.1
     */
    function getCommentOptions($comment)
    {
        $options = [];

        if (!is_numeric(val("CommentID", $comment))) {
            return $options;
        }

        $sender = Gdn::controller();
        $session = Gdn::session();
        $discussion = Gdn::controller()->data("Discussion");

        $categoryID = val("CategoryID", $discussion);

        // Can the user edit the comment?
        $canEdit = CommentModel::canEdit($comment, $timeLeft);
        if ($canEdit) {
            if ($timeLeft) {
                $timeLeft = " (" . Gdn_Format::seconds($timeLeft) . ")";
            }
            $options["EditComment"] = [
                "Label" => t("Edit") . $timeLeft,
                "Url" => "/post/editcomment/" . $comment->CommentID,
                "EditComment",
            ];
        }

        if ($session->checkPermission("Garden.Moderation.Manage") && !empty(val("DateUpdated", $comment))) {
            $options["RevisionHistory"] = [
                "Label" => t("Revision History"),
                "Url" =>
                    "/log/filter?" . http_build_query(["recordType" => "comment", "recordID" => $comment->CommentID]),
                "RevisionHistory",
            ];
        }

        // Can the user delete the comment?
        $canDelete = CategoryModel::checkPermission($categoryID, "Vanilla.Comments.Delete");
        $canSelfDelete =
            $canEdit && $session->UserID == $comment->InsertUserID && c("Vanilla.Comments.AllowSelfDelete");
        if ($canDelete || $canSelfDelete) {
            $options["DeleteComment"] = [
                "Label" => t("Delete"),
                "Url" =>
                    "/discussion/deletecomment/" .
                    $comment->CommentID .
                    "/" .
                    $session->transientKey() .
                    "/?Target=" .
                    urlencode("/discussion/{$comment->DiscussionID}/x"),
                "Class" => "DeleteComment",
            ];
        }

        $integrationsCatalog = Gdn::getContainer()
            ->get(AttachmentService::class)
            ->getCatalog();
        if (count($integrationsCatalog) > 0) {
            $options["Integrations"] = TwigStaticRenderer::renderReactModule("LegacyIntegrationsOptionsMenuItems", [
                "recordType" => "comment",
                "recordID" => $comment->CommentID,
                "redirectTarget" => commentUrl($comment),
                "isAuthor" => $session->UserID == $comment->InsertUserID,
            ]);
        }

        // DEPRECATED (as of 2.1)
        $sender->EventArguments["Type"] = "Comment";

        // Allow plugins to add options
        $sender->EventArguments["CommentOptions"] = &$options;
        $sender->EventArguments["Comment"] = $comment;
        $sender->fireEvent("CommentOptions");

        return $options;
    }
endif;

if (!function_exists("writeCommentOptions")):
    /**
     * Output comment options.
     *
     * @param DataSet $comment
     * @since 2.1
     */
    function writeCommentOptions($comment)
    {
        $controller = Gdn::controller();
        $session = Gdn::session();

        $id = $comment->CommentID;
        $options = getCommentOptions($comment);
        if (empty($options)) {
            return;
        }

        echo '<span class="ToggleFlyout OptionsMenu">';
        echo '<span class="OptionsTitle" title="' . t("Options") . '">' . t("Options") . "</span>";
        echo sprite("SpFlyoutHandle", "Arrow");
        echo '<ul class="Flyout MenuItems">';
        foreach ($options as $code => $option) {
            if (is_string($option)) {
                echo $option;
            } else {
                echo wrap(anchor($option["Label"], $option["Url"], val("Class", $option, $code)), "li");
            }
        }
        echo "</ul>";
        echo "</span>";
        if (c("Vanilla.AdminCheckboxes.Use")) {
            // Only show the checkbox if the user has permission to affect multiple items
            $discussion = Gdn::controller()->data("Discussion");
            if (CategoryModel::checkPermission(val("CategoryID", $discussion), "Vanilla.Comments.Delete")) {
                if (!property_exists($controller, "CheckedComments")) {
                    $controller->CheckedComments = $session->getAttribute("CheckedComments", []);
                }
                $itemSelected = inSubArray($id, $controller->CheckedComments);
                echo '<span class="AdminCheck"><input type="checkbox" aria-label="' .
                    t("Select Discussion") .
                    '" name="' .
                    "Comment" .
                    'ID[]" value="' .
                    $id .
                    '"' .
                    ($itemSelected ? ' checked="checked"' : "") .
                    " /></span>";
            }
        }
    }
endif;

if (!function_exists("writeCommentForm")):
    /**
     * Output comment form.
     *
     * @since 2.1
     */
    function writeCommentForm()
    {
        $session = Gdn::session();
        $controller = Gdn::controller();

        $discussion = $controller->data("Discussion");
        $categoryID = val("CategoryID", $discussion);
        $userCanClose = CategoryModel::checkPermission($categoryID, "Vanilla.Discussions.Close");
        $userCanComment = CategoryModel::checkPermission($categoryID, "Vanilla.Comments.Add");

        // Closed notification
        if ($discussion->Closed == "1") { ?>
            <div class="Foot Closed pageBox">
                <div class="Note Closed"><?php echo t("This discussion has been closed."); ?></div>
            </div>
        <?php } elseif (!$userCanComment) {
            if (!Gdn::session()->isValid()) {
                if (Gdn::themeFeatures()->get("NewGuestModule")) {
                    /** @var GuestModule $guestModule */
                    $guestModule = Gdn::getContainer()->get(GuestModule::class);
                    $guestModule->setWidgetAlignment("center");
                    $guestModule->setDesktopOnlyWidget(true);
                    echo $guestModule;
                } else {
                     ?>
                    <div class="Foot Closed pageBox">
                        <div class="Note Closed SignInOrRegister"><?php
                        $popup = c("Garden.SignIn.Popup") ? ' class="Popup"' : "";
                        $returnUrl = Gdn::request()->pathAndQuery();
                        echo formatString(
                            t(
                                "Sign In or Register to Comment.",
                                '<a href="{SignInUrl,html}"{Popup}>Sign In</a> or <a href="{RegisterUrl,html}">Register</a> to comment.'
                            ),
                            [
                                "SignInUrl" => url(signInUrl($returnUrl)),
                                "RegisterUrl" => url(registerUrl($returnUrl)),
                                "Popup" => $popup,
                            ]
                        );
                        ?>
                        </div>
                        <?php
                    //echo anchor(t('All Discussions'), 'discussions', 'TabLink');
                    ?>
                    </div>
                    <?php
                }
            }
        }

        if (($discussion->Closed == "1" && $userCanClose) || ($discussion->Closed == "0" && $userCanComment)) {
            echo $controller->fetchView("comment", "post", "vanilla");
        }
    }
endif;

if (!function_exists("writeCommentFormHeader")):
    /**
     *
     */
    function writeCommentFormHeader()
    {
        $session = Gdn::session();
        if (c("Vanilla.Comment.UserPhotoFirst", true)) {
            echo userPhoto($session->User);
            echo userAnchor($session->User, "Username");
        } else {
            echo userAnchor($session->User, "Username");
            echo userPhoto($session->User);
        }
    }
endif;

if (!function_exists("writeEmbedCommentForm")):
    /**
     *
     */
    function writeEmbedCommentForm()
    {
        $session = Gdn::session();
        $controller = Gdn::controller();
        $discussion = $controller->data("Discussion");

        if ($discussion && $discussion->Closed == "1") { ?>
            <div class="Foot Closed">
                <div class="Note Closed"><?php echo t("This discussion has been closed."); ?></div>
            </div>
        <?php } else { ?>
            <h2><?php echo t("Leave a comment"); ?></h2>
            <div class="MessageForm CommentForm EmbedCommentForm">
                <?php
                echo '<div class="FormWrapper">';
                echo $controller->Form->open(["id" => "Form_Comment"]);
                echo $controller->Form->errors();
                echo $controller->Form->hidden("Name");
                echo wrap($controller->Form->bodyBox("Body"));
                echo "<div class=\"Buttons\">\n";

                $allowSigninPopup = c("Garden.SignIn.Popup");
                $attributes = ["target" => "_top"];

                // If we aren't ajaxing this call then we need to target the url of the parent frame.
                $returnUrl = $controller->data("ForeignSource.vanilla_url", Gdn::request()->pathAndQuery());
                $returnUrl = trim($returnUrl, "/") . "#vanilla-comments";

                if ($session->isValid()) {
                    $authenticationUrl = url(signOutUrl($returnUrl), true);
                    echo wrap(
                        sprintf(
                            t(
                                'Commenting as %1$s (%2$s)',
                                'Commenting as %1$s <span class="SignOutWrap">(%2$s)</span>'
                            ),
                            Gdn_Format::text($session->User->Name),
                            anchor(t("Sign Out"), $authenticationUrl, "SignOut", $attributes)
                        ),
                        "div",
                        ["class" => "Author"]
                    );
                    echo $controller->Form->button("Post Comment", ["class" => "Button CommentButton"]);
                } else {
                    $authenticationUrl = url(signInUrl($returnUrl), true);
                    if ($allowSigninPopup) {
                        $cssClass = "SignInPopup Button Stash";
                    } else {
                        $cssClass = "Button Stash";
                    }

                    echo anchor(t("Comment As ..."), $authenticationUrl, $cssClass, $attributes);
                }
                echo "</div>\n";
                echo $controller->Form->close();
                echo "</div> ";
                ?>
            </div>
        <?php }
    }
endif;

if (!function_exists("isMeAction")):
    /**
     *
     *
     * @param $row
     * @return bool|void
     */
    function isMeAction($row)
    {
        if (!c("Garden.Format.MeActions")) {
            return;
        }
        $row = (array) $row;
        if (!array_key_exists("Body", $row)) {
            return false;
        }

        return strpos(trim($row["Body"]), "/me ") === 0;
    }
endif;

if (!function_exists("formatMeAction")):
    /**
     *
     *
     * @param $comment
     * @return string|void
     */
    function formatMeAction($comment)
    {
        if (!isMeAction($comment) || !c("Garden.Format.MeActions")) {
            return;
        }

        // Maxlength (don't let people blow up the forum)
        $comment->Body = substr($comment->Body, 4);
        $maxlength = c("Vanilla.MeAction.MaxLength", 100);
        $body = formatBody($comment);
        if (strlen($body) > $maxlength) {
            $body = substr($body, 0, $maxlength) . "...";
        }

        return '<div class="AuthorAction">' . $body . "</div>";
    }
endif;
