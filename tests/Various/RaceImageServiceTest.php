<?php

namespace Tests\Various;

use App\Enum\ImageType;
use App\Service\RaceImageService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\SeedsCharactersTrait;

/**
 * Avatars et portraits de race (panneau « Avatars & portraits ») :
 * inventaire avec diagnostics (dimensions hors canon, portrait sans
 * miniature, image choisie par des joueurs mais absente), upload
 * redimensionné au canon + compteur de race, suppression gardée.
 * Arborescence img/ temporaire ; usages depuis la vraie base — skip propre
 * quand elle est inaccessible.
 */
class RaceImageServiceTest extends TestCase
{
    use SeedsCharactersTrait;

    private RaceImageService $service;
    private string $root;

    protected function setUp(): void
    {
        $this->bootstrapOrSkip();
        $this->root = sys_get_temp_dir() . '/race_images_' . uniqid();
        mkdir($this->root . '/img/avatars/nain', 0777, true);
        mkdir($this->root . '/img/portraits/nain', 0777, true);
        $this->service = new RaceImageService(null, $this->root);
    }

    protected function tearDown(): void
    {
        global $link;
        if (isset($link) && $link instanceof Connection) {
            $this->removeSeededCharacters($link);
        }

        foreach (glob($this->root . '/img/*/*/*') ?: [] as $file) {
            unlink($file);
        }
        foreach ([...glob($this->root . '/img/*/*', GLOB_ONLYDIR) ?: [],
                  ...glob($this->root . '/img/*', GLOB_ONLYDIR) ?: []] as $dir) {
            rmdir($dir);
        }
        rmdir($this->root . '/img');
        rmdir($this->root);
    }

    private function writeImage(string $relative, int $width, int $height): void
    {
        $image = imagecreatetruecolor($width, $height);
        imagejpeg($image, $this->root . '/' . $relative);
        imagedestroy($image);
    }

    public function testInventoryFlagsOffCanonAndMissingMini(): void
    {
        [$canonWidth, $canonHeight] = ImageType::PORTRAIT->dimensions();
        $this->writeImage('img/portraits/nain/900.jpeg', $canonWidth, $canonHeight); // canon, sans mini
        $this->writeImage('img/portraits/nain/901.jpeg', 100, 100);                  // hors canon
        $this->writeImage('img/portraits/nain/901_mini.jpeg', 50, 79);

        $entries = $this->service->inventory(ImageType::PORTRAIT, 'nain');
        $byFile = array_column($entries, null, 'file');

        $this->assertStringContainsString('miniature', $byFile['900.jpeg']['problems'][0]);
        $this->assertStringContainsString('dimensions 100×100', $byFile['901.jpeg']['problems'][0]);
        $this->assertTrue($byFile['901.jpeg']['hasMini']);
        $this->assertArrayNotHasKey('901_mini.jpeg', $byFile, 'les miniatures ne sont pas des entrées');
    }

    public function testInventoryReportsPlayersPointingAtMissingFiles(): void
    {
        /* Un joueur qui DÉSIGNE un portrait absent de l'arborescence : le cas
         * le pose, au lieu d'espérer qu'un compte du monde le fasse. */
        global $link;
        $this->seedCharacter($link, 'ame', ['portrait' => 'img/portraits/ame/introuvable.jpeg']);

        mkdir($this->root . '/img/portraits/ame', 0777, true);
        $entries = $this->service->inventory(ImageType::PORTRAIT, 'ame');

        $missing = array_filter($entries, fn(array $entry) => $entry['missing']);
        $this->assertNotEmpty($missing, 'les chemins choisis par des joueurs sans fichier sont remontés');
    }

    public function testUploadResizesToCanonAndIncrementsCounter(): void
    {
        $tmp = $this->root . '/source.png';
        $image = imagecreatetruecolor(300, 200);
        imagepng($image, $tmp);

        $created = $this->service->upload(ImageType::AVATAR, 'nain', $tmp);
        unlink($tmp);

        $size = getimagesize($this->root . '/img/avatars/nain/' . $created);
        $this->assertSame([50, 50], [$size[0], $size[1]], 'redimensionné au canon avatar');

        // le compteur de la race a avancé : un second envoi prend le numéro suivant
        $image2 = imagecreatetruecolor(60, 60);
        imagepng($image2, $tmp);
        $second = $this->service->upload(ImageType::AVATAR, 'nain', $tmp);
        unlink($tmp);
        $this->assertNotSame($created, $second);
    }

    public function testUploadKeepsPngFormatAndTransparency(): void
    {
        $tmp = $this->root . '/source.png';
        $image = imagecreatetruecolor(60, 60);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagepng($image, $tmp);
        imagedestroy($image);

        $created = $this->service->upload(ImageType::AVATAR, 'nain', $tmp);
        unlink($tmp);

        $this->assertStringEndsWith('.png', $created, 'un png ne doit pas être ré-encodé en jpeg');
        $result = imagecreatefrompng($this->root . '/img/avatars/nain/' . $created);
        $alpha = (imagecolorat($result, 25, 25) >> 24) & 0x7F;
        $this->assertSame(127, $alpha, 'la transparence survit au redimensionnement');
    }

    public function testUploadKeepsJpegAsJpeg(): void
    {
        $tmp = $this->root . '/source.jpeg';
        $image = imagecreatetruecolor(60, 60);
        imagejpeg($image, $tmp);
        imagedestroy($image);

        $created = $this->service->upload(ImageType::AVATAR, 'nain', $tmp);
        unlink($tmp);

        $this->assertStringEndsWith('.jpeg', $created);
        $this->assertSame(IMAGETYPE_JPEG, getimagesize($this->root . '/img/avatars/nain/' . $created)[2]);
    }

    public function testDeleteRefusesImagesChosenByPlayers(): void
    {
        /* Un portrait CHOISI par quelqu'un, posé ici : c'est le refus de le
         * supprimer qu'on vérifie, pas la présence d'un joueur historique. */
        global $link;
        $path = 'img/portraits/nain/choisi_par_un_joueur.jpeg';
        $this->seedCharacter($link, 'nain', ['portrait' => $path]);
        [$dir, $race, $file] = array_slice(explode('/', $path), 1);
        mkdir($this->root . '/img/' . $dir . '/' . $race, 0777, true);
        $this->writeImage('img/' . $dir . '/' . $race . '/' . $file, 210, 320);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/joueur/');
        $this->service->delete(ImageType::PORTRAIT, $race, $file);
    }

    private function bootstrapOrSkip(): void
    {
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
            require_once __DIR__ . '/../../config/functions.php';
            require_once __DIR__ . '/../../config/constants.php';
        } catch (\Throwable $e) {
            $this->markTestSkipped('Legacy bootstrap failed: ' . $e->getMessage());
        }

        global $link;
        if (!isset($link) || !$link instanceof Connection) {
            $this->markTestSkipped('Global $link not populated by bootstrap.');
        }

        try {
            $link->executeQuery("SELECT 1 FROM players LIMIT 1");
        } catch (\Throwable $e) {
            $this->markTestSkipped('players table unreachable: ' . $e->getMessage());
        }
    }
}
