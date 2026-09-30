<?php

declare(strict_types=1);

namespace JoomlaCodeception\Dto;

enum ResponseType: string
{
    case Html   = 'html';
    case Json   = 'json';
    case Binary = 'binary';
    case Any    = 'any';
}
