<?php
/**
 * Layout chrome: top nav. Available variables (set by LayoutRenderer::wrap()):
 * - $identity: ?Identity
 * - $isPublic: bool
 */
?>
<?php if ($isPublic): ?>
<nav id="site-nav" class="site-nav family-sans navbar navbar-expand-md navbar-dark fixed-top bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="/"><i class="fas fa-home me-2" aria-hidden="true"></i><span class="visually-hidden">Home</span></a>
        <button type="button" class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#public-nav-toggler" aria-controls="public-nav-toggler" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
        <section class="collapse navbar-collapse" id="public-nav-toggler">
            <div class="navbar-nav ms-auto">
                <a class="nav-item nav-link" href="/#rules">Rules</a>
                <a class="nav-item nav-link" href="/#volunteers">Volunteers</a>
                <a class="nav-item nav-link" href="/#entry-info">Entry Info</a>
                <a class="nav-item nav-link" href="/#contact">Contact</a>
                <a class="nav-item nav-link" href="/index.php?section=login">Log In</a>
            </div>
        </section>
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
