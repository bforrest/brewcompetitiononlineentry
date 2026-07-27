<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Presentation;

use Bcoem\Domain\LandingPage\Model\Archive;
use Bcoem\Domain\LandingPage\Model\BestOfShowSummary;
use Bcoem\Domain\LandingPage\Model\CompetitionLimits;
use Bcoem\Domain\LandingPage\Model\CompetitionLocations;
use Bcoem\Domain\LandingPage\Model\CompetitionRules;
use Bcoem\Domain\LandingPage\Model\Contact;
use Bcoem\Domain\LandingPage\Model\ContactMode;
use Bcoem\Domain\LandingPage\Model\ContestOverview;
use Bcoem\Domain\LandingPage\Model\JudgingProgress;
use Bcoem\Domain\LandingPage\Model\Sponsor;
use Bcoem\Domain\LandingPage\Model\WinnerSummary;
use Bcoem\Domain\Shared\ValueObject\WindowStatus;

final readonly class LandingPageViewModel
{
    /**
     * @param list<Alert> $alerts
     * @param list<Contact> $contacts
     * @param list<Sponsor> $sponsors
     * @param list<Archive> $archives
     */
    public function __construct(
        public ContestOverview $contest,
        public bool $loggedIn,
        public ?string $viewerName,
        public WindowStatus $registrationStatus,
        public WindowStatus $entryStatus,
        public WindowStatus $judgeStatus,
        public WindowStatus $dropoffStatus,
        public WindowStatus $shippingStatus,
        public CompetitionLimits $capacity,
        public JudgingProgress $judging,
        public bool $winnerResultsVisible,
        public CompetitionLocations $locations,
        public array $alerts,
        public array $contacts,
        public array $sponsors,
        public array $archives,
        public WinnerSummary $winners,
        public HeroPresentation $hero,
        public LandingPageLinks $links,
        public LandingPageCopy $copy,
        public ?CompetitionRules $rules = null,
        public ?LandingPageDates $dates = null,
        public ?LandingPageActions $actions = null,
        public ?LandingPageSections $sections = null,
        public ContactMode $contactMode = ContactMode::Directory,
        public ?BestOfShowSummary $bestOfShow = null,
        public string $locale = 'en-US',
    ) {
        self::assertListOf($alerts, Alert::class, 'alerts');
        self::assertListOf($contacts, Contact::class, 'contacts');
        self::assertListOf($sponsors, Sponsor::class, 'sponsors');
        self::assertListOf($archives, Archive::class, 'archives');
    }

    /** @param array<mixed> $items */
    private static function assertListOf(array $items, string $class, string $name): void
    {
        if (!array_is_list($items)) {
            throw new \InvalidArgumentException(ucfirst($name) . ' must be a list.');
        }
        foreach ($items as $item) {
            if (!$item instanceof $class) {
                throw new \InvalidArgumentException(ucfirst($name) . ' must contain only ' . $class . '.');
            }
        }
    }
}
