<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Presentation;

enum AlertLevel: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Danger = 'danger';
    case Success = 'success';
}
