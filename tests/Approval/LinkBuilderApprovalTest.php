<?php
/**
 * Approval (snapshot) tests for the link/URL/form-builder functions.
 *
 * Functions covered
 * ─────────────────
 * • build_action_link($icon,$base_url,$section,$go,$action,$filter,
 *                     $id,$dbTable,$alt_title,$method,$tooltip_text)
 *   Generates an HTML <a> tag with optional Font Awesome icon.
 *   Three distinct shapes:
 *     method=0 (default)  — plain href link with FA icon
 *     method=1            — FA icon prepended, tooltip text shown after icon
 *     method=2            — Fancybox iframe (print form) link
 *     icon="fa-trash-o"   — delete confirmation link (data-confirm attribute)
 *
 * • build_output_link($icon,$base_url,$filename,$section,$go,$action,
 *                     $filter,$id,$dbTable,$alt_title,$modal_window)
 *   Generates a link to an output/ file (e.g. PDF pullsheets).
 *
 * • build_form_action($base_url,$section,$go,$action,$filter,
 *                     $id,$dbTable,$check_required)
 *   Generates the opening <form> tag for admin processing forms.
 *
 * • build_public_url($section,$go,$action,$id,$sef,$base_url,$view)
 *   Returns an href string.  Branches on $_SESSION['prefsSEF']:
 *     'Y' → SEF (clean URL segments)
 *     else → query-string URL
 *
 * All four functions are pure (no database access).
 * Sessions are initialised in setUp() so build_public_url can read prefsSEF.
 *
 * Snapshot strategy
 * ─────────────────
 * Each distinct output shape is snapshotted once.  The snapshot captures the
 * full HTML string, so any attribute-order change, typo, or restructure of
 * the link builder is immediately caught.
 */

declare(strict_types=1);

namespace BCOEM\Tests\Approval;

use PHPUnit\Framework\TestCase;

class LinkBuilderApprovalTest extends TestCase
{
    use SnapshotAssertions;

    private const BASE_URL = 'https://homebrew.example.com/';

    protected function setUp(): void
    {
        parent::setUp();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Default to non-SEF; individual tests override this as needed
        $_SESSION['prefsSEF'] = '';
    }

    // ── build_action_link ─────────────────────────────────────────────────

    /**
     * method=0 (default) — standard icon link with all optional params set.
     */
    public function testActionLinkMethod0AllParams(): void
    {
        $result = build_action_link(
            'fa-edit',
            self::BASE_URL,
            'entries',
            'edit',
            'edit',
            'open',
            '42',
            'brewing',
            'Edit Entry',
            0,
            'Edit this entry'
        );

        $this->assertStringContainsString('<a', $result, 'must produce an anchor tag');
        $this->assertStringContainsString('fa-edit', $result, 'icon class must be present');
        $this->assertMatchesSnapshot($result, 'build_action_link_method0_all_params');
    }

    /**
     * method=0 with "default" values for optional params — verifies that
     * conditional appends (go, action, filter, id) are correctly omitted.
     */
    public function testActionLinkMethod0DefaultParams(): void
    {
        $result = build_action_link(
            'fa-eye',
            self::BASE_URL,
            'entries',
            'default',
            'default',
            'default',
            'default',
            'brewing',
            'View',
            0,
            'View entry'
        );

        $this->assertMatchesSnapshot($result, 'build_action_link_method0_default_params');
    }

    /**
     * method=1 — prepend icon, append tooltip_text as visible label.
     */
    public function testActionLinkMethod1WithIconAndLabel(): void
    {
        $result = build_action_link(
            'fa-check',
            self::BASE_URL,
            'entries',
            'confirm',
            'confirm',
            'default',
            '7',
            'brewing',
            'Confirm Entry',
            1,
            'Confirm this entry'
        );

        $this->assertStringContainsString('fa-check', $result, 'icon must be present');
        $this->assertStringContainsString('Confirm this entry', $result, 'tooltip text must be label');
        $this->assertMatchesSnapshot($result, 'build_action_link_method1_icon_label');
    }

    /**
     * method=2 — Fancybox iframe link (print form).
     */
    public function testActionLinkMethod2PrintFormLink(): void
    {
        $result = build_action_link(
            'fa-print',
            self::BASE_URL,
            '99',        // $section = bid for print links
            'default',
            'print',
            'default',
            '55',
            'brewing',
            'Print Form',
            2,
            'Print entry form'
        );

        $this->assertStringContainsString('fancybox', $result, 'print link must use fancybox');
        $this->assertStringContainsString('iframe', $result, 'print link must be iframe type');
        $this->assertMatchesSnapshot($result, 'build_action_link_method2_print');
    }

    /**
     * icon="fa-trash-o" — delete link with data-confirm attribute.
     * method is ignored for trash links; the function uses a special branch.
     */
    public function testActionLinkTrashIconUsesDeletePath(): void
    {
        $result = build_action_link(
            'fa-trash-o',
            self::BASE_URL,
            'entries',
            'delete',
            'delete',
            'default',
            '13',
            'brewing',
            'Are you sure you want to delete this entry?',
            0,
            'Delete entry'
        );

        $this->assertStringContainsString('data-confirm', $result, 'trash link must have data-confirm');
        $this->assertStringContainsString('fa-trash-o', $result, 'trash icon must be present');
        $this->assertStringContainsString('process.inc.php', $result, 'trash link must go to process.inc.php');
        $this->assertMatchesSnapshot($result, 'build_action_link_trash_delete');
    }

    // ── build_output_link ─────────────────────────────────────────────────

    /**
     * Standard output link without modal window.
     */
    public function testOutputLinkNoModal(): void
    {
        $result = build_output_link(
            'fa-file-pdf-o',
            self::BASE_URL,
            'pullsheets.php',
            'pullsheets',
            'default',
            'default',
            'default',
            '5',
            'brewing',
            'Download pullsheet',
            false
        );

        $this->assertStringContainsString('pullsheets.php', $result, 'filename must be in URL');
        $this->assertStringContainsString('fa-file-pdf-o', $result, 'icon must be present');
        $this->assertMatchesSnapshot($result, 'build_output_link_no_modal');
    }

    /**
     * Output link with modal_window=true adds fancybox attributes.
     */
    public function testOutputLinkWithModal(): void
    {
        $result = build_output_link(
            'fa-file-pdf-o',
            self::BASE_URL,
            'scoresheets.php',
            'scoresheets',
            'default',
            'default',
            'default',
            '3',
            'judging_scores',
            'View scoresheet',
            true
        );

        $this->assertStringContainsString('fancybox', $result, 'modal link must have fancybox attribute');
        $this->assertMatchesSnapshot($result, 'build_output_link_with_modal');
    }

    /**
     * Output link with all optional query-string params set.
     */
    public function testOutputLinkAllOptionalParams(): void
    {
        $result = build_output_link(
            'fa-download',
            self::BASE_URL,
            'labels.php',
            'labels',
            'print',
            'all',
            'received',
            '99',
            'brewing',
            'Print labels',
            false
        );

        $this->assertMatchesSnapshot($result, 'build_output_link_all_params');
    }

    // ── build_form_action ─────────────────────────────────────────────────

    /**
     * Basic form action with all params set and validation disabled.
     */
    public function testFormActionBasicNoValidation(): void
    {
        $result = build_form_action(
            self::BASE_URL,
            'entries',
            'edit',
            'save',
            'open',
            '42',
            'brewing',
            false
        );

        $this->assertStringContainsString('<form', $result, 'must produce a form tag');
        $this->assertStringContainsString('process.inc.php', $result, 'must target process.inc.php');
        $this->assertMatchesSnapshot($result, 'build_form_action_no_validation');
    }

    /**
     * Form action with check_required=true adds data-toggle="validator".
     */
    public function testFormActionWithValidation(): void
    {
        $result = build_form_action(
            self::BASE_URL,
            'setup',
            'default',
            'default',
            'default',
            'default',
            'bcoem_sys',
            true
        );

        $this->assertStringContainsString('data-toggle="validator"', $result,
            'check_required=true must add validator toggle');
        $this->assertMatchesSnapshot($result, 'build_form_action_with_validation');
    }

    /**
     * A section containing 'step' is normalised to 'setup'.
     * (The function does: if strpos($section,'step') !== FALSE → 'setup')
     */
    public function testFormActionStepSectionNormalisedToSetup(): void
    {
        $result = build_form_action(
            self::BASE_URL,
            'step1',      // should be rewritten to 'setup'
            'default',
            'default',
            'default',
            'default',
            'bcoem_sys',
            false
        );

        $this->assertStringContainsString('section=setup', $result,
            '"step*" sections should be rewritten to "setup"');
        $this->assertStringNotContainsString('section=step', $result,
            '"step*" should not appear literally in the action URL');
        $this->assertMatchesSnapshot($result, 'build_form_action_step_normalised');
    }

    // ── build_public_url ─────────────────────────────────────────────────

    /**
     * prefsSEF = '' → classic query-string URL.
     */
    public function testPublicUrlQueryStringMode(): void
    {
        $_SESSION['prefsSEF'] = '';

        $url = build_public_url(
            'register',
            'default',
            'default',
            'default',
            '',
            self::BASE_URL
        );

        $this->assertStringContainsString('index.php', $url, 'non-SEF must use index.php');
        $this->assertStringContainsString('section=register', $url);
        $this->assertMatchesSnapshot($url, 'build_public_url_query_string_basic');
    }

    /**
     * prefsSEF = '' with all optional params set.
     */
    public function testPublicUrlQueryStringAllParams(): void
    {
        $_SESSION['prefsSEF'] = '';

        $url = build_public_url(
            'entries',
            'list',
            'view',
            '7',
            '',
            self::BASE_URL,
            'compact'
        );

        $this->assertMatchesSnapshot($url, 'build_public_url_query_string_all_params');
    }

    /**
     * prefsSEF = 'Y' → clean SEF segments.
     */
    public function testPublicUrlSefMode(): void
    {
        $_SESSION['prefsSEF'] = 'Y';

        $url = build_public_url(
            'entries',
            'list',
            'view',
            '7',
            'Y',
            self::BASE_URL,
            'compact'
        );

        $this->assertStringNotContainsString('index.php', $url, 'SEF must not use index.php');
        $this->assertStringNotContainsString('?', $url, 'SEF must not use query strings');
        $this->assertMatchesSnapshot($url, 'build_public_url_sef_all_params');
    }

    /**
     * prefsSEF = 'Y' with "default" params omitted from the clean URL.
     */
    public function testPublicUrlSefModeDefaultParamsOmitted(): void
    {
        $_SESSION['prefsSEF'] = 'Y';

        $url = build_public_url(
            'register',
            'default',
            'default',
            'default',
            'Y',
            self::BASE_URL
        );

        $this->assertStringNotContainsString('default', $url,
            '"default" param values must be omitted from SEF URLs');
        $this->assertMatchesSnapshot($url, 'build_public_url_sef_defaults_omitted');
    }
}
