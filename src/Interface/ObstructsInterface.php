<?php

namespace App\Interface;

/**
 * Ce qu'un TYPE obstrue une fois posé : le pas, le tir, les deux, ou rien.
 *
 * Les deux réponses sont indépendantes, et le catalogue le montre : dix murs
 * sur cinquante-trois arrêtent le pas sans arrêter la flèche — une palissade
 * basse, une balustrade. Une plante n'arrête ni l'un ni l'autre, une ressource
 * les deux.
 *
 * Implémenté par les deux catalogues, parce qu'un coffre est un OBJET et qu'un
 * mur est une race : posés, tous deux occupent une case et doivent répondre.
 * L'appelant demande au type, jamais à la table — même couture que
 * {@see OwnsCaracsInterface} et {@see LockableInterface}.
 *
 * Ne vaut que POSÉ. Ce qui traîne au sol n'obstrue rien, quoi qu'en dise son
 * type, et la localisation le dit déjà : `slot` sépare ce qui tient sa case de
 * ce qui y est simplement tombé.
 */
interface ObstructsInterface
{
    /** Barre-t-il le pas ? */
    public function blocksPassage(): bool;

    /** Arrête-t-il la flèche ? */
    public function blocksProjectiles(): bool;
}
