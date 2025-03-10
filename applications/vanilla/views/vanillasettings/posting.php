<?php if (!defined("APPLICATION")) {
    exit();
}
helpAsset(
    t("Need More Help?"),
    anchor(
        t("Video tutorial on advanced settings"),
        "https://success.vanillaforums.com/kb/articles/451-h-posting-video"
    )
);
?>
<h1><?php echo t("Posting Settings"); ?></h1>

<?php
/** @var Gdn_Form $form */
$form = $this->Form;
/** @var \Garden\EventManager $eventManager */
$eventManager = Gdn::getContainer()->get(\Garden\EventManager::class);
$formats = $this->data("formats");
$formatOptions = [];
foreach ($formats as $altFormatKey => $formatName) {
    if (is_numeric($altFormatKey)) {
        // Key and value are the same
        $formatOptions[$formatName] = $formatName;
    } else {
        // Key and value are different.
        $formatOptions[$altFormatKey] = $formatName;
    }
}
echo $form->open();
echo $form->errors();
?>
<div class="form-group">
    <?php
    $checkboxDesc =
        "Checkboxes allow admins to perform batch actions on a number of posts or comments at the same time.";
    echo $form->toggle(
        "Vanilla.AdminCheckboxes.Use",
        "Enable checkboxes on posts and comments",
        [],
        $checkboxDesc
    );
    ?>
</div>

<h2 class="subheading"><?php echo t("Formats"); ?></h2>
<div class="form-group">
    <?php
    $formatNotes1 = t("InputFormatter.Notes1", "Select the default format of the editor for posts in the community.");
    $formatNotes2 = t(
        "InputFormatter.Notes2",
        'The editor will auto-detect the format of old posts when editing them and load their
            original formatting rules. Aside from this exception, the selected post format below will take precedence.'
    );
    $label =
        '<p class="info">' .
        $formatNotes1 .
        '</p><p class="info"><strong>' .
        t("Note:") .
        " </strong>" .
        $formatNotes2 .
        "</p>";
    ?>
    <div class="label-wrap">
        <?php
        echo $form->label("Post Format", "Garden.InputFormatter");
        echo $label;
        ?>
    </div>
    <div class="input-wrap">
        <?php echo $form->dropDown("Garden.InputFormatter", $formatOptions); ?>
    </div>
</div>

<?php echo $this->data("extraFormatFormHTML"); ?>

<div class="form-group">
    <?php
    $mobileFormatterNote1 = t("MobileInputFormatter.Notes1", "Specify an editing format for mobile devices.");
    $mobileFormatterNote2 = t(
        "MobileInputFormatter.Notes2",
        'If mobile devices should have the same experience,
    specify the same one as above. If users report issues with mobile editing, this is a good option to change.'
    );
    $label =
        '<p class="info">' .
        $mobileFormatterNote1 .
        '</p><p class="info"><strong>' .
        t("Note:") .
        " </strong>" .
        $mobileFormatterNote2 .
        "</p>";
    ?>
    <div class="label-wrap">
        <?php
        echo $form->label("Mobile Format", "Garden.MobileInputFormatter");
        echo '<p class="info">' . $label . "</p>";
        ?>
    </div>
    <div class="input-wrap">
        <?php echo $form->dropDown("Garden.MobileInputFormatter", $formatOptions); ?>
    </div>
</div>

<h2 class="subheading"><?php echo t("Embeds"); ?></h2>

<div class="form-group">
    <?php
    $embedsLabel = "Enable link embeds in posts and comments";
    $embedsDesc = "@" . t("Allow links to be transformed");
    echo $form->toggle("Garden.Format.DisableUrlEmbeds", $embedsLabel, [], $embedsDesc, true);
    ?>
</div>

<div class="form-group">
    <?php
    $imageUploadLimitLabel = t("ImageUploadLimits.Notes1", "Enable Image Upload Limit");
    $ImageUploadDesc = t("ImageUploadLimits.Notes2", "Add limits to image upload dimensions.");
    echo $form->toggle("ImageUpload.Limits.Enabled", $imageUploadLimitLabel, [], $ImageUploadDesc, false);
    ?>
</div>

<div class="form-group ImageUploadLimitsDimensions dimensionsDisabled">
    <div class="label-wrap-wide">
        <?php
        echo $form->label(t("Max Image Width"), "ImageUpload.Limits.Width");
        echo wrap(t("ImageUploadLimits.Width2", "Images will be scaled down if they exceed this width."), "div", [
            "class" => "info",
        ]);
        ?>
    </div>
    <div class="input-wrap-right">
        <div class="textbox-suffix" data-suffix="px">
            <?php echo $form->textBox("ImageUpload.Limits.Width", [
                "class" => "form-control",
                "value" => c("ImageUpload.Limits.Width", 1400),
            ]); ?>
        </div>
    </div>
</div>
<div class="form-group ImageUploadLimitsDimensions dimensionsDisabled">
    <div class="label-wrap-wide">
        <?php
        echo $form->label(t("Max Image Height"), "ImageUpload.Limits.Height");
        echo wrap(t("ImageUploadLimits.Height2", "Images will be scaled down if they exceed this height."), "div", [
            "class" => "info",
        ]);
        ?>
    </div>
    <div class="input-wrap-right">
        <div class="textbox-suffix" data-suffix="px">
            <?php echo $form->textBox("ImageUpload.Limits.Height", [
                "class" => "form-control",
                "value" => c("ImageUpload.Limits.Height", 1000),
            ]); ?>
        </div>
    </div>
</div>
<div class="form-group">
    <div class="label-wrap">
        <?php
        echo $form->label(t("Configure custom Kaltura domains"), \VanillaSettingsController::CONFIG_KALTURA_DOMAINS);
        echo wrap(
            wrap(
                t(
                    "Add your custom Kaltura domain(s) to transform links into embedded videos in posts, comments or articles."
                ),
                "p"
            ) . wrap(t("Specify one domain per line. Use * for wildcard matches."), "p"),
            "div",
            ["class" => "info"]
        );
        ?>
    </div>
    <div class="input-wrap">
        <?php echo $form->textBox(\VanillaSettingsController::CONFIG_KALTURA_DOMAINS, [
            "implode" => "\n",
            "MultiLine" => true,
            "class" => "InputBox SmallInput",
        ]); ?>
    </div>
</div>
<h2 class="subheading"><?php echo t("Appearance"); ?></h2>
<div class="form-group">
    <?php
    $Options = ["1" => "1", "2" => "2", "3" => "3", "4" => "4", "5" => "5", "0" => "No limit"];
    $Fields = ["TextField" => "Code", "ValueField" => "Code"];
    ?>
    <div class="label-wrap">
        <?php
        echo $form->label("Maximum Category Display Depth", "Vanilla.Categories.MaxDisplayDepth");
        echo wrap(t("Nested categories deeper than this depth will be placed in a comma-delimited list."), "div", [
            "class" => "info",
        ]);
        ?>
    </div>
    <div class="input-wrap">
        <?php echo $form->dropDown("Vanilla.Categories.MaxDisplayDepth", $Options, $Fields); ?>
    </div>
</div>
<div class="form-group">
    <?php
    $Options = [
        "10" => "10",
        "15" => "15",
        "20" => "20",
        "25" => "25",
        "30" => "30",
        "40" => "40",
        "50" => "50",
        "100" => "100",
    ];
    $Fields = ["TextField" => "Code", "ValueField" => "Code"];
    ?>
    <div class="label-wrap">
        <?php
        echo $form->label("Posts per Page", "Vanilla.Discussions.PerPage");
        echo wrap(sprintft("Number of %s listed per page.", t("Posts")), "div", ["class" => "info"]);
        ?>
    </div>
    <div class="input-wrap">
        <?php echo $form->dropDown("Vanilla.Discussions.PerPage", $Options, $Fields); ?>
    </div>
</div>
<div class="form-group">
    <div class="label-wrap">
        <?php
        echo $form->label("Comments per Page", "Vanilla.Comments.PerPage");
        echo wrap(sprintft("Number of %s listed per page.", t("Comments")), "div", ["class" => "info"]);
        ?>
    </div>
    <div class="input-wrap">
        <?php echo $form->dropDown("Vanilla.Comments.PerPage", $Options, $Fields); ?>
    </div>
</div>

<h2 class="subheading"><?php echo t("Rules"); ?></h2>
<div class="form-group">
    <?php
    $Options = [
        "0" => t("Authors may never edit"),
        "350" => sprintf(t("Authors may edit for %s"), t("5 minutes")),
        "900" => sprintf(t("Authors may edit for %s"), t("15 minutes")),
        "3600" => sprintf(t("Authors may edit for %s"), t("1 hour")),
        "14400" => sprintf(t("Authors may edit for %s"), t("4 hours")),
        "86400" => sprintf(t("Authors may edit for %s"), t("1 day")),
        "604800" => sprintf(t("Authors may edit for %s"), t("1 week")),
        "2592000" => sprintf(t("Authors may edit for %s"), t("1 month")),
        "-1" => t("Authors may always edit"),
    ];
    $Fields = ["TextField" => "Text", "ValueField" => "Code"];
    ?>
    <div class="label-wrap">
        <?php
        echo $form->label("Post & Comment Editing", "Garden.EditContentTimeout");
        echo wrap(
            t(
                "EditContentTimeout.Notes",
                "If a user is in a role that has permission to edit content, those permissions will override this."
            ),
            "div",
            ["class" => "info"]
        );
        ?>
    </div>
    <div class="input-wrap">
        <?php echo $form->dropDown("Garden.EditContentTimeout", $Options, $Fields); ?>
    </div>
</div>
<div class="form-group">
    <?php
    $autosaveDescription =
        t("Automatically save drafts of unpublished posts, questions, ideas and comments.") .
        " " .
        anchor("Learn more", "https://success.vanillaforums.com/kb/articles/199-saving-drafts") .
        ".";
    echo $form->toggle("Vanilla.Drafts.Autosave", "Automatically Save Drafts", [], $autosaveDescription);
    ?>
</div>
<div class="form-group">
    <div class="label-wrap">
        <?php echo $form->label("Max Post Length", "Vanilla.Comment.MaxLength"); ?>
        <div class="info"><?php echo t(
            "It is a good idea to keep the maximum number of characters allowed in a post down to a reasonable size."
        ); ?></div>
    </div>
    <div class="input-wrap">
        <?php echo $form->textBox("Vanilla.Comment.MaxLength", ["class" => "InputBox SmallInput"]); ?>
    </div>
</div>
<div class="form-group">
    <div class="label-wrap">
        <?php echo $form->label("Min Post Length", "Vanilla.Comment.MinLength"); ?>
        <div class="info"><?php echo t("You can specify a minimum post length to discourage short posts."); ?></div>
    </div>
    <div class="input-wrap">
        <?php echo $form->textBox("Vanilla.Comment.MinLength", ["class" => "InputBox SmallInput"]); ?>
    </div>
</div>
<?php echo $form->close("Save"); ?>
