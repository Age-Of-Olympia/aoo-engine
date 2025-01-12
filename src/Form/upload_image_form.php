<?php

use App\Entity\EntityManagerFactory;
use App\Entity\Race;

// Fetch optional parameters from URL or from variables
// If $selectedRaceId is not set by including script, check $_GET, else default to null
if (!isset($selectedRaceId)) {
    $selectedRaceId = isset($_GET['raceId']) ? (int)$_GET['raceId'] : null;
}

// If $selectedType is not set by including script, check $_GET, else default to null
if (!isset($selectedType)) {
    $selectedType = isset($_GET['type']) ? $_GET['type'] : null;
}

$entityManager = EntityManagerFactory::getEntityManager();

// Fetch all Race records from the DB
$raceRepository = $entityManager->getRepository(Race::class);
$races = $raceRepository->findAll();

/**
 * If we have a $selectedRaceId, let's check it is valid.
 * - If valid, we store $lockedRaceName to display it.
 * - If invalid, we could reset $selectedRaceId to null or handle error differently.
 */
$lockedRaceName = null;
if ($selectedRaceId) {
    $lockedRace = $raceRepository->find($selectedRaceId);
    if ($lockedRace) {
        $lockedRaceName = $lockedRace->getName();
    } else {
        // If invalid, set it back to null or handle it differently
        $selectedRaceId = null;
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload Portrait or Avatar</title>
    <link rel="stylesheet" href="css/modal.css" />
    <script src="js/modal.js"></script>

</head>
<script>
    // A small helper to fetch the next number:
    async function updateNextNumber() {
        // If we locked "type" or "raceId", they might be hidden inputs.
        // Make sure we pick up their values correctly (or from the dropdown).
        const typeSelect = document.getElementById('type');
        const raceIdSelect = document.getElementById('raceId');

        // If the elements exist, pick up from them; otherwise from hidden fields
        const type   = typeSelect   ? typeSelect.value   : document.querySelector('input[name="type"]').value;
        const raceId = raceIdSelect ? raceIdSelect.value : document.querySelector('input[name="raceId"]').value;

        // Build the query URL
        const url = `/api/account/get_next_number_api.php?type=${encodeURIComponent(type)}&raceId=${encodeURIComponent(raceId)}`;

        try {
            const response = await fetch(url);
            const data     = await response.json();

            if (data.error) {
                alert("Error: " + data.error);
                return;
            }

            // data.nextNumber should contain the value we want
            document.getElementById('number').value = data.nextNumber;
        } catch (e) {
            console.error("Failed to fetch next number", e);
        }
    }

    // We'll call updateNextNumber whenever the user changes "type" or "race"
    document.addEventListener('DOMContentLoaded', () => {
        // Only attach event listeners if the elements actually exist
        if (document.getElementById('type')) {
            document.getElementById('type').addEventListener('change', updateNextNumber);
        }
        if (document.getElementById('raceId')) {
            document.getElementById('raceId').addEventListener('change', updateNextNumber);
        }

        // Optionally call it once on page load
        updateNextNumber();
    });
</script>

<body>
    <?php
        use App\View\ModalView;
        
        // The Waiting Modal     
        $modalView = new ModalView();
        $modalView->displayModal('waitingModal','waitingMessage', 'Patience...', 'Image en cours d\'upload');
        
        // The result modal
        $modalView = new ModalView();
        $modalView->displayModal('resultModal','resultContent', 'Envoi !');
    ?>


    <form  id="uploadForm" enctype="multipart/form-data">
        <!-- Choose "portrait" or "avatar" -->
        <?php if ($selectedType): ?>
            <!-- If type is provided, lock it in as a hidden field -->
            <input type="hidden" name="type" value="<?= htmlspecialchars($selectedType) ?>">
            <!-- <p>Type: <?= htmlspecialchars($selectedType) ?></p> -->
        <?php else: ?>
            <!-- Otherwise, show the type dropdown -->
            <label for="type">Select type:</label>
            <select name="type" id="type" required>
                <option value="portrait">Portrait</option>
                <option value="avatar">Avatar</option>
            </select>
        <?php endif; ?>

        <!-- Race dropdown (taken from DB) -->
        <?php if ($selectedRaceId): ?>
            <!-- If race is provided, lock it in as a hidden field -->
            <input type="hidden" name="raceId" value="<?= $selectedRaceId ?>">
            <!-- <p>Race: <?= htmlspecialchars($lockedRaceName ?? 'Unknown race') ?></p> -->
        <?php else: ?>
            <!-- Otherwise, show the race dropdown -->
            <label for="raceId">Race :</label>
            <select name="raceId" id="raceId" required>
                <?php foreach ($races as $race): ?>
                    <option value="<?= $race->getId(); ?>">
                        <?= htmlspecialchars($race->getName()); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <!-- Portrait or Avatar Number -->
        <label for="number">Numéro du fichier :</label>
        <input type="number" name="number" id="number" required>

        <!-- The file input -->
        <label for="image">Fichier :</label>
        <input type="file" name="image" id="image" accept="image/jpeg,image/png" required>

        <button type="submit">Envoi</button>
    </form>

    <script>
  // Grab references to our modals
  const waitingModal = document.getElementById('waitingModal');
  const resultModal  = document.getElementById('resultModal');
  const resultContent= document.getElementById('resultContent');



  // Intercept the form submit
  document.getElementById('uploadForm').addEventListener('submit', async function(e) {
    e.preventDefault(); // Stop normal form submission

    // Show "waiting" modal
    showModal(waitingModal);

    try {
      // Prepare form data
      const formData = new FormData(this);

      // POST to your upload script
      const response = await fetch('/api/account/upload_image_api.php', {
        method: 'POST',
        body: formData
      });

      // We can assume the server returns JSON with success/error info
      // But if your server returns plain text or HTML, adjust accordingly:
      const result = await response.json();

      // Hide waiting modal
      hideModal(waitingModal);

      // Display result in result modal
      if (result.success) {
        resultContent.innerHTML = `<p style="color:green;">${result.message}</p>`;
      } else {
        resultContent.innerHTML = `<p style="color:red;">${result.error || 'Echec de l\'envoi.'}</p>`;
      }
      showModal(resultModal);

    } catch (error) {
      // Hide waiting modal
      hideModal(waitingModal);

      // Show error in result modal
      resultContent.innerHTML = `<p style="color:red;">Quelque chose s'est mal passé. ${error}</p>`;
      showModal(resultModal);
    }
  });

  bindModalButton(resultModal,true);
</script>
</body>
</html>