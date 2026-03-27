<?php
/**
 * Characterization tests for HTML-generating utility functions in common.lib.php.
 *
 * These functions have no DB dependencies and return HTML strings.
 * We test structure and key attribute values rather than exact whitespace.
 */

use PHPUnit\Framework\TestCase;

class HtmlGeneratorsTest extends TestCase
{
    // ── create_bs_alert() ────────────────────────────────────

    public function test_create_bs_alert_returns_div_with_correct_type(): void
    {
        $result = create_bs_alert("my-alert", "warning");
        $this->assertStringContainsString('alert-warning', $result);
        $this->assertStringContainsString('id="my-alert"', $result);
    }

    public function test_create_bs_alert_with_header_includes_h5(): void
    {
        $result = create_bs_alert("a1", "danger", "Watch out!");
        $this->assertStringContainsString('<h5', $result);
        $this->assertStringContainsString('Watch out!', $result);
        $this->assertStringContainsString('<hr>', $result);
    }

    public function test_create_bs_alert_with_body_includes_span(): void
    {
        $result = create_bs_alert("a2", "info", "", "This is the body.");
        $this->assertStringContainsString('<span>This is the body.</span>', $result);
    }

    public function test_create_bs_alert_with_icon_includes_fa_class(): void
    {
        $result = create_bs_alert("a3", "success", "Done", "", "fa-check-circle");
        $this->assertStringContainsString('fa-check-circle', $result);
    }

    public function test_create_bs_alert_no_dismiss_removes_button(): void
    {
        $result = create_bs_alert("a4", "info", "", "", "", "no-dismiss");
        $this->assertStringNotContainsString('btn-close', $result);
    }

    public function test_create_bs_alert_default_is_dismissable(): void
    {
        $result = create_bs_alert("a5", "info");
        $this->assertStringContainsString('btn-close', $result);
        $this->assertStringContainsString('alert-dismissible', $result);
    }

    public function test_create_bs_alert_stacked_mode_adds_class(): void
    {
        $result = create_bs_alert("a6", "info", "", "", "", "", TRUE);
        $this->assertStringContainsString('alert-stacked', $result);
        $this->assertStringNotContainsString('d-flex', $result);
    }

    public function test_create_bs_alert_non_stacked_adds_flex_class(): void
    {
        $result = create_bs_alert("a7", "info", "", "", "", "", FALSE);
        $this->assertStringContainsString('d-flex', $result);
    }

    public function test_create_bs_alert_has_fade_show_classes(): void
    {
        $result = create_bs_alert("a8", "primary");
        $this->assertStringContainsString('fade show', $result);
    }

    public function test_create_bs_alert_has_role_alert(): void
    {
        $result = create_bs_alert("a9", "secondary");
        $this->assertStringContainsString('role="alert"', $result);
    }

    public function test_create_bs_alert_is_wrapped_in_div(): void
    {
        $result = create_bs_alert("a10", "info");
        $this->assertStringStartsWith('<div', $result);
        $this->assertStringEndsWith('</div>', $result);
    }

    // ── create_bs_popover() ──────────────────────────────────

    public function test_create_bs_popover_returns_anchor_tag(): void
    {
        $result = create_bs_popover("p1", "my-class", "link", "Title", "Body", "hover", "", "Click me");
        $this->assertStringStartsWith('<a', $result);
        $this->assertStringEndsWith('</a>', $result);
    }

    public function test_create_bs_popover_includes_title(): void
    {
        $result = create_bs_popover("p2", "", "link", "My Popover Title", "Body", "hover", "", "");
        $this->assertStringContainsString('title="My Popover Title"', $result);
    }

    public function test_create_bs_popover_includes_body_as_data_content(): void
    {
        $result = create_bs_popover("p3", "", "link", "T", "Popover body text", "hover", "", "");
        $this->assertStringContainsString('data-content="Popover body text"', $result);
    }

    public function test_create_bs_popover_button_type_adds_btn_class(): void
    {
        $result = create_bs_popover("p4", "base-class", "button", "T", "B", "click", "", "");
        $this->assertStringContainsString('btn btn-primary', $result);
    }

    public function test_create_bs_popover_link_type_shows_link_text(): void
    {
        $result = create_bs_popover("p5", "", "link", "T", "B", "hover", "", "Read more");
        $this->assertStringContainsString("Read more", $result);
    }

    public function test_create_bs_popover_icon_type_includes_fa_tag(): void
    {
        $result = create_bs_popover("p6", "", "icon", "T", "B", "hover", "fa fa-info", "");
        $this->assertStringContainsString('<i class="fa fa-info">', $result);
    }

    public function test_create_bs_popover_has_data_toggle_attribute(): void
    {
        $result = create_bs_popover("p7", "", "link", "T", "B", "hover", "", "X");
        $this->assertStringContainsString('data-toggle="popover"', $result);
    }

    public function test_create_bs_popover_trigger_is_set(): void
    {
        $result = create_bs_popover("p8", "", "link", "T", "B", "focus", "", "X");
        $this->assertStringContainsString('data-trigger="focus"', $result);
    }

    // ── style_number_const() ─────────────────────────────────
    // Method 1 and 2 are pure (no session). Method 0 reads $_SESSION.

    public function test_style_number_const_method1_pads_number(): void
    {
        // Method 1: returns raw category + separator + subcategory
        $result = style_number_const("01", "A", ".", 1);
        $this->assertSame("01.A", $result);
    }

    public function test_style_number_const_method2_ltrims_zeros(): void
    {
        // Method 2: ltrim leading zeros from category
        $result = style_number_const("01", "A", ".", 2);
        $this->assertSame("1.A", $result);
    }

    public function test_style_number_const_method3_default_ltrims_category(): void
    {
        // Method 3 (default): ltrim category, keep subcategory as-is
        $result = style_number_const("01", "A", ".", 3);
        $this->assertSame("1.A", $result);
    }

    public function test_style_number_const_method0_ba_returns_empty_string(): void
    {
        // Method 0 with BA style set should return ""
        $_SESSION['prefsStyleSet'] = "BA";
        $result = style_number_const("01", "A", ".", 0);
        $this->assertSame("", $result);
        unset($_SESSION['prefsStyleSet']);
    }

    public function test_style_number_const_method0_bjcp2021_ltrims(): void
    {
        $_SESSION['prefsStyleSet'] = "BJCP2021";
        $result = style_number_const("01", "A", ".", 0);
        $this->assertSame("1.A", $result);
        unset($_SESSION['prefsStyleSet']);
    }

    public function test_style_number_const_method0_no_session_returns_empty(): void
    {
        unset($_SESSION['prefsStyleSet']);
        $result = style_number_const("01", "A", ".", 0);
        $this->assertSame("", $result);
    }

    // ── designations() ───────────────────────────────────────
    // Takes a comma-separated STRING of rank designations, not an array.
    // Returns HTML lines for any designation that differs from $display.

    public function test_designations_returns_string(): void
    {
        // $judge_array is a comma-delimited string; $display is what to exclude
        $result = designations("Certified,National", "Certified");
        $this->assertIsString($result);
    }

    public function test_designations_excludes_display_value(): void
    {
        $result = designations("Certified,National", "Certified");
        // "Certified" is the $display value and should be excluded
        $this->assertStringNotContainsString(">Certified<", $result);
        $this->assertStringContainsString("National", $result);
    }

    public function test_designations_empty_string_returns_br_tag(): void
    {
        // explode(",","") returns [""], so the loop runs once with $rank2="".
        // "" != "Certified" is true → $return .= "<br />" → returns "<br />".
        // There is no empty-string guard in the function.
        $result = designations("", "Certified");
        $this->assertSame("<br />", $result);
    }

    // ── GetSQLValueString() ──────────────────────────────────
    // Characterization notes:
    //   "int"    → returns PHP int via intval()
    //   "double" → returns SQL-quoted string e.g. '3.14'  (NOT a float)
    //   "defined"→ returns the raw defined/not-defined value (NO extra quoting)
    //   "text"   → requires DB connection (mysqli_real_escape_string); skip here

    public function test_get_sql_value_string_type_int_casts_to_int(): void
    {
        $result = GetSQLValueString("42abc", "int");
        $this->assertSame(42, $result);
    }

    public function test_get_sql_value_string_type_int_empty_returns_null_string(): void
    {
        $result = GetSQLValueString("", "int");
        $this->assertSame("NULL", $result);
    }

    public function test_get_sql_value_string_type_double_returns_quoted_string(): void
    {
        // double type wraps in single quotes → "'3.14'" not the float 3.14
        $result = GetSQLValueString("3.14xyz", "double");
        $this->assertSame("'3.14'", $result);
    }

    public function test_get_sql_value_string_type_defined_with_value(): void
    {
        // "defined" returns the raw $theDefinedValue — no extra quoting added
        $result = GetSQLValueString("yes", "defined", "YES", "NO");
        $this->assertSame("YES", $result);
    }

    public function test_get_sql_value_string_type_defined_without_value(): void
    {
        $result = GetSQLValueString("", "defined", "YES", "NO");
        $this->assertSame("NO", $result);
    }
}
