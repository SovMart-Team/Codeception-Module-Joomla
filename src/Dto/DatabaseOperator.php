<?php

declare(strict_types=1);

namespace JoomlaCodeception\Dto;

enum DatabaseOperator: string
{
    case Equal          = '=';
    case NotEqual       = '!=';
    case IsNull         = 'IS NULL';
    case IsNotNull      = 'IS NOT NULL';
    case In             = 'IN';
    case NotIn          = 'NOT IN';
    case Like           = 'LIKE';
    case NotLike        = 'NOT LIKE';
    case Less           = '<';
    case LessOrEqual    = '<=';
    case Greater        = '>';
    case GreaterOrEqual = '>=';
}
