<?php
/**
 * Source-level regression guards for hardening fixes that can't be reached
 * through the browser suite.
 *
 * These read PHP source as text. They are deliberately coarse: they catch the
 * specific unsafe patterns that were fixed coming back, not every possible way
 * to write them. Behavioural coverage of the register and contact forms lives
 * in e2e/tests/xss-cookies.spec.ts.
 */

use PHPUnit\Framework\TestCase;

class SourceHardeningTest extends TestCase
{
    /** Files that refill form fields from $_COOKIE. */
    public static function cookieEchoFiles(): array
    {
        return [
            'register form'    => ['sections/register.sec.php'],
            'contact form'     => ['sections/contact.sec.php'],
            // Only shown during first-time setup, so e2e can't reach it.
            'setup admin user' => ['setup/admin_user.setup.php'],
        ];
    }

    /**
     * P1-SEC-003: a cookie value must never be echoed or concatenated into
     * HTML without htmlspecialchars().
     *
     * @dataProvider cookieEchoFiles
     */
    public function test_cookie_values_are_html_escaped_when_output(string $file): void
    {
        $source = file_get_contents(ROOT . $file);
        $this->assertNotFalse($source, "cannot read $file");

        // echo $_COOKIE['x'] with no htmlspecialchars( wrapper
        $this->assertSame(
            0,
            preg_match_all('/echo\s+\$_COOKIE\[/', $source, $m),
            "$file echoes a raw \$_COOKIE value"
        );

        // "...".$_COOKIE['x']."..." string building with no wrapper
        $this->assertSame(
            0,
            preg_match_all('/"\s*\.\s*\$_COOKIE\[[^\]]+\]\s*\./', $source, $m),
            "$file concatenates a raw \$_COOKIE value into HTML"
        );
    }

    /**
     * P1-SEC-004: process.inc.php must not switch on display_errors. (paths.php
     * sets it from DEBUG, but that line runs after this one, so anything that
     * fails before it would be shown to the user.)
     */
    public function test_process_endpoint_does_not_force_display_errors_on(): void
    {
        $source = file_get_contents(ROOT . 'includes/process.inc.php');
        $this->assertNotFalse($source);

        $this->assertSame(
            0,
            preg_match("/ini_set\(\s*['\"]display_errors['\"]\s*,\s*['\"]?(1|on|true)['\"]?\s*\)/i", $source),
            'includes/process.inc.php turns display_errors on'
        );
    }

    /**
     * SQLi migration phase 1, task 3: sections/winners.sec.php's two
     * mysqli_query() calls (style lookup and style-count) and
     * sections/brewer_entries.sec.php's eval-row style lookup were
     * converted to $db_conn->where()/getOne() parameter binding. None of
     * these three queries are reachable through the current e2e fixtures
     * without flipping prefsDisplayWinners / closing judging (unsafe against
     * other sessions sharing this dev stack - see
     * e2e/tests/sqli-migration-sections-files.spec.ts's header comment), so
     * this is a source-level regression guard rather than a behavioural
     * test: it fails if either file goes back to building a query string
     * with sprintf()/concatenation and a raw mysqli_query() call.
     */
    public function test_winners_and_brewer_entries_style_lookups_stay_parameterized(): void
    {
        $winners = file_get_contents(ROOT . 'sections/winners.sec.php');
        $this->assertNotFalse($winners);
        $this->assertSame(
            0,
            preg_match('/mysqli_query\(\$connection,\s*\$query_styl/', $winners),
            'sections/winners.sec.php has reverted to raw mysqli_query() for its style lookups'
        );
        $this->assertStringContainsString("\$db_conn->getOne(\$prefix.\"styles\"", $winners);
        $this->assertStringContainsString('$db_conn->getOne($brewing_db_table, "COUNT(*) as count")', $winners);

        $brewerEntries = file_get_contents(ROOT . 'sections/brewer_entries.sec.php');
        $this->assertNotFalse($brewerEntries);
        $this->assertSame(
            0,
            preg_match('/mysqli_query\(\$connection,\s*\$query_style\b/', $brewerEntries),
            'sections/brewer_entries.sec.php has reverted to raw mysqli_query() for its eval-row style lookup'
        );
        $this->assertStringContainsString('$db_conn->getOne($prefix."styles", "id,brewStyleType")', $brewerEntries);
    }
}
