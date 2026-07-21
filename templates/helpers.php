<?php
/**
 * HTML escaping helper function for templates.
 * Used throughout plain-PHP templates to prevent XSS attacks.
 */
function e(?string $string): string
{
    return htmlspecialchars((string) $string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
