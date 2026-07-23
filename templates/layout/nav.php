<?php
/**
 * Layout chrome: top nav. Available variables (set by LayoutRenderer::wrap()):
 * - $identity: ?Identity
 * - $isPublic: bool
 */
?>
<?php if ($isPublic): ?>
<nav class="navbar navbar-inverse navbar-fixed-top">
    <div class="container">
        <ul class="nav navbar-nav">
            <li><a href="/"><span class="glyphicon glyphicon-home" aria-hidden="true"></span><span class="sr-only">Home</span></a></li>
        </ul>
        <ul class="nav navbar-nav navbar-right">
            <li><a href="/index.php?section=entry#rules">Rules</a></li>
            <li><a href="/index.php?section=volunteers">Volunteers</a></li>
            <li><a href="/index.php?section=entry">Entry Info</a></li>
            <li><a href="/index.php?section=contact">Contact</a></li>
            <li><a href="/index.php?section=login">Log In</a></li>
        </ul>
    </div>
</nav>
<?php else: ?>
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
<?php endif; ?>
