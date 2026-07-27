<?php
/**
 * Layout chrome: top nav. Available variables (set by LayoutRenderer::wrap()):
 * - $identity: ?Identity
 * - $isPublic: bool
 * - $isLanding: bool, only for the typed landing page
 * - $view: LandingPageViewModel, only for the typed landing page
 */
?>
<?php if ($isPublic && ($isLanding ?? false)): ?>
<nav id="site-nav" class="site-nav family-sans navbar navbar-expand-md navbar-dark fixed-top bg-dark" aria-labelledby="landing-title">
    <div class="container-fluid">
        <a class="navbar-brand" href="#home"><i class="fas fa-home me-2" aria-hidden="true"></i><span class="visually-hidden">Home</span></a>
        <button type="button" class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#public-nav-toggler" aria-controls="public-nav-toggler" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
        <section class="collapse navbar-collapse" id="public-nav-toggler">
            <div class="navbar-nav ms-auto">
                <a class="nav-item nav-link" href="#rules"><?= e($view->copy->rules) ?></a>
                <a class="nav-item nav-link" href="#volunteers"><?= e($view->copy->volunteers) ?></a>
                <a class="nav-item nav-link" href="#entry-info"><?= e($view->copy->entryInfo) ?></a>
                <a class="nav-item nav-link" href="<?= e($view->links->contact) ?>"><?= e($view->copy->contact) ?></a>
                <?php if ($view->sponsors !== []): ?>
                <a class="nav-item nav-link" href="<?= e($view->links->sponsors) ?>"><?= e($view->copy->sponsors) ?></a>
                <?php endif; ?>
                <?php if ($view->archives !== []): ?>
                <button class="btn btn-outline-light ms-md-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#archive-list" aria-controls="archive-list"><?= e($view->copy->pastWinners) ?></button>
                <?php endif; ?>
                <?php if ($view->loggedIn): ?>
                <?php if ($view->viewerName !== null): ?><span class="navbar-text px-2">Hello, <?= e($view->viewerName) ?></span><?php endif; ?>
                <a class="nav-item nav-link" href="<?= e($view->links->account) ?>">Account</a>
                <a class="nav-item nav-link" href="<?= e($view->links->logout) ?>"><?= e($view->copy->logout) ?></a>
                <?php else: ?>
                <a class="nav-item nav-link" href="<?= e($view->links->register) ?>"><?= e($view->copy->register) ?></a>
                <a class="nav-item nav-link" href="<?= e($view->links->login) ?>" data-bs-toggle="modal" data-bs-target="#login-modal" aria-controls="login-modal"><?= e($view->copy->login) ?></a>
                <?php endif; ?>
            </div>
        </section>
    </div>
</nav>
<?php elseif ($isPublic): ?>
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
