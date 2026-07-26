<?php

namespace Tests\Player;

use App\Service\BuildingService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Ce qu'une entité a à dire vit déjà quelque part : `players.text`, le
 * message du jour. Un bâtiment ÉTANT une ligne de `players`, il a la
 * colonne — et au recensement de l'expérimental, ses 13 549 bâtiments
 * portaient tous le même texte, le défaut de création. La place était
 * donc libre : l'inscription d'une pancarte n'est pas un champ de plus,
 * c'est le MDJ d'un objet.
 *
 * D'où la seule subtilité à tenir : tant que le texte vaut ce défaut,
 * l'objet n'a RIEN à dire — sans quoi les 13 549 murs du monde se
 * mettraient à afficher « Je suis nouveau, frappez-moi! ».
 */
#[Group('entities-golden-master')]
class BuildingInscriptionTest extends LegacyPlayerFixtureTestCase
{
    public function testTheCreationDefaultIsNotAnInscription(): void
    {
        $entity = $this->createRealPlayer('GmInscr');
        $entity->get_data();

        $this->assertSame(
            BuildingService::DEFAULT_TEXT,
            trim((string) $entity->data->text),
            'une entité neuve porte bien le défaut'
        );
        $this->assertSame(
            '',
            BuildingService::inscriptionOf($entity),
            'et ce défaut ne compte pas comme quelque chose à lire'
        );
    }

    public function testATextWrittenOnTheEntityIsItsInscription(): void
    {
        $entity = $this->createRealPlayer('GmInscr');
        $entity->get_data();

        $this->link->executeStatement(
            'UPDATE players SET text = ? WHERE id = ?',
            ['Route de Thèbes, trois lieues.', (int) $entity->id]
        );
        BuildingService::purgeEntityCaches((int) $entity->id);

        $reloaded = \App\Factory\PlayerFactory::legacy((int) $entity->id);
        $reloaded->get_data();

        $stored = BuildingService::inscriptionOf($reloaded);

        /* get_data() sert le MDJ encodé en entités — comportement de
         * longue date, que Str::richText rend inerte sans le réencoder
         * (double_encode: false). On lit donc le texte au travers. */
        $this->assertSame(
            'Route de Thèbes, trois lieues.',
            html_entity_decode($stored, ENT_QUOTES, 'UTF-8'),
            'la virgule ne découpe plus rien : c\'est une colonne de texte'
        );
        $this->assertStringContainsString(',', $stored, 'et elle traverse le stockage intacte');
    }

    /**
     * Un bâtiment neuf se tait : rien n'est encore inscrit dessus. La
     * phrase de création a du sens pour un personnage qui vient de
     * naître, aucune pour un mur — et elle occupe l'emplacement de
     * l'inscription.
     */
    public function testANewBuildingSaysNothingAtAll(): void
    {
        $this->requireBuildingsOrSkip();
        $id = $this->placeStructure('palissade', 0, 4);

        $building = \App\Factory\PlayerFactory::legacy($id);
        $building->get_data();

        $this->assertSame('', trim((string) $building->data->text), 'aucun texte à la pose');
        $this->assertSame('', BuildingService::inscriptionOf($building));
    }

    public function testAnEmptyTextSaysNothing(): void
    {
        $entity = $this->createRealPlayer('GmInscr');
        $entity->get_data();

        $this->link->executeStatement('UPDATE players SET text = ? WHERE id = ?', ['   ', (int) $entity->id]);
        BuildingService::purgeEntityCaches((int) $entity->id);

        $reloaded = \App\Factory\PlayerFactory::legacy((int) $entity->id);
        $reloaded->get_data();

        $this->assertSame('', BuildingService::inscriptionOf($reloaded));
    }
}
