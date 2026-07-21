<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Bcoem\Domain\AdminPreferences\ValueObject\EntryConstraints;
use Bcoem\Domain\AdminPreferences\Exception\InvalidConstraintException;

class EntryConstraintsTest extends TestCase
{
    private EntryConstraints $constraints;

    protected function setUp(): void
    {
        $this->constraints = new EntryConstraints();
    }

    public function test_create_with_defaults(): void
    {
        $this->assertSame(5, $this->constraints->globalEntryLimit());
        $this->assertSame([], $this->constraints->perStyleLimits());
        $this->assertNull($this->constraints->perTableLimit());
        $this->assertSame([], $this->constraints->subCategoryLimits());
    }

    public function test_create_with_custom_global_limit(): void
    {
        $constraints = new EntryConstraints(globalEntryLimit: 10);
        $this->assertSame(10, $constraints->globalEntryLimit());
    }

    public function test_negative_global_limit_throws(): void
    {
        $this->expectException(InvalidConstraintException::class);
        new EntryConstraints(globalEntryLimit: -1);
    }

    public function test_zero_global_limit_throws(): void
    {
        $this->expectException(InvalidConstraintException::class);
        new EntryConstraints(globalEntryLimit: 0);
    }

    public function test_per_style_limits(): void
    {
        $limits = [1 => 3, 2 => 5, 3 => 2];
        $constraints = new EntryConstraints(perStyleLimits: $limits);
        $this->assertSame($limits, $constraints->perStyleLimits());
    }

    public function test_negative_per_style_limit_throws(): void
    {
        $this->expectException(InvalidConstraintException::class);
        new EntryConstraints(perStyleLimits: [1 => -1]);
    }

    public function test_zero_per_style_limit_throws(): void
    {
        $this->expectException(InvalidConstraintException::class);
        new EntryConstraints(perStyleLimits: [1 => 0]);
    }

    public function test_per_table_limit(): void
    {
        $constraints = new EntryConstraints(perTableLimit: 20);
        $this->assertSame(20, $constraints->perTableLimit());
    }

    public function test_both_per_style_and_per_table_limits_throws(): void
    {
        $this->expectException(InvalidConstraintException::class);
        new EntryConstraints(
            perStyleLimits: [1 => 3],
            perTableLimit: 20
        );
    }

    public function test_sub_category_limits(): void
    {
        $limits = ['IPA' => 2, 'Stout' => 3];
        $constraints = new EntryConstraints(subCategoryLimits: $limits);
        $this->assertSame($limits, $constraints->subCategoryLimits());
    }

    public function test_invalid_category_name_throws(): void
    {
        $this->expectException(InvalidConstraintException::class);
        new EntryConstraints(subCategoryLimits: ['' => 2]);
    }

    public function test_negative_sub_category_limit_throws(): void
    {
        $this->expectException(InvalidConstraintException::class);
        new EntryConstraints(subCategoryLimits: ['IPA' => -1]);
    }

    public function test_can_submit_entry_with_no_restrictions(): void
    {
        $this->assertTrue($this->constraints->canSubmitEntry(1));
        $this->assertTrue($this->constraints->canSubmitEntry(999));
        $this->assertTrue($this->constraints->canSubmitEntry(1, 'IPA'));
    }

    public function test_can_submit_entry_with_per_style_limits(): void
    {
        $constraints = new EntryConstraints(perStyleLimits: [1 => 3, 2 => 5]);
        $this->assertTrue($constraints->canSubmitEntry(1));
        $this->assertTrue($constraints->canSubmitEntry(2));
        $this->assertTrue($constraints->canSubmitEntry(3));  // No limit, allowed
    }

    public function test_can_submit_entry_with_per_table_limit(): void
    {
        $constraints = new EntryConstraints(perTableLimit: 20);
        $this->assertTrue($constraints->canSubmitEntry(1));
        $this->assertTrue($constraints->canSubmitEntry(999));
    }

    public function test_can_submit_entry_with_sub_category_limits(): void
    {
        $constraints = new EntryConstraints(subCategoryLimits: ['IPA' => 2, 'Stout' => 3]);
        $this->assertTrue($constraints->canSubmitEntry(1, 'IPA'));
        $this->assertTrue($constraints->canSubmitEntry(2, 'Stout'));
        $this->assertTrue($constraints->canSubmitEntry(3, 'Porter'));  // No limit
    }

    public function test_with_global_limit_returns_new_instance(): void
    {
        $newConstraints = $this->constraints->withGlobalLimit(10);
        $this->assertNotSame($this->constraints, $newConstraints);
        $this->assertSame(5, $this->constraints->globalEntryLimit());
        $this->assertSame(10, $newConstraints->globalEntryLimit());
    }

    public function test_with_global_limit_same_value_returns_same_instance(): void
    {
        $newConstraints = $this->constraints->withGlobalLimit(5);
        $this->assertSame($this->constraints, $newConstraints);
    }

    public function test_with_per_style_limits_returns_new_instance(): void
    {
        $limits = [1 => 3, 2 => 5];
        $newConstraints = $this->constraints->withPerStyleLimits($limits);
        $this->assertNotSame($this->constraints, $newConstraints);
        $this->assertSame($limits, $newConstraints->perStyleLimits());
    }

    public function test_with_per_style_limits_clears_per_table_limit(): void
    {
        $constraints = new EntryConstraints(perTableLimit: 20);
        $newConstraints = $constraints->withPerStyleLimits([1 => 3]);
        $this->assertNull($newConstraints->perTableLimit());
    }

    public function test_with_per_table_limit_returns_new_instance(): void
    {
        $constraints = new EntryConstraints(perStyleLimits: [1 => 3]);
        $newConstraints = $constraints->withPerTableLimit(20);
        $this->assertNotSame($constraints, $newConstraints);
        $this->assertSame(20, $newConstraints->perTableLimit());
        $this->assertSame([], $newConstraints->perStyleLimits());  // Cleared
    }

    public function test_with_sub_category_limits_returns_new_instance(): void
    {
        $limits = ['IPA' => 2];
        $newConstraints = $this->constraints->withSubCategoryLimits($limits);
        $this->assertNotSame($this->constraints, $newConstraints);
        $this->assertSame($limits, $newConstraints->subCategoryLimits());
    }

    public function test_copy_on_write_chain(): void
    {
        $c1 = new EntryConstraints(globalEntryLimit: 5);
        $c2 = $c1->withGlobalLimit(10);
        $c3 = $c2->withPerStyleLimits([1 => 3]);
        $c4 = $c3->withSubCategoryLimits(['IPA' => 2]);

        $this->assertSame(5, $c1->globalEntryLimit());
        $this->assertSame(10, $c2->globalEntryLimit());
        $this->assertSame([], $c1->perStyleLimits());
        $this->assertSame([1 => 3], $c3->perStyleLimits());
    }
}
