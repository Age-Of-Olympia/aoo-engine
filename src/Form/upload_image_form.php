<?php

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\EntityManagerFactory;
use App\Entity\Race;

$entityManager = EntityManagerFactory::getEntityManager();

// 1) Fetch all Race records from the DB
$raceRepository = $entityManager->getRepository(Race::class);
$races = $raceRepository->findAll();

?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload Portrait or Avatar</title>
</head>
<script>
    // A small helper to fetch the next number:
    async function updateNextNumber() {
        const type   = document.getElementById('type').value;
        const raceId = document.getElementById('raceId').value;

        // Build the query URL
        const url = `/ajax/account/get_next_number.php?type=${encodeURIComponent(type)}&raceId=${encodeURIComponent(raceId)}`;

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
        document.getElementById('type').addEventListener('change', updateNextNumber);
        document.getElementById('raceId').addEventListener('change', updateNextNumber);

        // Optionally call it once on page load
        updateNextNumber();
    });
</script>

<body>
    <form action="/ajax/account/upload_image.php" method="POST" enctype="multipart/form-data">
        <!-- Choose "portrait" or "avatar" -->
        <label for="type">Select type:</label>
        <select name="type" id="type" required>
            <option value="portrait">Portrait</option>
            <option value="avatar">Avatar</option>
        </select>

        <!-- Race dropdown (taken from DB) -->
        <label for="raceId">Race:</label>
        <select name="raceId" id="raceId" required>
            <?php foreach ($races as $race): ?>
                <!-- We'll use race ID in the "value" so we can look it up in upload_image.php -->
                <option value="<?= $race->getId(); ?>">
                    <?= htmlspecialchars($race->getName()); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Portrait or Avatar Number -->
        <label for="number">Number:</label>
        <input type="number" name="number" id="number" required>

        <!-- The file input -->
        <label for="image">Choose file:</label>
        <input type="file" name="image" id="image" accept="image/jpeg,image/png" required>

        <button type="submit">Upload</button>
    </form>
</body>
</html>