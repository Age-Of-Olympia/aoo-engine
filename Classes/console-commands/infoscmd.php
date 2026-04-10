<?php
use Classes\Command;
use Classes\Argument;

class InfosCmd extends Command
{
    public function __construct() {
        parent::__construct("infos", [new Argument('sujet',false),new Argument('mat',false)]);
        parent::setDescription(<<<EOT
Affiche des informations sur un personnage.
Exemple:
> infos option [matricule ou nom]
> infos option 1
> infos option Orcrist
EOT);
    }

    public function execute(array $argumentValues): string
    {
        $sujet = strtolower($argumentValues[0]);

        if ($sujet === 'option') {

            $player = parent::getPlayer($argumentValues[1]);
            $player->get_data();

            $options = $player->get_options();

            if (empty($options)) {
                return $player->data->name . ' n\'a aucune option.';
            }

            return $player->data->name . ' a ' . count($options) . ' option(s) : ' . implode(', ', $options);
        }

        return 'error: sujet "' . $argumentValues[0] . '" inconnu. Sujets disponibles : option';
    }
}
