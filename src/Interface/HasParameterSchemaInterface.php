<?php

namespace App\Interface;

use App\Action\Schema\ParameterSchema;

interface HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema;
}
