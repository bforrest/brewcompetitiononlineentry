<?php if ($isPublic): ?>
<footer class="site-footer bg-dark text-light justify-content-center container-fluid fixed-bottom pt-3 d-print-none">
    <p class="text-center"><span class="d-none d-lg-inline"><?= e($contestTitle ?? '') ?> &ndash; </span><a href="http://www.brewingcompetitions.com" target="_blank">BCOE&amp;M</a> 3.0.3<span class="d-none d-lg-inline"> &ndash; Amateur Competition Edition</span> <span class="far fa-copyright fa-xs"></span>2009-<?= date('Y') ?></p>
</footer>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"></script>
<?php else: ?>
<footer class="navbar navbar-default navbar-fixed-bottom">
    <p class="navbar-text col-md-12 col-sm-12 col-xs-12 text-muted small">
        &copy; <?= date('Y') ?> Brew Competition Online Entry &amp; Management
    </p>
</footer>
<?php endif; ?>
