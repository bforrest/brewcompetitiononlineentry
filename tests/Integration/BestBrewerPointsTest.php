<?php
/**
 * Integration tests for best_brewer_points().
 *
 * best_brewer_points($bid, $places, $entry_scores, $points_prefs,
 *                    $tiebreaker, $method="0")
 *
 * $bid           — brewer user id (used by total_paid_received() DB call)
 * $places        — array [1st_count, 2nd_count, 3rd_count, 4th_count, hm_count]
 * $entry_scores  — array of numeric judging scores for tiebreaker calculations
 * $points_prefs  — array [pts_1st, pts_2nd, pts_3rd, pts_4th, pts_hm]
 *                  (or for method=1, contains distribution info — not tested here)
 * $tiebreaker    — array of tiebreaker rule strings (empty = no tiebreaker)
 * $method        — "0" = default (additive), "1" = CoA method
 *
 * The function calls total_paid_received("", $bid) which runs a DB query
 * to count the brewer's paid+received entries.  That count is used by the
 * TBNumEntries and TBAvgScore tiebreakers.
 *
 * Most of the logic is pure arithmetic; the DB involvement is minimal but
 * enough to require a live connection.
 */

declare(strict_types=1);

namespace BCOEM\Tests\Integration;

class BestBrewerPointsTest extends IntegrationTestCase
{
    /**
     * Helper: insert N paid+received entries for a brewer.
     */
    private function seedEntries(int $userId, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->insertEntry($userId, '1A', paid: 1, received: 1);
        }
    }

    // ── Basic points (method 0, no tiebreakers) ────────────────────────────────

    public function testNoPlacesReturnsZeroPoints(): void
    {
        $ids = $this->insertTestUser('zero@test.example', 'Zero', 'Points');
        // No entries seeded; total_paid_received returns 0

        $points = best_brewer_points(
            $ids['userId'],
            [0, 0, 0, 0, 0],   // places
            [],                  // entry_scores
            [7, 5, 3, 1, 1],   // points_prefs
            [],                  // tiebreakers
            '0'
        );

        $this->assertSame(0.0, (float)$points, 'Zero placements should yield 0 points');
    }

    public function testSingleFirstPlacePoints(): void
    {
        $ids = $this->insertTestUser('first@test.example', 'First', 'Place');
        $this->seedEntries($ids['userId'], 1);

        // 1st = 7 pts, 2nd = 5, 3rd = 3, 4th = 1, HM = 1
        $points = best_brewer_points(
            $ids['userId'],
            [1, 0, 0, 0, 0],
            [38.0],
            [7, 5, 3, 1, 1],
            [],
            '0'
        );

        $this->assertSame(7.0, (float)$points, '1 first place @ 7 pts = 7');
    }

    public function testMixedPlacementsSum(): void
    {
        $ids = $this->insertTestUser('mixed@test.example', 'Mixed', 'Places');
        $this->seedEntries($ids['userId'], 5);

        // 2 firsts (14) + 1 second (5) + 1 third (3) + 0 fourth + 1 HM (1) = 23
        $points = best_brewer_points(
            $ids['userId'],
            [2, 1, 1, 0, 1],
            [45.0, 42.0, 38.0, 36.0, 33.0],
            [7, 5, 3, 1, 1],
            [],
            '0'
        );

        $this->assertSame(23.0, (float)$points, '2×7 + 1×5 + 1×3 + 1×1 = 23');
    }

    // ── Tiebreaker: TBTotalPlaces ──────────────────────────────────────────────

    /**
     * TBTotalPlaces adds a fractional bonus based on total 1st+2nd+3rd placements.
     * power starts at 0 and increments by 2 for each tiebreaker → divisor = 10^2 = 100.
     * bonus = sum(places[0..2]) / 100
     */
    public function testTBTotalPlacesTiebreakerAddsBonus(): void
    {
        $ids = $this->insertTestUser('tbtotal@test.example', 'TBTotal', 'Brewer');
        $this->seedEntries($ids['userId'], 2);

        // 1 first + 1 second + 0 third = sum = 2; power=2 → bonus = 2/100 = 0.02
        // Main points: 1×7 + 1×5 = 12; total = 12.02
        $points = best_brewer_points(
            $ids['userId'],
            [1, 1, 0, 0, 0],
            [42.0, 38.0],
            [7, 5, 3, 1, 1],
            ['TBTotalPlaces'],
            '0'
        );

        $this->assertEqualsWithDelta(12.02, (float)$points, 0.0001, '12 main pts + 0.02 TBTotalPlaces bonus');
    }

    // ── Tiebreaker: TBFirstPlaces ──────────────────────────────────────────────

    public function testTBFirstPlacesTiebreakerAddsBonus(): void
    {
        $ids = $this->insertTestUser('tbfirst@test.example', 'TBFirst', 'Brewer');
        $this->seedEntries($ids['userId'], 2);

        // 2 first places; power=2 → bonus = 2/100 = 0.02
        // Main points: 2×7 = 14; total = 14.02
        $points = best_brewer_points(
            $ids['userId'],
            [2, 0, 0, 0, 0],
            [45.0, 43.0],
            [7, 5, 3, 1, 1],
            ['TBFirstPlaces'],
            '0'
        );

        $this->assertEqualsWithDelta(14.02, (float)$points, 0.0001, '14 main pts + 0.02 TBFirstPlaces bonus');
    }

    // ── Tiebreaker: TBNumEntries (DB-dependent) ────────────────────────────────

    /**
     * TBNumEntries uses total_paid_received() to get the entry count.
     * Bonus = floor(100 / count) / 10^power.
     * With 4 entries and power=4: floor(100/4)=25, bonus = 25/10000 = 0.0025.
     */
    public function testTBNumEntriesTiebreakerWithFourEntries(): void
    {
        $ids = $this->insertTestUser('tbnumentries@test.example', 'TBNum', 'Entries');
        $this->seedEntries($ids['userId'], 4);  // 4 paid+received entries

        // Main: 1×7 = 7; bonus = floor(100/4)/10000 = 25/10000 = 0.0025; total = 7.0025
        $points = best_brewer_points(
            $ids['userId'],
            [1, 0, 0, 0, 0],
            [40.0, 38.0, 36.0, 34.0],
            [7, 5, 3, 1, 1],
            ['TBNumEntries'],
            '0'
        );

        $this->assertEqualsWithDelta(7.0025, (float)$points, 0.00001, '7 main pts + TBNumEntries bonus (4 entries)');
    }

    public function testTBNumEntriesWithZeroEntriesAddZeroBonus(): void
    {
        $ids = $this->insertTestUser('tbnumzero@test.example', 'TBNumZero', 'Brewer');
        // No entries seeded → total_paid_received returns 0 → bonus = 0

        $points = best_brewer_points(
            $ids['userId'],
            [1, 0, 0, 0, 0],
            [],
            [7, 5, 3, 1, 1],
            ['TBNumEntries'],
            '0'
        );

        $this->assertSame(7.0, (float)$points, '7 main pts + 0 TBNumEntries bonus (0 entries)');
    }

    // ── Tiebreaker: TBMinScore ─────────────────────────────────────────────────

    public function testTBMinScoreTiebreakerUsesMinEntryScore(): void
    {
        $ids = $this->insertTestUser('tbmin@test.example', 'TBMin', 'Score');
        $this->seedEntries($ids['userId'], 3);

        // min([38, 45, 36]) = 36; floor(10 × 36) = 360; power=4 → 360/10000 = 0.036
        // Main: 1×7 = 7; total = 7.036
        $points = best_brewer_points(
            $ids['userId'],
            [1, 0, 0, 0, 0],
            [38.0, 45.0, 36.0],
            [7, 5, 3, 1, 1],
            ['TBMinScore'],
            '0'
        );

        $this->assertEqualsWithDelta(7.036, (float)$points, 0.0001, '7 main pts + 0.036 TBMinScore bonus');
    }

    // ── Tiebreaker: TBMaxScore ─────────────────────────────────────────────────

    public function testTBMaxScoreTiebreakerUsesMaxEntryScore(): void
    {
        $ids = $this->insertTestUser('tbmax@test.example', 'TBMax', 'Score');
        $this->seedEntries($ids['userId'], 3);

        // max([38, 45, 36]) = 45; floor(10 × 45) = 450; power=4 → 450/10000 = 0.045
        $points = best_brewer_points(
            $ids['userId'],
            [1, 0, 0, 0, 0],
            [38.0, 45.0, 36.0],
            [7, 5, 3, 1, 1],
            ['TBMaxScore'],
            '0'
        );

        $this->assertEqualsWithDelta(7.045, (float)$points, 0.0001, '7 main pts + 0.045 TBMaxScore bonus');
    }

    // ── Multiple tiebreakers stack ────────────────────────────────────────────

    public function testMultipleTiebreakersAccumulate(): void
    {
        $ids = $this->insertTestUser('tbmulti@test.example', 'Multi', 'Tiebreak');
        $this->seedEntries($ids['userId'], 2);

        // TBTotalPlaces: power=2; places[0..2] = 1+0+0 = 1; bonus = 1/100 = 0.01
        // TBFirstPlaces: power=4; places[0] = 1; bonus = 1/10000 = 0.0001
        // Main: 1×7 = 7
        // Total: 7 + 0.01 + 0.0001 = 7.0101
        $points = best_brewer_points(
            $ids['userId'],
            [1, 0, 0, 0, 0],
            [40.0, 38.0],
            [7, 5, 3, 1, 1],
            ['TBTotalPlaces', 'TBFirstPlaces'],
            '0'
        );

        $this->assertEqualsWithDelta(7.0101, (float)$points, 0.00001, 'Stacked tiebreakers: 7 + 0.01 + 0.0001');
    }
}
