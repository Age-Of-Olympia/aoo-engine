<?php

namespace App\Enum;

enum LogType: int {
    case Verbose = 0;
    case Log = 1;
    case Warning = 2;
    case Error = 3;
}