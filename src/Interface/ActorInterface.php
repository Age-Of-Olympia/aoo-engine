<?php

namespace App\Interface;

use App\Enum\EquipResult;
use Classes\Item;

interface ActorInterface
{
  public function getId(): int;
  public function haveEffect(string $name): int;
  public function addEffect($name, $duration=0): void;
  public function endEffect(string $name): void;
  public function have_option(string $name): int;
  public function have_effects_to_purge(): bool;
  public function get_caracs(bool $nude=false): bool;
  public function getCoords(bool $refresh = true): object;
  public function getRemaining(string $trait): int;
  public function equip(Item $item, bool $doNotRefresh = false): EquipResult;
  public function getMunition(Item $object, bool $equiped=false): ?Item;
  public function putBonus($bonus) : bool;
  public function put_malus($malus): void;
  public function put_xp(int $xp): void;
  public function put_assist(ActorInterface $target, int $totalDamages): void;
  public function putEnergie($bonus) : void;
  public function go($goCoords);
  public function move_player($coords);
  public function get_action_xp($target);
  public function get_data(bool $forceRefresh=true);
  public function get_upgrades();
  public function purge_effects();

  public function add_action($name, $charges=false);
  public function end_action($name);
  public function add_option($name);
  public function get_options();
  public function get_effects();
  public function get_actions();

  public static function refresh_list();
  public function get_row();
  public function check_missive_permission($target);
  public function drop($item, $n);
  public function put_pf($pf);
  

}