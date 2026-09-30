<?php

declare(strict_types=1);

namespace JoomlaCodeception\Dto;

enum JoomlaClient: string
{
    case Administrator = 'administrator';
    case Site          = 'site';
    case Api           = 'api';
}
