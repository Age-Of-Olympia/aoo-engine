<?php

echo '<h3>Foregrounds (indestructibles, passables)</h3>';

echo '
<style>
.foreground-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 20px;
    padding: 15px;
}

.foreground-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 10px;
    border: 2px solid #eee;
    border-radius: 8px;
    background: white;
    cursor: pointer;
    transition: all 0.2s;
}

.foreground-item:hover {
    border-color: #0066cc;
    transform: translateY(-2px);
}

.foreground-item.selected {
    border-color: #ff3300;
}

.foreground-preview {
    position: relative;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 10px;
}

.foreground-grid {
    display: grid;
    gap: 1px;
    background: #ddd;
    padding: 1px;
    border-radius: 4px;
}

.foreground-grid img {
    display: block;
    width: 50px;
    height: 50px;
    image-rendering: pixelated;
}

.foreground-label {
    font-size: 12px;
    color: #333;
    text-align: center;
}

.map.foregrounds {
    cursor: pointer;
}
</style>

<div class="foreground-container">
';

// First, group the images by their base name (removing -XX suffix)
$groupedImages = [];
foreach(File::scan_dir('img/foregrounds/', $without=".png") as $e) {
    if (preg_match('/^(.+)-(\d+)$/', $e, $matches)) {
        $baseName = $matches[1];
        $index = intval($matches[2]);
        $groupedImages[$baseName][$index] = $e;
    } else {
        // Single images without split parts
        $url = 'img/foregrounds/'. $e .'.png';
        if(file_exists($url)) {
            echo '<div class="foreground-item">
                <div class="foreground-preview">
                    <img
                        class="map foregrounds select-name"
                        data-type="foregrounds"
                        data-name="'. $e .'"
                        data-is-split="false"
                        src="'. $url .'"
                        style="width: 50px; height: 50px;"
                    />
                </div>
                <div class="foreground-label">'. $e .'</div>
            </div>';
        }
    }
}

// Handle split images
foreach($groupedImages as $baseName => $images) {
    ksort($images);
    $count = count($images);
    $gridSize = ceil(sqrt($count));
    
    echo '<div class="foreground-item">
        <div class="foreground-preview">
            <div class="foreground-grid" style="grid-template-columns: repeat('. $gridSize .', 50px);">';
    
    // Create a data structure for all image parts
    $imageParts = [];
    foreach($images as $index => $imageName) {
        $url = 'img/foregrounds/'. $imageName .'.png';
        if(file_exists($url)) {
            $imageParts[] = [
                'name' => $imageName,
                'url' => $url
            ];
            echo '<img src="'. $url .'" style="width: 50px; height: 50px; image-rendering: pixelated;"/>';
        }
    }
    
    // Make the whole grid clickable as a map foreground
    echo '</div>
        <img
            class="map foregrounds select-name"
            data-type="foregrounds"
            data-name="'. $baseName .'"
            data-is-split="true"
            data-grid-size="'. $gridSize .'"
            data-parts="'. htmlspecialchars(json_encode($imageParts)) .'"
            src="'. $imageParts[0]['url'] .'"
            style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer;"
        />
    </div>
    <div class="foreground-label">'. $baseName .' ('. $gridSize .'x'. $gridSize .')</div>
</div>';
}

echo '</div>';
