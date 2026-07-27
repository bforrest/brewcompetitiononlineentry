<?php
declare(strict_types=1);

namespace Bcoem\Kernel\View;

use Bcoem\Domain\LandingPage\Presentation\LandingPageViewModel;
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

    public function public(string $title, string $contestTitle, string $templatePath, array $vars = []): string
    {
        return $this->wrapPublic($title, $contestTitle, $this->renderTemplate($templatePath, $vars));
    }

    public function landing(LandingPageViewModel $view, string $templatePath): string
    {
        return $this->wrapLanding(
            $view,
            $this->renderTemplate($templatePath, ['view' => $view]),
        );
    }

    private function wrapPublic(string $title, string $contestTitle, string $content): string
    {
        return $this->wrap(null, $title, '', false, $content, $contestTitle);
    }

    private function wrapLanding(LandingPageViewModel $view, string $content): string
    {
        $identity = null;
        $title = $view->contest->name;
        $contestTitle = $view->contest->name;
        $isPublic = true;
        $isLanding = true;

        ob_start();
        include self::LAYOUT_DIR . '/head.php';
        $head = ob_get_clean();

        ob_start();
        include self::LAYOUT_DIR . '/nav.php';
        $nav = ob_get_clean();

        ob_start();
        include self::LAYOUT_DIR . '/footer.php';
        $footer = ob_get_clean();

        $contestTitleHtml = e($contestTitle);
        $heroImageUrl = e($view->hero->imageUrl);
        $heroHeading = e($view->hero->heading);
        $heroSubheading = e($view->hero->subheading);
        $hostName = e($view->contest->hostName);
        $hostPresentation = $view->contest->hostWebsite === null
            ? $hostName
            : '<a class="link-light" href="' . e($view->contest->hostWebsite) . '" target="_blank" rel="noopener noreferrer">' . $hostName . '</a>';
        $hostLocation = $view->contest->hostLocation === null
            ? ''
            : ' <span class="text-light">&mdash; ' . e($view->contest->hostLocation) . '</span>';
        $hostLogo = $view->contest->logoPath === null
            ? ''
            : '<img class="img-fluid mb-3" src="' . e($view->contest->logoPath) . '" alt="' . $hostName . ' logo">';
        $locale = e($view->locale);
        $hostedBy = e($view->copy->hostedBy);

        return <<<HTML
<!DOCTYPE html>
<html lang="{$locale}">
{$head}
<body>
<header id="home" class="site-header">
{$nav}
<div id="sticky-home" class="contains-link d-print-none"><a href="#home" aria-label="Return to top"><i class="fas fa-arrow-circle-up fa-2x" aria-hidden="true"></i></a></div>
<section id="hero" class="landing-hero text-light bg-dark pt-5 pb-4" aria-labelledby="landing-title" style="background-image: linear-gradient(rgba(0, 0, 0, 0.45), rgba(0, 0, 0, 0.75)), url('{$heroImageUrl}')">
    <div class="container-xxl pt-4">
        {$hostLogo}
        <img class="visually-hidden" src="{$heroImageUrl}" alt="" role="presentation">
        <h1 id="landing-title" class="fw-bold animate__animated animate__fadeInDown">{$heroHeading}</h1>
        <p class="lead mb-0">{$heroSubheading}</p>
        <p class="mb-0">{$hostedBy} {$hostPresentation}{$hostLocation}</p>
    </div>
</section>
</header>
{$content}
{$footer}
<script>
(() => {
    const stickyHome = document.getElementById('sticky-home');
    const updateStickyHome = () => {
        if (stickyHome) {
            stickyHome.style.display = window.scrollY >= 300 ? 'block' : 'none';
        }
    };
    window.addEventListener('scroll', updateStickyHome, { passive: true });
    updateStickyHome();

    const loginModal = document.getElementById('login-modal');
    loginModal?.addEventListener('shown.bs.modal', () => {
        document.getElementById('login-user-name')?.focus();
    });
})();
</script>
</body>
</html>
HTML;
    }

    private function wrap(?Identity $identity, string $title, string $activeNav, bool $withSidebar, string $content, ?string $contestTitle = null): string
    {
        $cssCommonUrl = '/css/common.min.css';
        $themePref = $_SESSION['prefsTheme'] ?? 'default';
        $themeUrl = '/css/' . $themePref . '.min.css';
        $isPublic = $contestTitle !== null;

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

        if ($isPublic) {
            $contestTitleHtml = e($contestTitle);

            return <<<HTML
<!DOCTYPE html>
<html lang="en">
{$head}
<body>
<header id="home" class="site-header">
{$nav}
<div id="sticky-home" class="contains-link d-print-none"><a href="#home" aria-label="Return to top"><i class="fas fa-arrow-circle-up fa-2x"></i></a></div>
<div id="salutation" class="text-light bg-black pt-4 pb-3 d-print-none">
    <section class="container-xxl">
        <h1 class="fw-bold animate__animated animate__fadeInDown">{$contestTitleHtml}</h1>
    </section>
</div>
</header>
<div id="main-content" class="container-xxl">
    {$content}
</div>
{$footer}
</body>
</html>
HTML;
        }

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
