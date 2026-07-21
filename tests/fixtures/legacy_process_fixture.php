<?php
// Minimal stand-in for includes/process.inc.php. Proves LegacyProcessHandler's
// OWN bridging logic ($_GET/$_POST population, chdir to the target's
// directory, requiring a relative-path file) without pulling in the real
// process.inc.php's full side-effecting bootstrap chain and unconditional
// exit() call.
echo 'FIXTURE_OK action=' . ($_GET['action'] ?? 'MISSING') . ' posted=' . ($_POST['field'] ?? 'MISSING') . ' cwd_basename=' . basename(getcwd());
