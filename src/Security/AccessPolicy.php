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
        // Mirrors includes/process.inc.php's own dispatch order (lines
        // 198-431): only a fixed set of $action values (login, logout,
        // delete, barcode_check_in, ...) are special-cased there BEFORE
        // falling through to the generic $dbTable-driven CRUD dispatch -
        // every other $action value (add, edit, massupdate, ... - the bulk
        // of the app's actual writes, registration among them) is
        // dispatched purely on $dbTable, with $action never consulted
        // again. A process:action:{action} policy key therefore only
        // governs when $action is one of the ones process.inc.php itself
        // special-cases, i.e. the key actually exists in the map; treating
        // ANY non-null/non-default $action as authoritative (this
        // function's pre-Task-10 shape) silently denied every add/edit/...
        // CRUD write through process.inc.php, since none of those actions
        // has its own process:action:* entry - caught by Task 10's
        // equivalence gate (fresh-entrant registration 403ing).
        if ($action !== null && isset($this->map["process:action:{$action}"])) {
            return $this->map["process:action:{$action}"];
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
