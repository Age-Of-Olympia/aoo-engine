<?php

$now = new DateTimeImmutable();
if ($now->format('j') === $now->format('t')) {
    (new \App\Service\CronService())->executeCron('monthly');
}
