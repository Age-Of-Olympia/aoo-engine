<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Interface\ActorInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class MalusOutcomeInstruction extends OutcomeInstruction
{
    public function execute(ActorInterface $actor, ActorInterface $target): OutcomeResult {
        $params =$this->getParameters();
        $to = $param["to"] ?? "target";
        $malus = $params["malus"] ?? 1;

        if ($to == "target") {
            $target->put_malus($malus);
        } else if ($to == "actor") {
            $actor->put_malus($malus);
        }

        return new OutcomeResult(true, array(), array());
    }

}
