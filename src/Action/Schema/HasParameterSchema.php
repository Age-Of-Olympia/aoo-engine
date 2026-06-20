<?php

namespace App\Action\Schema;

interface HasParameterSchema
{
    public static function parameterSchema(): ParameterSchema;
}
