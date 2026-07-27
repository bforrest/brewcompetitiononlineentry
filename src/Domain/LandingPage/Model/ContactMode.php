<?php

declare(strict_types=1);

namespace Bcoem\Domain\LandingPage\Model;

enum ContactMode: string
{
    case Hidden = 'hidden';
    case Directory = 'directory';
    case Form = 'form';
}
