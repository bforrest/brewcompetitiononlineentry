<?php
/**
 * Layout chrome: top nav. Available variables (set by LayoutRenderer::wrap()):
 * - $identity: ?Identity
 */
?>
<nav class="navbar navbar-default">
    <div class="container-fluid">
        <div class="navbar-header">
            <a class="navbar-brand" href="/">Brew Competition Online Entry &amp; Management</a>
        </div>
        <ul class="nav navbar-nav navbar-right">
            <?php if ($identity !== null): ?>
            <li><span class="navbar-text"><?= e($identity->username ?? '') ?></span></li>
            <li><a href="/includes/process.inc.php?section=logout">Log out</a></li>
            <?php else: ?>
            <li><a href="/register">Register</a></li>
            <li><a href="/index.php?section=login">Log in</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>
