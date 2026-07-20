<?php

declare(strict_types=1);

namespace Bcoem\PHPStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/** Phase 3 will make this actually flag mysqli_* calls outside src/Database/Connection.php. */
final class NoMysqliOutsideConnectionRule implements Rule
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
