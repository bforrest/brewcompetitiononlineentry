<?php

declare(strict_types=1);

namespace Bcoem\PHPStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/**
 * Phase 3 will make this actually flag Domain-layer references to legacy code
 * outside src/Legacy/. When it does: src/Kernel/Middleware/AuthenticationMiddleware.php
 * (reads $_SESSION directly) and src/Kernel/Middleware/SessionMiddleware.php
 * (reads $GLOBALS['installation_id']) are sanctioned exceptions - they ARE the
 * deliberate session-ingress boundary between legacy superglobal state and the
 * Slim pipeline (Phase 2, Task 5), not architectural drift. Carve them out
 * explicitly rather than relocating them into src/Legacy/ (they are kernel
 * infrastructure, not throwaway legacy-bridge code) or discovering the
 * false-positive by surprise (flagged by Phase 2's final whole-branch review).
 */
final class NoLegacyReferenceOutsideLegacyRule implements Rule
{
    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        return [];
    }
}
