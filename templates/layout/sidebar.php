<?php
/**
 * Layout chrome: admin sidebar navigation. Available variables (set by
 * LayoutRenderer::wrap()):
 * - $activeNav: string
 *
 * A small, new, static list of real admin section links (hrefs confirmed
 * against admin/sidebar.admin.php's own dashboard links) - NOT a port of
 * that file, which is a 513-line live-data status dashboard, not a nav
 * menu. See Docs/superpowers/specs/2026-07-22-shared-layout-renderer-design.md.
 */
$links = [
    'entries' => ['label' => 'Entries', 'href' => '/index.php?section=admin&go=entries'],
    'participants' => ['label' => 'Participants', 'href' => '/index.php?section=admin&go=participants'],
    'judging' => ['label' => 'Judging Tables', 'href' => '/judging/tables'],
    'preferences' => ['label' => 'Preferences', 'href' => '/index.php?section=admin&go=preferences'],
];
?>
<div class="sidebar col-lg-3 col-md-4 col-sm-12 col-xs-12">
    <ul class="nav nav-pills nav-stacked">
        <?php foreach ($links as $key => $link): ?>
            <li<?= $activeNav === $key ? ' class="active"' : '' ?>>
                <a href="<?= e($link['href']) ?>"><?= e($link['label']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
