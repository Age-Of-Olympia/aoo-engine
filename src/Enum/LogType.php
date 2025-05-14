<?php

namespace App\Enum;

//synced with console.js L137
enum LogType: int {
    case Verbose = 0;
    case Log = 1;
    case Warning = 2;
    case Error = 3;
}