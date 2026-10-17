<?php

namespace App\Enum;

enum MonitoringStatus: string
{
    case Ok = 'ok';
    case Running = 'running';
    case Warning = 'warning';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Ok      => 'OK',
            self::Running => 'En cours',
            self::Warning => 'Attention',
            self::Error   => 'Erreur',
        };
    }

    /** Bootstrap badge/border variant. */
    public function variant(): string
    {
        return match ($this) {
            self::Ok      => 'success',
            self::Running => 'info',
            self::Warning => 'warning',
            self::Error   => 'danger',
        };
    }
}
