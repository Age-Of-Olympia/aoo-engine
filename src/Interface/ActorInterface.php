<?php

namespace App\Interface;

interface ActorInterface
{
  public function haveEffect(string $name): int;
  public function addEffect($name, $duration=0): void;
  public function endEffect(string $name): void;
  public function getCoords(): object;
  public function getRemaining(string $trait): int;
}