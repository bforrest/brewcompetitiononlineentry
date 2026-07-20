<?php

declare(strict_types=1);

namespace Bcoem\PHPStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/** Phase 3 will make this actually flag Domain-layer references to legacy code outside src/Legacy/. */
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
