<?php
declare(strict_types=1);

namespace Bcoem\Domain\AdminPreferences\ValueObject;

/**
 * StyleSetConfiguration represents the active style set and any allowed exceptions.
 *
 * This is an immutable value object that enforces copy-on-write semantics.
 * When you need to modify it, you get a new instance rather than mutating the existing one.
 *
 * Properties:
 * - styleSet: The official style guidelines to use (BJCP2025, AABC2022, etc.)
 * - allowedStyleIds: IDs of official styles that can be entered (for filtering)
 * - customExceptions: IDs of custom/local styles that are allowed despite not being in the official set
 */
final class StyleSetConfiguration
{
    /**
     * @param StyleSet $styleSet The active style set guidelines
     * @param array<int> $allowedStyleIds Official style IDs that can be entered (empty = all allowed)
     * @param array<int> $customExceptions Custom/local style IDs to allow as exceptions
     */
    public function __construct(
        private readonly StyleSet $styleSet,
        private readonly array $allowedStyleIds = [],
        private readonly array $customExceptions = [],
    ) {
    }

    public function styleSet(): StyleSet
    {
        return $this->styleSet;
    }

    /**
     * @return array<int>
     */
    public function allowedStyleIds(): array
    {
        return $this->allowedStyleIds;
    }

    /**
     * @return array<int>
     */
    public function customExceptions(): array
    {
        return $this->customExceptions;
    }

    /**
     * Check if a given style ID is allowed to be entered.
     *
     * Allowed means: either no restrictions are set (allowedStyleIds is empty),
     * or the style ID is in allowedStyleIds, or the style ID is in customExceptions.
     */
    public function isStyleAllowed(int $styleId): bool
    {
        // If allowedStyleIds is empty, all official styles are allowed
        if (empty($this->allowedStyleIds)) {
            return true;
        }

        // Check if it's an allowed official style or a custom exception
        return in_array($styleId, $this->allowedStyleIds, true) ||
               in_array($styleId, $this->customExceptions, true);
    }

    /**
     * Add a custom exception style (copy-on-write).
     *
     * Returns a new StyleSetConfiguration with the exception added.
     * Does not modify this instance.
     */
    public function addException(int $styleId): self
    {
        if (in_array($styleId, $this->customExceptions, true)) {
            return $this;  // Already exists, return self
        }

        $newExceptions = $this->customExceptions;
        $newExceptions[] = $styleId;

        return new self($this->styleSet, $this->allowedStyleIds, $newExceptions);
    }

    /**
     * Remove a custom exception style (copy-on-write).
     *
     * Returns a new StyleSetConfiguration with the exception removed.
     * Does not modify this instance.
     */
    public function removeException(int $styleId): self
    {
        if (!in_array($styleId, $this->customExceptions, true)) {
            return $this;  // Doesn't exist, return self
        }

        $newExceptions = array_values(
            array_filter(
                $this->customExceptions,
                fn(int $id) => $id !== $styleId
            )
        );

        return new self($this->styleSet, $this->allowedStyleIds, $newExceptions);
    }

    /**
     * Transition to a different style set (copy-on-write).
     *
     * Returns a new StyleSetConfiguration with the style set changed.
     * Clears custom exceptions when transitioning (they may not apply to the new set).
     */
    public function withStyleSet(StyleSet $newSet): self
    {
        if ($newSet === $this->styleSet) {
            return $this;  // Same set, return self
        }

        return new self($newSet, $this->allowedStyleIds, []);
    }

    /**
     * Create a new configuration with updated allowed style IDs.
     *
     * This filters the set of official styles that can be entered.
     * Empty array means all official styles are allowed.
     */
    public function withAllowedStyleIds(array $styleIds): self
    {
        // Type check: all must be integers
        foreach ($styleIds as $id) {
            if (!is_int($id)) {
                throw new \InvalidArgumentException('All style IDs must be integers');
            }
        }

        return new self($this->styleSet, $styleIds, $this->customExceptions);
    }
}
