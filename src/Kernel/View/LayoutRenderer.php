<?php
declare(strict_types=1);

namespace Bcoem\Kernel\View;

use Bcoem\Security\Identity;

/**
 * Renders modernized Slim views inside a clean, parameterized
 * reimplementation of the legacy app's chrome (head/nav/sidebar/footer) -
 * explicit inputs only, no ambient globals. See
 * Docs/superpowers/specs/2026-07-22-shared-layout-renderer-design.md for
 * the full design rationale (why not reuse legacy/index.legacy.php's real
 * nav.sec.php/sidebar.admin.php includes verbatim - they read a large set
 * of ambient variables computed earlier in legacy's global bootstrap chain).
 *
 * Also the single place that guarantees templates/helpers.php's e() helper
 * is loaded before any template (chrome or inner) runs.
 */
final class LayoutRenderer
{
    private const LAYOUT_DIR = __DIR__ . '/../../../templates/layout';
    private const HELPERS_PATH = __DIR__ . '/../../../templates/helpers.php';

    public function admin(Identity $identity, string $title, string $activeNav, string $templatePath, array $vars = []): string
    {
        return $this->wrap($identity, $title, $activeNav, true, $this->renderTemplate($templatePath, $vars));
    }

    public function authenticated(Identity $identity, string $title, string $templatePath, array $vars = []): string
    {
        return $this->wrap($identity, $title, '', false, $this->renderTemplate($templatePath, $vars));
    }

    public function public(string $title, string $templatePath, array $vars = []): string
    {
        return $this->wrapPublic($title, $this->renderTemplate($templatePath, $vars));
    }

    private function wrapPublic(string $title, string $content): string
    {
        return $this->wrap(null, $title, '', false, $content);
    }

    private function wrap(?Identity $identity, string $title, string $activeNav, bool $withSidebar, string $content): string
    {
        $cssCommonUrl = '/css/common.min.css';
        $themePref = $_SESSION['prefsTheme'] ?? 'default';
        $themeUrl = '/css/' . $themePref . '.min.css';

        ob_start();
        include self::LAYOUT_DIR . '/head.php';
        $head = ob_get_clean();

        ob_start();
        include self::LAYOUT_DIR . '/nav.php';
        $nav = ob_get_clean();

        $sidebar = '';
        $contentColumnClass = 'col-lg-12 col-md-12 col-sm-12 col-xs-12';
        if ($withSidebar) {
            ob_start();
            include self::LAYOUT_DIR . '/sidebar.php';
            $sidebar = ob_get_clean();
            $contentColumnClass = 'col-lg-9 col-md-8 col-sm-12 col-xs-12';
        }

        ob_start();
        include self::LAYOUT_DIR . '/footer.php';
        $footer = ob_get_clean();

        $titleHtml = e($title);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
{$head}
<body>
{$nav}
<div class="container-fluid">
    <div class="row">
        {$sidebar}
        <div class="{$contentColumnClass}">
            <div class="page-header">
                <h1>{$titleHtml}</h1>
            </div>
            {$content}
        </div>
    </div>
</div>
{$footer}
</body>
</html>
HTML;
    }

    private function renderTemplate(string $templatePath, array $vars): string
    {
        require_once self::HELPERS_PATH;

        if (!is_file($templatePath)) {
            throw new \RuntimeException("LayoutRenderer: template not found: {$templatePath}");
        }

        extract($vars);
        ob_start();
        include $templatePath;
        return ob_get_clean();
    }
}
