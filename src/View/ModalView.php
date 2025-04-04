<?php

namespace App\View;

class ModalView
{

    /**
     * affiche le html d'une fenêtre modale
     */
    public function displayModal(string $modalId, string $modalContentId, string $title = "" , string $initContent = "" ): void
    {
        $modal ='            
            <div id="'.$modalId.'"  class="modal-bg">
                <div class="modal">
                    <div class="modal-content">
                        <h3>'.$title.'</h3>
                        <div id="'.$modalContentId.'">'.$initContent.'</div>
                        <button class="closeButton">Fermer</button>
                    </div>
                </div>
            </div>';
        echo $modal;
    }


}