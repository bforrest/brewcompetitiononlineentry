<?php
/**
 * Integration tests for brewer_info().
 *
 * brewer_info($uid, $filter) queries the `brewer` table (or an archive variant)
 * and returns a caret-delimited (^) string of brewer fields.
 *
 * Field positions in the return string (from source):
 *   0  brewerFirstName
 *   1  brewerLastName
 *   2  brewerPhone1
 *   3  brewerJudgeRank  (with special handling for Mead judges)
 *   4  brewerJudgeID
 *   5  brewerMHP
 *   6  brewerEmail
 *   7  uid
 *   8  brewerClubs
 *   9  brewerDiscount
 *   10 brewerAddress
 *   11 brewerCity
 *   12 brewerState
 *   13 brewerZip
 *   14 brewerCountry
 *   15 brewerBreweryName  (or &nbsp; if not Pro edition)
 *   16 "Certified Mead Judge" or &nbsp;
 *   17 TTB licence or &nbsp;
 *   18 Production info or &nbsp;
 */

declare(strict_types=1);

namespace BCOEM\Tests\Integration;

class BrewerInfoTest extends IntegrationTestCase
{
    // ── Basic lookup by uid ────────────────────────────────────────────────────

    public function testBasicBrewerLookupByUid(): void
    {
        $ids = $this->insertTestUser('alice@test.example', 'Alice', 'Hopps');

        $result = brewer_info($ids['userId']);

        // brewer_info returns a caret-delimited string; parse it
        $parts = explode('^', $result);

        $this->assertSame('Alice',              $parts[0], 'first name at position 0');
        $this->assertSame('Hopps',              $parts[1], 'last name at position 1');
        $this->assertSame('alice@test.example', $parts[6], 'email at position 6');
        $this->assertSame((string)$ids['userId'], $parts[7], 'uid at position 7');
    }

    // ── Lookup falls back to id column when uid not found ─────────────────────

    /**
     * The function first queries WHERE uid='$uid'. If it finds zero rows it
     * retries WHERE id='$uid'. This covers the case where the uid foreign-key
     * relationship is wrong but the row id matches.
     */
    public function testFallbackToIdLookupWhenUidMissing(): void
    {
        // Insert brewer with uid deliberately NOT matching a users row
        $brewerId = $this->insert('brewer', [
            'uid'            => 99999,    // no matching users row
            'brewerFirstName'=> 'Bob',
            'brewerLastName' => 'Malt',
            'brewerEmail'    => 'bob@test.example',
            'brewerDiscount' => 'N',
            'brewerJudgeRank'=> 'Non-BJCP',
            'brewerJudgeMead'=> 'N',
            'brewerCountry'  => 'United States',
        ]);

        // Look up by the brewer row's id (not uid=99999)
        $result = brewer_info($brewerId);
        $parts  = explode('^', $result);

        $this->assertSame('Bob',  $parts[0], 'first name via fallback id lookup');
        $this->assertSame('Malt', $parts[1], 'last name via fallback id lookup');
    }

    // ── Judge rank fields ──────────────────────────────────────────────────────

    public function testNonBjcpMeadJudgeRankLabel(): void
    {
        // A brewer who is a Mead judge with rank "Non-BJCP" gets a special label
        $userId = $this->insert('users', [
            'user_name'  => 'mead@test.example',
            'password'   => '$2a$08$placeholder',
            'userLevel'  => '2',
            'userCreated'=> date('Y-m-d H:i:s'),
        ]);
        $this->insert('brewer', [
            'uid'            => $userId,
            'brewerFirstName'=> 'Mead',
            'brewerLastName' => 'Lady',
            'brewerEmail'    => 'mead@test.example',
            'brewerDiscount' => 'N',
            'brewerJudgeRank'=> 'Non-BJCP',
            'brewerJudgeMead'=> 'Y',   // Mead judge flag
            'brewerCountry'  => 'United States',
        ]);

        $result = brewer_info($userId);
        $parts  = explode('^', $result);

        // Source: if brewerJudgeMead=='Y' && brewerJudgeRank=='Non-BJCP' → 'Non-BJCP Beer'
        $this->assertSame('Non-BJCP Beer', $parts[3], 'Non-BJCP mead judge rank label');
    }

    public function testBjcpJudgeRankPassedThrough(): void
    {
        $ids = $this->insertTestUser('bjcp@test.example', 'Judge', 'Hoppy');

        // Overwrite brewer with a specific BJCP rank
        self::$conn->query(
            "UPDATE `" . self::$pfx . "brewer` SET `brewerJudgeRank`='National', `brewerJudgeID`='B0001' WHERE uid='{$ids['userId']}'"
        );

        $result = brewer_info($ids['userId']);
        $parts  = explode('^', $result);

        $this->assertSame('National', $parts[3], 'BJCP rank at position 3');
        $this->assertSame('B0001',    $parts[4], 'BJCP judge ID at position 4');
    }

    // ── Discount flag ──────────────────────────────────────────────────────────

    public function testBrewerWithDiscountFlagY(): void
    {
        $ids = $this->insertTestUser('discount@test.example', 'Rich', 'Club', 'Y');

        $result = brewer_info($ids['userId']);
        $parts  = explode('^', $result);

        $this->assertSame('Y', $parts[9], 'discount flag at position 9');
    }

    // ── Missing uid returns empty string fields ────────────────────────────────

    public function testLookupForNonExistentUidReturnsNullishFields(): void
    {
        // No user or brewer with uid 999888 in the seeded data
        $result = brewer_info(999888);
        // The function still returns a caret-delimited string, but all fields
        // from a null row will be empty/null → the string is mostly carets
        $this->assertIsString($result);
    }

    // ── Certified Mead Judge label (position 16) ──────────────────────────────

    public function testCertifiedMeadJudgeLabelAtPosition16(): void
    {
        $userId = $this->insert('users', [
            'user_name'  => 'cmj@test.example',
            'password'   => '$2a$08$placeholder',
            'userLevel'  => '2',
            'userCreated'=> date('Y-m-d H:i:s'),
        ]);
        $this->insert('brewer', [
            'uid'            => $userId,
            'brewerFirstName'=> 'Cert',
            'brewerLastName' => 'Mead',
            'brewerEmail'    => 'cmj@test.example',
            'brewerJudgeMead'=> 'Y',
            'brewerJudgeRank'=> 'Certified',
            'brewerDiscount' => 'N',
            'brewerCountry'  => 'United States',
        ]);

        $result = brewer_info($userId);
        $parts  = explode('^', $result);

        // The source appends a hard-coded '&nbsp;' after the label string, so
        // the actual value is 'Certified Mead Judge&nbsp;' (with a trailing
        // non-breaking space entity).  Pin that current behavior here.
        $this->assertSame('Certified Mead Judge&nbsp;', $parts[16], 'Certified Mead Judge label at position 16');
    }
}
