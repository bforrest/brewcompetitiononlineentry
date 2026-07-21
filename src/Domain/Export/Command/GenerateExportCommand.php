<?php
declare(strict_types=1);

namespace Bcoem\Domain\Export\Command;

use Symfony\Component\Validator\Constraints as Assert;

final class GenerateExportCommand
{
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['csv', 'html', 'pdf', 'xml'])]
    public string $format;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        'paid', 'nopay', 'required', 'winners', 'circuit', 'all',
        'judges', 'stewards', 'staff', 'avail_judges', 'avail_stewards',
        'judging_scores', 'judging_scores_bos'
    ])]
    public string $filter;

    #[Assert\Choice(choices: ['default', 'all', 'not_received'])]
    public string $view = 'default';

    #[Assert\Optional]
    #[Assert\Type(type: 'string')]
    public ?string $archiveSuffix = null;

    public function __construct(
        string $format,
        string $filter,
        string $view = 'default',
        ?string $archiveSuffix = null
    ) {
        $this->format = $format;
        $this->filter = $filter;
        $this->view = $view;
        $this->archiveSuffix = $archiveSuffix;
    }
}
