<?php

declare(strict_types=1);

namespace JoomlaCodeception\Dto;

enum CsrfMode: string
{
    case Auto    = 'auto';
    case Form    = 'form';
    case Query   = 'query';
    case Json    = 'json';
    case Header  = 'header';
    case Invalid = 'invalid';
    case Missing = 'missing';
}
