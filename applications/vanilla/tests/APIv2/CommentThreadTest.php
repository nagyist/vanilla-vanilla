<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

namespace VanillaTests\APIv2;

use CommentModel;
use Garden\Container\ContainerException;
use Garden\Container\NotFoundException;
use Garden\Schema\ValidationException;
use Vanilla\Forum\Widgets\PostCommentThreadAsset;
use Vanilla\Utility\ModelUtils;
use VanillaTests\Forum\ExpectedThreadStructure;
use VanillaTests\Forum\Utils\CommunityApiTestTrait;
use VanillaTests\SiteTestCase;
use VanillaTests\UsersAndRolesApiTestTrait;

/**
 * Tests for threaded comments.
 */
class CommentThreadTest extends SiteTestCase
{
    use CommunityApiTestTrait;
    use UsersAndRolesApiTestTrait;

    private CommentModel $commentModel;

    /**
     * @inheridoc
     */
    public function setUp(): void
    {
        $this->commentModel = $this->container()->get(CommentModel::class);
        $this->enableFeature("customLayout.post");
        parent::setUp();
    }

    /**
     * Test a simple thread structure.
     *
     * @return void
     */
    public function testSimpleThread()
    {
        $discussion = $this->createDiscussion();
        $comment1 = $this->createComment();
        $comment1_1 = $this->createNestedComment($comment1);
        $comment1_2 = $this->createNestedComment($comment1);
        $comment1_2_1 = $this->createNestedComment($comment1_2);
        // This one from a different user.
        $member = $this->createUser();
        $comment1_2_1_1 = $this->runWithUser(function () use ($comment1_2_1) {
            return $this->createNestedComment($comment1_2_1);
        }, $member);
        $comment1_3 = $this->createNestedComment($comment1);
        $comment1_4 = $this->createNestedComment($comment1);
        $comment1_5 = $this->createNestedComment($comment1);
        $comment2 = $this->createComment();

        $thread = $this->api()
            ->get("/comments/thread", [
                "parentRecordType" => "discussion",
                "parentRecordID" => $discussion["discussionID"],
            ])
            ->getBody();

        $expected = ExpectedThreadStructure::create()
            ->comment(
                $comment1,
                ExpectedThreadStructure::create()
                    ->comment($comment1_1)
                    ->comment($comment1_2, ExpectedThreadStructure::create()->hole($comment1_2, 2, 2))
                    ->comment($comment1_3)
                    ->hole($comment1, 2, 1)
            )
            ->comment($comment2);

        $expected->assertMatches($thread["threadStructure"]);

        // Assert that we have our preloaded comments.
        $this->assertRowsLike(
            [
                "commentID" => [
                    $comment1["commentID"],
                    $comment1_1["commentID"],
                    $comment1_2["commentID"],
                    $comment1_3["commentID"],
                    $comment2["commentID"],
                ],
            ],
            $thread["commentsByID"],
            strictOrder: false
        );
    }

    /**
     * Test that we create a "virtual" hole for the rest of our pages when querying by parentCommentID.
     *
     * @return void
     */
    public function testNestedQueryHole(): void
    {
        $discussion = $this->createDiscussion();
        $comment1 = $this->createComment();
        $comment1_1 = $this->createNestedComment($comment1);
        $comment1_2 = $this->createNestedComment($comment1);
        $comment1_2_1 = $this->createNestedComment($comment1_2);
        $comment1_3 = $this->createNestedComment($comment1);
        $comment1_4 = $this->createNestedComment($comment1);

        $thread = $this->api()
            ->get("/comments/thread", [
                "parentRecordType" => "discussion",
                "parentRecordID" => $discussion["discussionID"],
                "parentCommentID" => $comment1["commentID"],
                "page" => 2,
                "limit" => 1,
            ])
            ->getBody();

        $expected = ExpectedThreadStructure::create(2) // Initial depth is 2 because we queried a parentCommentID.
            ->comment($comment1_2, ExpectedThreadStructure::create()->comment($comment1_2_1))
            // Notably top level hole here.
            ->hole($comment1, 2, 1, offset: 2);

        $expected->assertMatches($thread["threadStructure"]);

        // Make sure we've made apiUrls for our holes.
        $hole = $thread["threadStructure"][1];
        $expectedUrl = url(
            "/api/v2/comments/thread?parentRecordType=discussion&parentRecordID={$discussion["discussionID"]}&parentCommentID={$comment1["commentID"]}&sort=dateInserted&page=3&limit=1&expand%5B0%5D=insertUser&expand%5B1%5D=body",
            true
        );
        $this->assertEquals($expectedUrl, $hole["apiUrl"]);
    }

    /**
     * Test that comments may only be posted into the correct thread.
     *
     * @return void
     */
    public function testPostIntoWrongThread(): void
    {
        $disc1 = $this->createDiscussion();
        $comment1 = $this->createComment();
        $disc2 = $this->createDiscussion();

        $this->expectExceptionMessage("Parent comment is from a different thread.");
        $this->createNestedComment($comment1, [
            "parentRecordType" => "discussion",
            "parentRecordID" => $disc2["discussionID"],
        ]);
    }

    /**
     * Test that comments may not be posted beyond our configured maximum depth.
     *
     * @return void
     */
    public function testCommentExceedsMaxDepthNoCustomLayouts(): void
    {
        // Comments can't be nested because custom layouts is disabled.
        $this->runWithConfig(["Feature.customLayout.post.Enabled" => false], function () {
            $discussion = $this->createDiscussion();
            $comment1 = $this->createComment();
            $this->expectExceptionMessage("Parent comments are not allowed without custom discussion threads.");
            $comment1_1 = $this->createNestedComment($comment1);
        });
    }

    /**
     * Test the focusCommentID and collapseChild options.
     *
     * @return void
     */
    public function testFocusCommentID(): void
    {
        // We will use collapseChildLimit 2 and collapseChildDepth 3.

        $discussion = $this->createDiscussion();
        $comment1 = $this->createComment();
        $comment1_1 = $this->createNestedComment($comment1);
        $comment1_2 = $this->createNestedComment($comment1);
        // Normally these would be collapsed.
        $comment1_3 = $this->createNestedComment($comment1);
        $comment1_4 = $this->createNestedComment($comment1);
        $comment1_5 = $this->createNestedComment($comment1);
        // Normally this would definitely be collapsed.
        $comment1_5_1 = $this->createNestedComment($comment1_5);
        $comment1_6 = $this->createNestedComment($comment1);
        $comment1_7 = $this->createNestedComment($comment1);

        $thread = $this->api()
            ->get("/comments/thread", [
                "parentRecordType" => "discussion",
                "parentRecordID" => $discussion["discussionID"],
                "focusCommentID" => $comment1_5_1["commentID"],
                "collapseChildLimit" => 2,
                "collapseChildDepth" => 3,
            ])
            ->getBody();

        $expected = ExpectedThreadStructure::create() // Initial depth is 2 because we queried a parentCommentID.
            ->comment(
                $comment1,
                ExpectedThreadStructure::create()
                    ->comment($comment1_1)
                    ->comment($comment1_2)
                    // 1_3 and 1_4 in this hole.
                    ->hole($comment1, 2, 1)
                    // because it's focused.
                    ->comment($comment1_5, ExpectedThreadStructure::create()->comment($comment1_5_1))
                    // 1_6 and 1_7
                    ->hole($comment1, 2, 1)
            );

        $expected->assertMatches($thread["threadStructure"]);
    }

    /**
     * Test that parent aggregates are calculated and scored appropriately.
     *
     * @return void
     */
    public function testParentAggregates()
    {
        $discussion = $this->createDiscussion();
        $comment1 = $this->createComment();
        $comment1_1 = $this->createNestedComment($comment1);
        $comment1_1_1 = $this->createNestedComment($comment1_1);
        $comment1_2 = $this->createNestedComment($comment1);

        $this->reactComment($comment1_1, "like");
        $this->reactComment($comment1_1_1, "like");

        $comments = $this->api()
            ->get("/comments", ["discussionID" => $discussion["discussionID"], "sort" => "commentID"])
            ->getBody();
        $this->assertRowsLike(
            [
                "commentID" => [
                    $comment1["commentID"],
                    $comment1_1["commentID"],
                    $comment1_1_1["commentID"],
                    $comment1_2["commentID"],
                ],
                "depth" => [1, 2, 3, 2],
                "score" => [0, 1, 1, 0],
                "scoreChildComments" => [2, 1, 0, 0],
                "countChildComments" => [3, 1, 0, 0],
            ],
            $comments
        );

        // Make sure we can reverse a reaction
        $this->reactComment($comment1_1, "Undo-like");
        $comments = $this->api()
            ->get("/comments", ["discussionID" => $discussion["discussionID"], "sort" => "commentID"])
            ->getBody();
        $this->assertRowsLike(
            [
                "commentID" => [
                    $comment1["commentID"],
                    $comment1_1["commentID"],
                    $comment1_1_1["commentID"],
                    $comment1_2["commentID"],
                ],
                "score" => [0, 0, 1, 0],
                "scoreChildComments" => [1, 1, 0, 0],
            ],
            $comments
        );

        // Deleting a comment updates aggregates
        $this->api()->delete("/comments/{$comment1_1_1["commentID"]}");

        $comments = $this->api()
            ->get("/comments", ["discussionID" => $discussion["discussionID"]])
            ->getBody();
        $this->assertRowsLike(
            [
                "commentID" => [$comment1["commentID"], $comment1_1["commentID"], $comment1_2["commentID"]],
                "depth" => [1, 2, 2],
                "score" => [0, 0, 0],
                "scoreChildComments" => [0, 0, 0],
                "countChildComments" => [2, 0, 0],
            ],
            $comments
        );
    }

    /**
     * Test the trending sort on a comment thread.
     *
     * @return void
     */
    public function testTrendingSort()
    {
        $discussion = $this->createDiscussion();
        $comment1 = $this->createComment();
        $comment2 = $this->createComment();
        $comment2_1 = $this->createNestedComment($comment2);
        $comment2_1_1 = $this->createNestedComment($comment2_1);
        $comment2_2 = $this->createNestedComment($comment2);
        $comment3 = $this->createComment();

        $this->reactComment($comment2_1, "like");
        $this->reactComment($comment1, "like");

        $thread = $this->api()
            ->get("/comments/thread", [
                "parentRecordType" => "discussion",
                "parentRecordID" => $discussion["discussionID"],
                "sort" => "-" . ModelUtils::SORT_TRENDING,
            ])
            ->getBody();

        $expected = ExpectedThreadStructure::create()
            ->comment(
                $comment2,
                ExpectedThreadStructure::create()
                    ->comment($comment2_1, ExpectedThreadStructure::create()->hole($comment2_1, 1, 1))
                    ->comment($comment2_2)
            )
            ->comment($comment1)
            ->comment($comment3);

        $expected->assertMatches($thread["threadStructure"]);
    }

    /**
     * Test that the Parent Comment is replaced by a quote when calling `/api/v2/comments` or `/api/v2/comments/{id}`.
     *
     * @return void
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ValidationException
     */
    public function testThreadCommentApiFallback(): void
    {
        $this->createDiscussion();
        $parentComment = $this->createComment(["body" => "Parent Comment"]);
        $childComment = $this->createComment([
            "parentCommentID" => $parentComment["commentID"],
            "body" => "Child Comment",
        ]);

        $results = $this->api()
            ->get("/comments", ["discussionID" => $childComment["discussionID"]])
            ->getBody();
        $this->assertStringContainsString(
            $parentComment["body"],
            $results[1]["body"],
            "Parent Comment should be quoted in the child comment."
        );

        $result = $this->api()
            ->get("/comments/{$childComment["commentID"]}")
            ->getBody();
        $this->assertStringContainsString(
            $parentComment["body"],
            $result["body"],
            "Parent Comment should be quoted in the child comment."
        );

        // Test with quoteParent set to false
        $results = $this->api()
            ->get("/comments", ["discussionID" => $childComment["discussionID"], "quoteParent" => false])
            ->getBody();
        $this->assertEquals("Child Comment", $results[1]["body"]);

        $result = $this->api()
            ->get("/comments/{$childComment["commentID"]}", ["quoteParent" => false])
            ->getBody();
        $this->assertEquals("Child Comment", $result["body"]);
    }

    /**
     * Test that the nothing happen when the Parent Comment is not a valid CommentID.
     *
     * @return void
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ValidationException
     */
    public function testThreadCommentApiFallbackInvalidCommentID(): void
    {
        $this->createDiscussion();
        $parentComment = $this->createComment(["body" => "Parent Comment"]);
        $childComment = $this->createComment([
            "parentCommentID" => $parentComment["commentID"],
            "body" => "Child Comment",
        ]);
        $this->api()->delete("/comments/{$parentComment["commentID"]}");

        // Test for `/api/v2/comments`
        $results = $this->api()
            ->get("/comments", ["discussionID" => $this->lastInsertedDiscussionID])
            ->getBody();

        $this->assertEquals($childComment["body"], $results[0]["body"]);

        // Test for `/api/v2/comments/{id}`
        $result = $this->api()
            ->get("/comments/{$childComment["commentID"]}")
            ->getBody();
        $this->assertEquals($childComment["body"], $result["body"]);
    }

    /**
     * Test that the legacy view has quote the parentComment.
     *
     * @return void
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ValidationException
     */
    public function testLegacyControllerFallback(): void
    {
        $this->createDiscussion();
        $firstComment = $this->createComment(["body" => "Parent Comment old"]);
        $this->createComment(["body" => "Parent Comment old1"]);
        $this->createComment(["body" => "Parent Comment old2 to get to page 2"]);
        $parentComment = $this->createComment(["body" => "Parent Comment"]);
        $childComment = $this->createComment([
            "parentCommentID" => $parentComment["commentID"],
            "body" => "Child Comment",
        ]);

        $this->runWithConfig(["Feature.customLayout.post.Enabled" => false], function () use (
            $firstComment,
            $parentComment
        ) {
            // Get the view to set things up.
            $view = $this->bessy()
                ->get("/discussion/comment/{$firstComment["commentID"]}")
                ->fetchView();

            $comment = $this->commentModel->getID($firstComment["commentID"]);
            $body = formatBody($comment);
            $this->assertStringContainsString(
                $parentComment["body"],
                $body,
                "Parent Comment should be quoted in the child comment."
            );
            $this->assertStringContainsString($body, $view, "Parent is not quoted in the views.");
        });
    }

    /**
     * Test that the nothing happen when the Parent Comment is not a valid CommentID.
     *
     * @return void
     * @throws ContainerException
     * @throws NotFoundException
     * @throws ValidationException
     */
    public function testLegacyControllerFallbackInvalidCommentID(): void
    {
        $this->createDiscussion();
        $parentComment = $this->createComment(["body" => "Parent Comment"]);
        $childComment = $this->createComment([
            "parentCommentID" => $parentComment["commentID"],
            "body" => "Child Comment",
        ]);
        $this->api()->delete("/comments/{$parentComment["commentID"]}");

        $this->runWithConfig(["Feature.customLayout.post.Enabled" => false], function () use (
            $childComment,
            $parentComment
        ) {
            // Get the view to set things up.
            $this->bessy()
                ->get("/discussion/comment/{$childComment["commentID"]}")
                ->fetchView();

            $comment = $this->commentModel->getID($childComment["commentID"]);
            $body = formatBody($comment);
            $this->assertStringNotContainsString(
                $parentComment["body"],
                $body,
                "Parent Comment should not be quoted in the child comment."
            );
        });
    }

    /**
     * Make sure the API quotes the parent comment if we fetch a flat thread.
     *
     * @return void
     */
    public function testNoQuoteWithThreadedComment(): void
    {
        $this->createDiscussion();
        $parentComment = $this->createComment(["body" => "Parent Comment"]);
        $childComment = $this->createComment([
            "parentCommentID" => $parentComment["commentID"],
            "body" => "Child Comment",
        ]);

        $this->runWithConfig(["Feature.customLayout.post.Enabled" => false], function () use (
            $childComment,
            $parentComment
        ) {
            $results = $this->api()
                ->get("/comments", ["discussionID" => $childComment["discussionID"]])
                ->getBody();
            $this->assertStringContainsString($parentComment["url"], $results[1]["body"]);

            $result = $this->api()
                ->get("/comments/{$childComment["commentID"]}")
                ->getBody();
            $this->assertStringContainsString($parentComment["url"], $result["body"]);
        });
    }

    /**
     * Test that maxDepth on layout is enforced with posting a new comment.
     *
     * @return void
     */
    public function testCommentExceedsMaxDepthSetInLayout(): void
    {
        $layout = $this->api()
            ->post("/layouts", [
                "layoutViewType" => "discussion",
                "name" => "My custom layout",
                "layout" => [
                    [
                        "\$hydrate" => "react." . PostCommentThreadAsset::getWidgetID(),
                        "apiParams" => [
                            "maxDepth" => 2,
                        ],
                    ],
                ],
            ])
            ->getBody();

        $this->api()->put("/layouts/{$layout["layoutID"]}/views", [
            [
                "recordType" => "global",
                "recordID" => -1,
            ],
        ]);

        $discussion = $this->createDiscussion();
        $comment1 = $this->createComment();
        $comment1_1 = $this->createNestedComment($comment1);
        $this->expectExceptionMessage("Comment exceeds maximum depth.");
        $comment1_1 = $this->createNestedComment($comment1_1);
    }
}
