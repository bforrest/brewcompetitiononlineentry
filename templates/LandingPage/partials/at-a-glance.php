<?php
declare(strict_types=1);

use Bcoem\Domain\LandingPage\Presentation\LandingAction;
use Bcoem\Domain\Shared\ValueObject\WindowStatus;

/** @return array{0: string, 1: string, 2: string} [colorSuffix, faIcon, label] */
$statusBadge = static function (WindowStatus $status) use ($view): array {
    return match ($status) {
        WindowStatus::Open => ['success', 'fa-circle-check', $view->copy->statusOpen],
        WindowStatus::Upcoming => ['secondary', 'fa-clock', $view->copy->statusUpcoming],
        WindowStatus::Closed => ['danger', 'fa-circle-exclamation', $view->copy->statusClosed],
    };
};

/** Renders one "Open – <date> / Close – <date>" bullet list, matching legacy's card body pattern. */
$dateRangeBody = static function (\Bcoem\Domain\LandingPage\Presentation\LandingPageDateRange $range) use ($view): string {
    $items = '';
    if ($range->opens !== null) {
        $items .= '<li><strong>' . e($view->copy->opens) . '</strong> &ndash; ' . e($range->opens) . '</li>';
    }
    if ($range->closes !== null) {
        $items .= '<li><strong>' . e($view->copy->closes) . '</strong> &ndash; ' . e($range->closes) . '</li>';
    }
    return '<ul class="list-unstyled">' . $items . '</ul>';
};

$renderCard = static function (
    string $slug,
    string $title,
    string $color,
    string $icon,
    string $badgeLabel,
    string $bodyHtml,
    ?LandingAction $action,
) use ($view): void {
    ?>
    <div class="col" data-glance-card="<?= e($slug) ?>">
        <div class="card h-100 glance-card-bg">
            <div class="card-body glance-card-body">
                <h3 class="h5 card-title pt-2 pb-2 glance-header my-2 text-<?= e($color) ?>-glance-header"><?= e($title) ?></h3>
                <div class="position-absolute top-0 start-50 translate-middle badge bg-<?= e($color) ?>-glance-pill dark rounded-pill glance-status-pill"><i class="fa <?= e($icon) ?> pe-2"></i><?= e($badgeLabel) ?></div>
                <p class="card-text glance-card-text"><small><?= $bodyHtml ?></small></p>
                <?php if ($action !== null): ?>
                <div class="d-grid"><a class="btn btn-success" href="<?= e($action->url) ?>"<?php if ($action->url === '#login-modal'): ?> data-bs-toggle="modal" data-bs-target="#login-modal"<?php endif; ?>><?= e($action->label) ?></a></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
};
?>
<section id="at-a-glance" class="container-xxl py-4" aria-labelledby="glance-heading">
    <header class="landing-page-section-header"><h2 id="glance-heading" class="fs-1 fw-bold"><?= e($view->copy->atAGlance) ?></h2></header>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-3 g-4 justify-content-center">
        <?php
        $entryCountBody = '<ul class="list-unstyled">'
            . '<li><strong>' . e($view->copy->entries) . '</strong> &ndash; ' . e((string) $view->capacity->entryCount)
            . ($view->capacity->entryLimit !== null ? ' / ' . e((string) $view->capacity->entryLimit) : '') . '</li>'
            . '<li><strong>' . e($view->copy->paidEntries) . '</strong> &ndash; ' . e((string) $view->capacity->paidEntryCount)
            . ($view->capacity->paidEntryLimit !== null ? ' / ' . e((string) $view->capacity->paidEntryLimit) : '') . '</li>'
            . '</ul>';
        $renderCard('entries', $view->copy->entries, 'primary', 'fa-circle-info', $view->copy->cardStatusLabel, $entryCountBody, null);

        if ($view->dates !== null) {
            [$color, $icon, $badge] = $statusBadge($view->registrationStatus);
            $renderCard(
                'account-registration',
                $view->copy->registrationDates,
                $color,
                $icon,
                $badge,
                $dateRangeBody($view->dates->registration),
                $view->actions?->account,
            );

            [$color, $icon, $badge] = $statusBadge($view->entryStatus);
            $renderCard(
                'entry-registration',
                $view->copy->entryDates,
                $color,
                $icon,
                $badge,
                $dateRangeBody($view->dates->entries),
                $view->actions?->entry,
            );

            [$color, $icon, $badge] = $statusBadge($view->judgeStatus);
            $renderCard(
                'judge-registration',
                $view->copy->judgeRegistrationCardTitle,
                $color,
                $icon,
                $badge,
                $dateRangeBody($view->dates->judges),
                $view->actions?->judge,
            );

            [$color, $icon, $badge] = $statusBadge($view->judgeStatus);
            $renderCard(
                'steward-registration',
                $view->copy->stewardRegistrationCardTitle,
                $color,
                $icon,
                $badge,
                $dateRangeBody($view->dates->judges),
                $view->actions?->steward,
            );

            if ($view->dates->dropoff->opens !== null || $view->dates->dropoff->closes !== null) {
                [$color, $icon, $badge] = $statusBadge($view->dropoffStatus);
                $renderCard('dropoff', $view->copy->dropoffDates, $color, $icon, $badge, $dateRangeBody($view->dates->dropoff), null);
            }

            if ($view->locations->shippingEnabled && ($view->dates->shipping->opens !== null || $view->dates->shipping->closes !== null)) {
                [$color, $icon, $badge] = $statusBadge($view->shippingStatus);
                $renderCard('shipping', $view->copy->shippingDates, $color, $icon, $badge, $dateRangeBody($view->dates->shipping), null);
            }
        }

        if ($view->locations->awardsLocationName !== null || $view->locations->awardsLocation !== null || $view->locations->awardsDetails !== null) {
            $awardsBody = '<ul class="list-unstyled">';
            if ($view->locations->awardsLocationName !== null) {
                $awardsBody .= '<li><strong>' . e($view->locations->awardsLocationName) . '</strong></li>';
            }
            if ($view->locations->awardsLocation !== null) {
                $awardsBody .= '<li>' . e($view->locations->awardsLocation) . '</li>';
            }
            if ($view->dates?->awards !== null) {
                $awardsBody .= '<li><strong>' . e($view->copy->awardsTime) . '</strong> &ndash; ' . e($view->dates->awards) . '</li>';
            }
            if ($view->locations->awardsDetails !== null) {
                /* awardsDetails is trusted admin-authored HTML, matching legacy's own
                   unescaped rendering of the same contestAwards column
                   (pub/entry_info.pub.php:645) - not a new trust decision. */
                $awardsBody .= '<li>' . $view->locations->awardsDetails . '</li>';
            }
            $awardsBody .= '</ul>';
            $renderCard('awards', $view->copy->awards, 'primary', 'fa-circle-info', $view->copy->cardInfoLabel, $awardsBody, null);
        }
        ?>
    </div>
</section>
