<?php
use Classes\AdminCommand;
use Classes\Argument;
use Classes\Db;
use Classes\File;
use Classes\Json;
use Classes\Player;

class RgpdCmd extends AdminCommand
{
    public function __construct() {
        parent::__construct("rgpd",[new Argument('action',false), new Argument('mat',false)]);
        parent::setDescription(<<<EOT
verifie [check] ou anonymise [anonymise] les donnees d'un personnage [mat]
Exemple:
> rgpd check 1
> rgpd anonymise 1
> rgpd anonymise orcrist

EOT);
    }

    //mail => vide
    //plain_mail => vide
    //text => "[Supprimé]"
    //story => "[Supprimé]"
    //psw => suprimé (hash donc inavalid / check connexion)
    // => "[Supprimé]"
    //forum/missive => [Supprimé en respect du RGPD suite à une demande de l'utilisateur]
    public function execute(  array $argumentValues ) : string
    {
        $action = strtolower($argumentValues[0]);
        if(!in_array($action, ['check', 'anonymise'])) {
             $this->result->Error("Action invalide. Utilisez 'check' ou 'anonymise'.");
            return '';
        }
        $player=parent::getPlayer($argumentValues[1]);
        $player->refresh_data();
        $player->get_data();
        $player->get_row();

        if($action === 'check') {
            $issues = $this->check_rgpd_compliance($player);
            if (empty($issues)) {
                $this->result->Log("Les données du joueur sont conformes au RGPD.");
            } else {
                $this->result->Warning("Problèmes de conformité RGPD détectés :\n" . implode("\n", $issues));
            }
            return '';
        } elseif ($action === 'anonymise') {
            $this->anonymise_data($player);
            $this->result->Log("Les données du joueur ont été anonymisées conformément au RGPD.");
        }
        return '';
    }

    private function check_rgpd_compliance(Player $player): array {
        $issues = [];
        $data = $player->row;
        //mail => vide
        //plain_mail => vide
        //name => "[Supprimé]"
        //text => "[Supprimé]"
        //story => "[Supprimé]"
        //psw => "[Supprimé]" (hash donc inavalid / check connexion)
        //forum/missive => [Supprimé en respect du RGPD suite à une demande de l'utilisateur]
        $deleted_text = htmlentities('[Supprimé]');
        $deleted_str = '[Supprimé]';
        $new_content = 'Supprimé en respect du RGPD suite à une demande de l\'utilisateur';
        if (!empty($data->mail)) {
            $issues[] = "L'adresse e-mail hashé est présente.";
        }
        if (!empty($data->plain_mail)) {
            $issues[] = "L'adresse e-mail en clair est présente.";
        }
        if (!empty($data->name) && $data->name !== $deleted_str) {
            $issues[] = "Le champ 'name' n'est pas anonymisé.";
        }
        if (!empty($data->text) && $data->text !== $deleted_text) {
            $issues[] = "Le champ 'text' (mdj) n'est pas anonymisé.";
        }
        if (!empty($data->story) && $data->story !== $deleted_text) {
            $issues[] = "Le champ 'story' n'est pas anonymisé.";
        }
        if (!empty($data->psw && $data->psw !== $deleted_str)) {
            $issues[] = "Le mot de passe est présent.";
        }

        $postCount=0;
        foreach (File::scan_dir(__DIR__ . '/../../datas/private/forum/posts/', without: '.json') as $e) {

            $postJson = json()->decode('forum/posts', $e);
            if (isset($postJson->author) && $postJson->author == $player->id) {
                if (isset($postJson->text) && $postJson->text !==  $new_content) {
                    $postCount++;
                }
            }
        }
        if($postCount > 0) {
            $issues[] = "Il y a $postCount message(s) de forum non anonymisé(s).";
        }
        return $issues;
    }

    private function anonymise_data(Player $player): void {
        $deleted_text = '[Supprimé]';
        $new_content = 'Supprimé en respect du RGPD suite à une demande de l\'utilisateur';


        $data = $player->data;
        // Anonymisation des données    
        $sql = 'UPDATE players SET name = ? , mail = ? , plain_mail = ?, text = ?, story = ?, psw = ?  WHERE id = ?';

        $db = new Db();

        $db->exe($sql, array($deleted_text,'','',$deleted_text ,$deleted_text ,$deleted_text ,$player->id));

        $player->refresh_data();

                $postCount=0;
        foreach (File::scan_dir(__DIR__ . '/../../datas/private/forum/posts/', without: '.json') as $e) {

            $postJson = json()->decode('forum/posts', $e);
            if (isset($postJson->author) && $postJson->author == $player->id) {
                if (isset($postJson->text) && $postJson->text !== $new_content) {
                    $postCount++;
                    $postJson->text = $new_content;
                    $path = 'datas/private/forum/posts/'. $e .'.json';

                    $data = Json::encode($postJson);

                    Json::write_json($path, $data);
                }
            }
        }
        
        $this->result->Log("Anonymisation de $postCount message(s) de forum.");
        
    }
}
