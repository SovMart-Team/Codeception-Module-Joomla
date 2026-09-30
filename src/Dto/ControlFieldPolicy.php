<?php

declare(strict_types=1);

namespace JoomlaCodeception\Dto;

enum ControlFieldPolicy: string
{
    case Merge       = 'merge';
    case FixtureOnly = 'fixture_only';
    case Remove      = 'remove';
}
