<?php

declare(strict_types=1);

namespace JoomlaCodeception\Dto;

enum RedirectPolicy: string
{
    case Stop   = 'stop';
    case Follow = 'follow';
}
