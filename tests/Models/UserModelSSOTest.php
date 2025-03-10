<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

namespace VanillaTests\Models;

use Vanilla\CurrentTimeStamp;
use VanillaTests\AuditLogTestTrait;
use VanillaTests\Bootstrap;
use VanillaTests\ExpectedAuditLog;
use VanillaTests\SiteTestCase;
use VanillaTests\VanillaTestCase;

/**
 * Test some SSO functions in the `UserModel`.
 */
class UserModelSSOTest extends SiteTestCase
{
    const PROVIDER_KEY = "UserModelSSOTest";

    use AuditLogTestTrait;

    /**
     * @var \Gdn_AuthenticationProviderModel
     */
    private $authModel;

    /**
     * @inheritDoc
     */
    public function setup(): void
    {
        parent::setUp();

        $this->container()->call(function (\Gdn_AuthenticationProviderModel $authModel) {
            $this->authModel = $authModel;
        });

        $this->authModel->delete([\Gdn_AuthenticationProviderModel::COLUMN_KEY => self::PROVIDER_KEY]);
        $this->authModel->save(
            [
                \Gdn_AuthenticationProviderModel::COLUMN_KEY => self::PROVIDER_KEY,
                \Gdn_AuthenticationProviderModel::COLUMN_ALIAS => self::PROVIDER_KEY,
                "AssociationSecret" => self::PROVIDER_KEY,
                "Name" => self::PROVIDER_KEY,
                "Trusted" => true,
            ],
            ["checkExisting" => true]
        );
    }

    /**
     * Test a basic connect.
     */
    public function testBasicConnect(): void
    {
        $user = $this->dummyUser();

        $id = $this->userModel->connect(__FUNCTION__, self::PROVIDER_KEY, $user);
        $this->assertGreaterThan(0, $id);

        $dbUser = $this->userModel->getID($id, DATASET_TYPE_ARRAY);
        $this->assertArraySubsetRecursive($user, $dbUser);

        $this->assertSame($id, $this->userModel->getAuthentication(__FUNCTION__, self::PROVIDER_KEY)["UserID"]);
    }

    /**
     * Test a re-connect with a user sync.
     */
    public function testConnectSync(): void
    {
        $user = $this->dummyUser();

        $id = $this->userModel->connect(__FUNCTION__, self::PROVIDER_KEY, $user);

        $user["Name"] = __FUNCTION__;
        $id2 = $this->userModel->connect(__FUNCTION__, self::PROVIDER_KEY, $user);
        $this->assertSame($id, $id2);
        $dbUser = $this->userModel->getID($id, DATASET_TYPE_ARRAY);
        $this->assertArraySubsetRecursive($user, $dbUser);
    }

    /**
     * Test re-connect with just the unique ID (no name or email).
     */
    public function testReconnectWithJustUniqueID(): void
    {
        $user = $this->dummyUser();

        $userID = $this->userModel->connect(__FUNCTION__, self::PROVIDER_KEY, $user);
        $this->assertGreaterThan(0, $userID);

        $userID2 = $this->userModel->connect(__FUNCTION__, self::PROVIDER_KEY, [
            "Photo" => "https://example.com/foo.png",
        ]);
        $this->assertSame($userID, $userID2);

        $userID3 = $this->userModel->connect(__FUNCTION__, self::PROVIDER_KEY, []);
        $this->assertSame($userID, $userID3);
    }

    /**
     * I should be able to give an array of roles when connecting.
     *
     * @param string|array $roles
     * @dataProvider provideConnectRoleTests
     */
    public function testConnectRoles($roles): void
    {
        $user = $this->dummyUser(["Roles" => $roles]);

        $id = $this->userModel->connect(__FUNCTION__, self::PROVIDER_KEY, $user);
        $this->assertSame(
            [$this->roleID(Bootstrap::ROLE_ADMIN), $this->roleID(Bootstrap::ROLE_MOD)],
            $this->userModel->getRoleIDs($id)
        );
    }

    /**
     * The role sync option should work through `UserModel::connect()`.
     */
    public function testConnectRoleSync(): void
    {
        $roleID = $this->defineRole(["Name" => "SSO1", "Sync" => "sso"]);
        $user = $this->dummyUser(["Password" => __FUNCTION__]);
        $this->saveUser($user, __FUNCTION__);

        $user["Roles"] = ["SSO1"];
        $id = $this->userModel->connect(__FUNCTION__, self::PROVIDER_KEY, $user, [
            \UserModel::OPT_ROLE_SYNC => ["sso"],
        ]);
        $this->assertSame([$this->roleID(Bootstrap::ROLE_MEMBER), $roleID], $this->userModel->getRoleIDs($id));
    }

    /**
     * The role sync option should work through `UserModel::connect()` with Garden.Registration.SSOConfirmEmail set to true.
     */
    public function testConnectUnconfirmedRoleSync(): void
    {
        $this->runWithConfig(["Garden.Registration.SSOConfirmEmail" => true], function () {
            $roleID = $this->defineRole(["Name" => "SSO1", "Sync" => "sso"]);
            $user = $this->dummyUser(["Password" => __FUNCTION__]);
            $this->saveUser($user, __FUNCTION__);

            $user["Roles"] = ["SSO1"];
            $id = $this->userModel->connect(__FUNCTION__, self::PROVIDER_KEY, $user, [
                \UserModel::OPT_ROLE_SYNC => ["sso"],
            ]);
            $this->assertSame(
                [
                    $this->roleID(VanillaTestCase::ROLE_UNCONFIRMED),
                    $this->roleID(VanillaTestCase::ROLE_MEMBER),
                    $roleID,
                ],
                $this->userModel->getRoleIDs($id)
            );
        });
    }

    /**
     * The role sync option should work through `UserModel::sso()`.
     */
    public function testSSOConnect(): void
    {
        $roleID = $this->defineRole(["Name" => "SSO1", "Sync" => "sso"]);
        $user = $this->dummyUser(["Password" => __FUNCTION__]);
        $this->saveUser($user, __FUNCTION__);

        try {
            $bak = $this->userModel->getConnectRoleSync();
            $this->userModel->setConnectRoleSync(["sso"]);

            $user["roles"] = ["SSO1"];
            $id = $this->ssoByString($user, __FUNCTION__);

            $this->assertSame([$this->roleID(Bootstrap::ROLE_MEMBER), $roleID], $this->userModel->getRoleIDs($id));
        } finally {
            $this->userModel->setConnectRoleSync($bak);
        }
    }

    /**
     * Data provider.
     *
     * @return array
     */
    public function provideConnectRoleTests(): array
    {
        $r = [
            "array" => [[Bootstrap::ROLE_ADMIN, Bootstrap::ROLE_MOD]],
            "csv" => [Bootstrap::ROLE_ADMIN . "," . Bootstrap::ROLE_MOD],
            "csv space" => [Bootstrap::ROLE_ADMIN . ", " . Bootstrap::ROLE_MOD],
        ];
        return $r;
    }

    /**
     * Test auto-connect on email.
     */
    public function testAutoConnect(): void
    {
        $func = __FUNCTION__;

        $this->runWithConfig(["Garden.SSO.AutoConnect" => true], function () use ($func) {
            $id = $this->createUserFixture(Bootstrap::ROLE_MEMBER, $func);
            $member = $this->userModel->getID($id, DATASET_TYPE_ARRAY);

            $ssoUser = [
                "Name" => $member["Name"] . "SSO",
                "Email" => $member["Email"],
            ];

            $id2 = $this->userModel->connect($func, self::PROVIDER_KEY, $ssoUser);
            $this->assertSame($id, $id2);
            $dbUser = $this->userModel->getID($id2, DATASET_TYPE_ARRAY);
            $this->assertArraySubsetRecursive($ssoUser, $dbUser);
        });
    }

    /**
     * An empty unique ID is a security risk so should not be connectable.
     */
    public function testEmptyUniqueID(): void
    {
        $id = $this->userModel->connect("", self::PROVIDER_KEY, $this->dummyUser());
        $this->assertFalse($id);
        $this->assertStringContainsString("UniqueID is required.", $this->userModel->Validation->resultsText());
    }

    /**
     * Test lookupSSOUser to see if it would find the user in the User table.
     */
    public function testLookupSSOUserUser(): void
    {
        $func = __FUNCTION__;
        $this->runWithConfig(
            ["Garden.Registration.EmailUnique" => true, "Garden.Registration.AutoConnect" => true],
            function () use ($func) {
                $id = $this->createUserFixture(Bootstrap::ROLE_MEMBER, $func);
                $ssoData = $this->dummyUser(["UniqueID" => 2, "Provider" => self::PROVIDER_KEY]);
                $this->userModel->update(["Email" => $ssoData["Email"]], ["UserID" => $id]);
                $user = $this->userModel->lookupSSOUser($ssoData, self::PROVIDER_KEY);
                $this->assertIsArray($user);
                $this->assertArraySubsetRecursive(["UserID" => $id, "Email" => $ssoData["Email"]], $user);
            }
        );
    }

    /**
     * Test lookupSSOUser to see that it would find the user in the UserAuthentication table.
     */
    public function testLookupSSOAuthUser(): void
    {
        $func = __FUNCTION__;
        // Look up a user that exists in UserAuthentication table only.
        $this->runWithConfig(
            ["Garden.Registration.EmailUnique" => true, "Garden.Registration.AutoConnect" => true],
            function () use ($func) {
                $id = $this->createUserFixture(Bootstrap::ROLE_MEMBER, $func);
                $ssoData = $this->dummyUser(["UniqueID" => 1, "Provider" => self::PROVIDER_KEY]);
                $this->userModel->saveAuthentication([
                    "UserID" => $id,
                    "UniqueID" => 1,
                    "Provider" => self::PROVIDER_KEY,
                ]);
                $user = $this->userModel->lookupSSOUser($ssoData, self::PROVIDER_KEY);
                $this->assertIsArray($user);
                $this->assertArraySubsetRecursive(["UserID" => $id], $user);
            }
        );
    }

    /**
     * Test lookupSSOUser to see that it would find the user in the User table.
     *  when Garden.Registration.EmailUnique is set to false.
     */
    public function testLookupSSOUserEmailNotUnique(): void
    {
        $func = __FUNCTION__;
        // Look up a user that exists in UserAuthentication table only.
        $this->runWithConfig(
            ["Garden.Registration.EmailUnique" => false, "Garden.Registration.AutoConnect" => true],
            function () use ($func) {
                $id = $this->createUserFixture(Bootstrap::ROLE_MEMBER, $func);
                $ssoData = $this->dummyUser(["UniqueID" => 2, "Provider" => self::PROVIDER_KEY]);
                $this->userModel->update(["Email" => $ssoData["Email"]], ["UserID" => $id]);
                $user = $this->userModel->lookupSSOUser($ssoData, self::PROVIDER_KEY);
                $this->assertIsNotArray($user);
            }
        );
    }

    /**
     * Test lookupSSOUser to see that it would find the user in the User table
     *  when Garden.Registration.AutoConnect is set to false.
     */
    public function testLookupSSOUserNoAutoConnect(): void
    {
        $func = __FUNCTION__;
        // Look up a user that exists in UserAuthentication table only.
        $this->runWithConfig(
            ["Garden.Registration.EmailUnique" => true, "Garden.Registration.AutoConnect" => false],
            function () use ($func) {
                $id = $this->createUserFixture(Bootstrap::ROLE_MEMBER, $func);
                $ssoData = $this->dummyUser(["UniqueID" => 2, "Provider" => self::PROVIDER_KEY]);
                $this->userModel->update(["Email" => $ssoData["Email"]], ["UserID" => $id]);
                $user = $this->userModel->lookupSSOUser($ssoData, self::PROVIDER_KEY);
                $this->assertIsNotArray($user);
            }
        );
    }

    /**
     * Save a user and set their authentication.
     *
     * @param array $user
     * @param string $uniqueID
     * @return int
     */
    private function saveUser(array $user, string $uniqueID): int
    {
        $userID = $this->userModel->save($user, [
            \UserModel::OPT_SAVE_ROLES => isset($user["RoleID"]),
            \UserModel::OPT_NO_CONFIRM_EMAIL => true,
            \UserModel::OPT_SSO_REGISTRATION => true,
        ]);
        $this->userModel->saveAuthentication([
            "UniqueID" => $uniqueID,
            "UserID" => $userID,
            "Provider" => self::PROVIDER_KEY,
        ]);
        return $userID;
    }

    /**
     * Run a basic test of SSO through `UserModel::sso()`.
     */
    public function testSSOMethod(): void
    {
        $user = $this->dummyUser();
        $id = $this->ssoByString($user, __FUNCTION__);

        $dbUser = $this->userModel->getID($id, DATASET_TYPE_ARRAY);
        $this->assertArraySubsetRecursive($user, $dbUser);

        $this->assertSame($id, $this->userModel->getAuthentication(__FUNCTION__, self::PROVIDER_KEY)["UserID"]);
    }

    /**
     * Connect a user with `UserModel::sso()`.
     *
     * @param array|string $user
     * @param string $uniqueID
     * @return false|int
     */
    private function ssoByString($user, string $uniqueID)
    {
        if (is_string($user)) {
            $ssoString = $user;
        } else {
            $str = base64_encode(json_encode($user + ["client_id" => self::PROVIDER_KEY, "id" => $uniqueID]));
            $timestamp = (string) time();
            $signature = hash_hmac("sha1", "$str $timestamp", self::PROVIDER_KEY);
            $ssoString = "$str $signature $timestamp";
        }

        $id = $this->userModel->sso($ssoString, true);
        if (!$id) {
            throw new \Exception("SSO Failed: " . $this->userModel->Validation->resultsText());
        }
        return $id;
    }

    /**
     * @param string $ssoString
     *
     * @return void
     */
    protected function ssoStringConnect(string $ssoString): void
    {
        $this->api()->setUserID(0);
        $dashboardHooks = self::container()->get(\DashboardHooks::class);
        \Gdn::request()->setUrl("/discussions?sso=" . urlencode($ssoString));
        $dashboardHooks->gdn_dispatcher_appStartup_handler(\Gdn::dispatcher());
    }

    /**
     * Test audit logs with the sso string connection
     *
     * @return void
     */
    public function testSSOStringConnectAuditLogs(): void
    {
        $userData = [
            "uniqueid" => 1000,
            "client_id" => self::PROVIDER_KEY,
            "name" => "SSO String User",
            "email" => "sso-string-user@example.com",
            "roles" => ["Administrator"],
        ];

        $partialUserData = [
            "uniqueid" => 1001,
            "client_id" => self::PROVIDER_KEY,
            "name" => "Partial SSO String User",
            "roles" => ["Administrator"],
        ];

        $secret = self::PROVIDER_KEY;
        $string = base64_encode(json_encode($userData));
        $partialString = base64_encode(json_encode($partialUserData));
        $timestamp = CurrentTimeStamp::get();
        $hash = hash_hmac("sha1", "$string $timestamp", $secret);
        $partialHash = hash_hmac("sha1", "$partialString $timestamp", $secret);

        // Success Path
        $ssoString = "{$string} {$hash} {$timestamp} hmacsha1";
        $this->ssoStringConnect($ssoString);
        $this->assertAuditLogged(ExpectedAuditLog::create("sso_string_success"));
        $this->assertAuditLogged(
            ExpectedAuditLog::create("user_register")->withMessage("User `SSO String User` registered.")
        );

        // Missing Timestamp
        $ssoString = "{$string} {$hash}";
        $this->resetTable("auditLog");
        $this->ssoStringConnect($ssoString);
        $this->assertAuditLogged(
            ExpectedAuditLog::create("sso_string_invalid")->withContext([
                "errorMessage" => "Missing SSO timestamp. Invalid SSO signature: {$hash}.",
            ])
        );

        // Invalid timestamp
        $ssoString = "{$string} {$hash} 1234567890";
        $this->resetTable("auditLog");
        $this->ssoStringConnect($ssoString);
        $this->assertAuditLogged(
            ExpectedAuditLog::create("sso_string_invalid")->withContext([
                "errorMessage" => "The SSO timestamp has expired. Invalid SSO signature: {$hash}.",
            ])
        );

        // Bad signature
        $badHash = "BAD_HASH";
        $ssoString = "{$string} {$badHash} {$timestamp} hmacsha1";
        $this->resetTable("auditLog");
        $this->ssoStringConnect($ssoString);
        $this->assertAuditLogged(
            ExpectedAuditLog::create("sso_string_invalid")->withContext([
                "errorMessage" => "Invalid SSO signature: {$badHash}.",
            ])
        );

        // Missing user data.
        $ssoString = "{$partialString} {$partialHash} {$timestamp} hmacsha1";
        $this->resetTable("auditLog");
        $this->ssoStringConnect($ssoString);
        $this->assertAuditLogged(
            ExpectedAuditLog::create("sso_string_invalid_user")->withContext([
                "errorMessage" => "Email is required.",
            ])
        );
    }
}
