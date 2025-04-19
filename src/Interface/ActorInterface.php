<?php

namespace App\Interface;

use App\Enum\EquipResult;
use Item;

interface ActorInterface
{
  public function haveEffect(string $name): int;
  public function addEffect($name, $duration=0): void;
  public function endEffect(string $name): void;
  public function have_option(string $name): int;
  public function get_caracs(bool $nude=false): bool;
  public function getCoords(): object;
  public function getRemaining(string $trait): int;
  public function equip(Item $item, bool $doNotRefresh = false): EquipResult;
  public function getMunition(Item $object, bool $equiped=false): ?Item;
  public function putBonus($bonus) : bool;
  public function put_malus($malus): void;
  public function putFat($bonus) : void;
  public function go($goCoords);
}