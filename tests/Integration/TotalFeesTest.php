<?php
/**
 * Integration tests for total_fees().
 *
 * total_fees($entry_fee, $entry_fee_discount, $entry_discount,
 *            $entry_discount_number, $cap_no, $special_discount_number,
 *            $bid, $filter, $comp_id)
 *
 * The function has three main branches, controlled by $bid and $filter:
 *
 *   Branch A: bid="default",  filter="default"  → sum across ALL users
 *   Branch B: bid=specific,   filter="default"  → one specific brewer
 *   Branch C: bid="default",  filter=specific   → all users, only a style category
 *
 * Parameters:
 *   $entry_fee              base per-entry fee (regular price)
 *   $entry_fee_discount     discounted per-entry fee (volume discount price)
 *   $entry_discount         'Y'/'N' — whether a volume discount applies
 *   $entry_discount_number  number of entries at full price before discount kicks in
 *   $cap_no                 fee cap (0 = no cap)
 *   $special_discount_number per-entry price for members with 'brewerDiscount'='Y'
 *   $bid                    brewer user-table id, or "default"
 *   $filter                 brewCategorySort filter, or "default"
 *   $comp_id                unused in the current code; pass 0
 *
 * NOTE: The function queries `brewConfirmed='1'` (Branch B only) and uses
 * the brewer's discount flag from the `brewer` table.
 */

declare(strict_types=1);

namespace BCOEM\Tests\Integration;

class TotalFeesTest extends IntegrationTestCase
{
    // ── Branch B: single brewer ────────────────────────────────────────────────

    public function testSingleBrewerNoEntriesReturnZero(): void
    {
        $ids = $this->insertTestUser('noentries@test.example', 'No', 'Entries');

        $result = total_fees(10, 8, 'N', 0, 0, '', $ids['userId'], 'default', 0);

        $this->assertSame(0, $result, 'Brewer with no entries should owe $0');
    }

    public function testSingleBrewerFlatFeeNoDiscount(): void
    {
        $ids = $this->insertTestUser('flat@test.example', 'Flat', 'Fee');

        // Insert 3 confirmed entries
        for ($i = 0; $i < 3; $i++) {
            $this->insertEntry($ids['userId'], '1A', paid: 1, confirmed: 1);
        }

        // $10 flat, no volume discount, no cap
        $result = total_fees(10, 10, 'N', 0, 0, '', $ids['userId'], 'default', 0);

        $this->assertSame(30, $result, '3 entries × $10 = $30');
    }

    public function testSingleBrewerVolumeDiscountBelowThreshold(): void
    {
        $ids = $this->insertTestUser('vol@test.example', 'Vol', 'Below');
        for ($i = 0; $i < 2; $i++) {
            $this->insertEntry($ids['userId'], '1A', paid: 1, confirmed: 1);
        }

        // First 3 entries at $10, after that $8; brewer has 2 entries (below threshold)
        $result = total_fees(10, 8, 'Y', 3, 0, '', $ids['userId'], 'default', 0);

        $this->assertSame(20, $result, '2 entries (below threshold of 3) × $10 = $20');
    }

    public function testSingleBrewerVolumeDiscountAboveThreshold(): void
    {
        $ids = $this->insertTestUser('volabove@test.example', 'Vol', 'Above');
        for ($i = 0; $i < 5; $i++) {
            $this->insertEntry($ids['userId'], '1A', paid: 1, confirmed: 1);
        }

        // First 3 at $10, then remaining 2 at $8
        // Expected: (3 × $10) + (2 × $8) = $30 + $16 = $46
        $result = total_fees(10, 8, 'Y', 3, 0, '', $ids['userId'], 'default', 0);

        $this->assertSame(46, $result, '3 × $10 + 2 × $8 = $46');
    }

    public function testSingleBrewerFeeCap(): void
    {
        $ids = $this->insertTestUser('capped@test.example', 'Cap', 'Brewer');
        for ($i = 0; $i < 6; $i++) {
            $this->insertEntry($ids['userId'], '1A', paid: 1, confirmed: 1);
        }

        // $10 per entry, cap at $40 → would be $60 but capped at $40
        $result = total_fees(10, 10, 'N', 0, 40, '', $ids['userId'], 'default', 0);

        $this->assertSame(40, $result, '6 × $10 = $60 but fee cap is $40');
    }

    public function testSingleBrewerMemberDiscountNoVolumeDiscount(): void
    {
        // brewerDiscount = 'Y' means the brewer has a club membership / special rate
        $ids = $this->insertTestUser('member@test.example', 'Club', 'Member', 'Y');
        for ($i = 0; $i < 3; $i++) {
            $this->insertEntry($ids['userId'], '1A', paid: 1, confirmed: 1);
        }

        // Member price = $7, no volume discount
        $result = total_fees(10, 10, 'N', 0, 0, 7, $ids['userId'], 'default', 0);

        $this->assertSame(21, $result, 'Member with 3 entries × $7 = $21');
    }

    public function testSingleBrewerMemberDiscountWithVolumeDiscount(): void
    {
        // Member gets $7, non-member volume discount is $8 after 2 entries.
        // The function picks: if member special > volume discount rate → use volume.
        // Here $8 > $7 → volume discount is NOT cheaper than member price,
        // so member price wins: 2 × $7 + 2 × min($7, $8) = 2 × $7 + 2 × $7 = $28.
        $ids = $this->insertTestUser('membervol@test.example', 'MemberVol', 'Brewer', 'Y');
        for ($i = 0; $i < 4; $i++) {
            $this->insertEntry($ids['userId'], '1A', paid: 1, confirmed: 1);
        }

        // entry_discount_number = 2 → first 2 at special, then apply whichever is lower
        // special_discount_number ($7) < entry_fee_discount ($8) → use $7 for all
        // Expected: 2 × $7 + (4-2) × $7 = $28
        $result = total_fees(10, 8, 'Y', 2, 0, 7, $ids['userId'], 'default', 0);

        $this->assertSame(28, $result, 'Member price ($7) beats volume discount ($8) so all 4 entries at $7');
    }

    // ── Branch A: all brewers ──────────────────────────────────────────────────

    /**
     * We insert two brewers with entries and verify the total sums correctly.
     * The baseline schema already has one admin user (id=1) with no entries,
     * so the function will iterate over all users including them.
     */
    public function testAllBrewersSumAcrossMultipleUsers(): void
    {
        $a = $this->insertTestUser('brewer.a@test.example', 'A', 'Brewer');
        $b = $this->insertTestUser('brewer.b@test.example', 'B', 'Brewer');

        $this->insertEntry($a['userId'], '1A');
        $this->insertEntry($a['userId'], '1B');   // user A: 2 entries
        $this->insertEntry($b['userId'], '10A');  // user B: 1 entry

        // $10 flat, no discount, no cap, all users
        $result = total_fees(10, 10, 'N', 0, 0, '', 'default', 'default', 0);

        // baseline admin user has no entries → $0, A → $20, B → $10 = $30
        $this->assertSame(30, $result, 'Sum across all brewers: A($20) + B($10) = $30');
    }

    // ── Branch C: style category filter ───────────────────────────────────────

    public function testFilterByStyleCategory(): void
    {
        $ids = $this->insertTestUser('catfilter@test.example', 'Cat', 'Filter');

        // 2 entries in category "01", 1 in category "02"
        $this->insert('brewing', [
            'brewBrewerID'    => $ids['userId'],
            'brewCategorySort'=> '01',
            'brewStyle'       => '1A',
            'brewCategory'    => '01',
            'brewPaid'        => 1,
            'brewReceived'    => 1,
            'brewConfirmed'   => 1,
            'brewName'        => 'Cat Filter Beer 1',
        ]);
        $this->insert('brewing', [
            'brewBrewerID'    => $ids['userId'],
            'brewCategorySort'=> '01',
            'brewStyle'       => '1B',
            'brewCategory'    => '01',
            'brewPaid'        => 1,
            'brewReceived'    => 1,
            'brewConfirmed'   => 1,
            'brewName'        => 'Cat Filter Beer 2',
        ]);
        $this->insert('brewing', [
            'brewBrewerID'    => $ids['userId'],
            'brewCategorySort'=> '02',
            'brewStyle'       => '2A',
            'brewCategory'    => '02',
            'brewPaid'        => 1,
            'brewReceived'    => 1,
            'brewConfirmed'   => 1,
            'brewName'        => 'Cat Filter Beer 3',
        ]);

        // Filter to category "01" only → 2 entries × $10 = $20 across all users
        $result = total_fees(10, 10, 'N', 0, 0, '', 'default', '01', 0);

        $this->assertSame(20, $result, 'Filtered to category "01": 2 entries × $10 = $20');
    }
}
