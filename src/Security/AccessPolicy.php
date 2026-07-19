<?php

declare(strict_types=1);

namespace Bcoem\Security;

final class AccessPolicy
{
    /** @param array<string, Role> $map */
    private function __construct(private readonly array $map)
    {
    }

    public static function fromFile(string $path): self
    {
        /** @var array<string, Role> $map */
        $map = require $path;
        return new self($map);
    }

    /** Most-specific-first: section+go+action, then section+go, then section alone. */
    public function requiredRoleFor(string $section, ?string $go, ?string $action): ?Role
    {
        if ($go !== null && $action !== null) {
            $key = "section:{$section}|go:{$go}|action:{$action}";
            if (isset($this->map[$key])) {
                return $this->map[$key];
            }
        }
        if ($go !== null) {
            $key = "section:{$section}|go:{$go}";
            if (isset($this->map[$key])) {
                return $this->map[$key];
            }
        }
        return $this->map["section:{$section}"] ?? null;
    }

    public function requiredRoleForProcessAction(?string $action, ?string $dbTable): ?Role
    {
        if ($action !== null && $action !== 'default') {
            return $this->map["process:action:{$action}"] ?? null;
        }
        if ($dbTable !== null && $dbTable !== 'default') {
            return $this->map["process:dbTable:{$dbTable}"] ?? null;
        }
        return null;
    }

    public function requiredRoleForFile(string $filename): ?Role
    {
        return $this->map["file:{$filename}"] ?? null;
    }

    public function requiredRoleForOutputSection(string $section): ?Role
    {
        return $this->map["output:section:{$section}"] ?? null;
    }
}
