<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Model;

enum WinnerMethod: int
{
    case Overall = 0;
    case Category = 1;
    case Subcategory = 2;
}
