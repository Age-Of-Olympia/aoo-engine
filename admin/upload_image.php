<?php
include ($_SERVER['DOCUMENT_ROOT'].'/admin/includes/header.php');
?>

<h1>Upload Images</h1>
<div class="upload-form">
    <form method="post" action="process_upload.php" enctype="multipart/form-data">
        <div class="form-group">
            <label>Select Image:</label>
            <input type="file" name="image" accept="image/*" required>
        </div>
        <button type="submit" class="btn">Upload Image</button>
    </form>
</div>

<?php
include ($_SERVER['DOCUMENT_ROOT'].'/admin/includes/footer.php');
?>
