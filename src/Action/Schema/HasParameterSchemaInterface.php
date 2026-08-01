<?php

namespace App\Action\Schema;

interface HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema;
}
