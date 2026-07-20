<?php
// Minimal stand-in for a real legacy root-level entry point. Proves
// LegacyBootstrap/LegacyPageHandler's OWN bridging logic (query-param
// copying into $_GET, chdir(ROOT), requiring a root-relative file) without
// pulling in the real app's side-effecting bootstrap chain. See the
// docblock on LegacyPageHandler for why this exists instead of testing
// against the real index.php directly.
echo 'FIXTURE_OK section=' . ($_GET['section'] ?? 'MISSING') . ' go=' . ($_GET['go'] ?? 'MISSING');
