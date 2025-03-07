<?php

namespace App\Enum;

enum EquipResult: string {
    case Equip = "equip";
    case Unequip = "unequip";
    case Cursed = "cursed";
    case DoNothing = "doNothing";
    case NoRoom = "noRoom";
}