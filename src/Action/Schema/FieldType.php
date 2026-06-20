<?php

namespace App\Action\Schema;

enum FieldType: string
{
    case TRAIT = 'trait';
    case INT = 'int';
    case BOOL = 'bool';
    case STRING = 'string';
    case ENUM = 'enum';
    case TRAIT_OR_INT = 'trait_or_int';
    case LIST = 'list';
}
