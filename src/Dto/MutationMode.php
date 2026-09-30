<?php

declare(strict_types=1);

namespace JoomlaCodeception\Dto;

enum MutationMode: string
{
    case Insert  = 'insert';
    case Update  = 'update';
    case Restore = 'restore';
}
