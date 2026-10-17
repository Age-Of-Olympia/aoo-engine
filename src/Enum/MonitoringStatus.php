<?php

namespace App\Enum;

/** Cases in severity order, worst first: the dashboard sorts by it. */
enum MonitoringStatus: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Running = 'running';
    case Ok = 'ok';

    public function label(): string
    {
        return match ($this) {
            self::Ok      => 'OK',
            self::Running => 'En cours',
            self::Warning => 'Attention',
            self::Error   => 'Erreur',
        };
    }
}
