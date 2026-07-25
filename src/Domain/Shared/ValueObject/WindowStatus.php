<?php

declare(strict_types=1);

namespace Bcoem\Domain\Shared\ValueObject;

enum WindowStatus: string
{
    case Upcoming = 'upcoming';
    case Open = 'open';
    case Closed = 'closed';
}
