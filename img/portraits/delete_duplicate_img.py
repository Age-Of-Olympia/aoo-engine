# require: pip3 install Pillow

import os
import hashlib
from PIL import Image

def calculate_image_hash(image_path):
    """Calculates the SHA-256 hash of an image."""
    with Image.open(image_path) as img:
        hash = hashlib.sha256(img.tobytes()).hexdigest()
    return hash

def find_and_remove_duplicates(folder_path):
    """Finds and removes duplicate images in a given folder and its subfolders."""
    if not os.path.exists(folder_path):
        print(f"Error: The folder '{folder_path}' does not exist.")
        return

    images_hashes = {}
    duplicates = []

    for root, dirs, files in os.walk(folder_path):
        for filename in files:
            if filename.lower().endswith(('.png', '.jpg', '.jpeg', '.gif', '.bmp', '.tiff')):
                file_path = os.path.join(root, filename)
                try:
                    image_hash = calculate_image_hash(file_path)
                except Exception as e:
                    print(f"Error processing file {file_path}: {e}")
                    continue

                if image_hash in images_hashes:
                    duplicates.append(file_path)
                else:
                    images_hashes[image_hash] = file_path

    # Remove duplicate files
    for duplicate in duplicates:
        os.remove(duplicate)
        print(f"Removed duplicate image: {duplicate}")

    print("Duplicate removal complete. Total duplicates removed:", len(duplicates))

if __name__ == "__main__":
    folder_path = "./"  # Remplacez par le chemin correct de votre dossier
    find_and_remove_duplicates(folder_path)
