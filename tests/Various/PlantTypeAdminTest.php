<?php

namespace Tests\Various;

use App\Entity\Harvestable;
use App\Entity\PlantType;
use App\Entity\Race;
use App\Service\AdminMenuAccessService;
use App\View\Admin\TypeEditorFace;
use PHPUnit\Framework\TestCase;

/**
 * Une plante se règle dans l'admin — page atteignable, champs affichés.
 *
 * Deux défauts jumeaux ont motivé ces cas, et ils avaient la même forme : le
 * FORMULAIRE et l'ENREGISTREMENT ne parlaient pas de la même chose.
 *
 *  - le formulaire n'affichait le rendement que pour les ressources, quand
 *    l'enregistrement l'acceptait de tout récoltable : modifier une plante
 *    effaçait son rendement, puisque le POST ne portait pas le champ ;
 *  - la création lisait la nature par un ternaire qui ignorait les plantes :
 *    une plante créée depuis sa page naissait bâtiment.
 *
 * Aucun des deux n'aurait été vu par un test de classe : il fallait relier le
 * visage, ce qu'il affiche et ce qu'il enregistre.
 */
class PlantTypeAdminTest extends TestCase
{
    /**
     * Le contrôle d'accès lit ses dérogations en base : sans elle, il sort du
     * processus au lieu de lever. D'où le bootstrap, et le skip propre.
     */
    protected function setUp(): void
    {
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
            \App\Entity\EntityManagerFactory::getEntityManager()->getConnection()->fetchOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unreachable: ' . $e->getMessage());
        }
    }

    /** Le visage des plantes règle un rendement, comme celui des ressources. */
    public function testBothHarvestingFacesOfferAYield(): void
    {
        $this->assertTrue(TypeEditorFace::plant()->harvests(), 'une plante se récolte');
        $this->assertTrue(TypeEditorFace::resource()->harvests());

        $this->assertFalse(TypeEditorFace::building()->harvests());
        $this->assertFalse(TypeEditorFace::scenery()->harvests());
        $this->assertFalse(TypeEditorFace::character()->harvests());
    }

    /**
     * La nature d'un visage a UNE source, et elle couvre les plantes.
     *
     * C'est le ternaire fautif : il rendait « edifice » pour tout ce qui
     * n'était ni décor ni ressource.
     */
    public function testEachFaceKnowsTheNatureItCreates(): void
    {
        $this->assertSame(TypeEditorFace::NATURE_PLANT, TypeEditorFace::plant()->nature());
        $this->assertSame(TypeEditorFace::NATURE_RESOURCE, TypeEditorFace::resource()->nature());
        $this->assertSame(TypeEditorFace::NATURE_DECOR, TypeEditorFace::scenery()->nature());
        $this->assertSame('edifice', TypeEditorFace::building()->nature());
    }

    /** Créer depuis la page des plantes donne bien une plante. */
    public function testCreatingFromThePlantPageBuildsAPlant(): void
    {
        $face = TypeEditorFace::plant();

        $type = Race::ofFamily($face->isStructure() ? 'structure' : 'character', $face->nature());

        $this->assertInstanceOf(PlantType::class, $type, 'et non un bâtiment');
        $this->assertInstanceOf(Harvestable::class, $type);
    }

    /** Le formulaire renvoie la nature, sans quoi l'enregistrement la perd. */
    public function testThePlantFormCarriesItsNatureBack(): void
    {
        $fields = TypeEditorFace::plant()->formFields();

        $this->assertStringContainsString('name="kind" value="structure"', $fields);
        $this->assertStringContainsString('name="nature" value="plante"', $fields);
    }

    /**
     * La page est ATTEIGNABLE : déclarée au contrôle d'accès.
     *
     * Elle existait sans être enregistrée nulle part — donc introuvable au
     * menu, et refusée par la garde d'accès. Une page que personne ne peut
     * ouvrir n'est pas une configuration.
     */
    public function testThePlantPageIsDeclaredToTheAccessControl(): void
    {
        $page = basename(TypeEditorFace::plant()->page);
        $this->assertSame('plant-types.php', $page);

        /* Surtout PAS `getRequiredLevel()` : une page inconnue y retombe sur
         * SUPERADMIN, donc l'appel réussit aussi bien pour une page qui
         * n'existe pas. C'est le registre lui-même qu'il faut interroger. */
        $declared = array_column((new AdminMenuAccessService())->getConfigurableMenus(), 'page');

        $this->assertContains(
            $page,
            $declared,
            'la page des types de plantes doit être déclarée : sinon elle est introuvable au menu'
        );
    }
}
