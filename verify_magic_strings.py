import os
import re

def find_effects_in_file(file_path):
    try:
        with open(file_path, 'rb') as file:
            content = file.read().decode('utf-8', errors='ignore')
        # Regex pour capturer les valeurs entre "haveEffect(" et ")"
        pattern = re.compile(r"haveEffect\('([^']+)'\)")
        matches = pattern.findall(content)
        return matches
    except Exception as e:
        print(f"Erreur lors de la lecture du fichier {file_path}: {e}")
        return []

def search_effects_in_project(root_dir):
    results = []
    for subdir, _, files in os.walk(root_dir):
        for file in files:
            file_path = os.path.join(subdir, file)
            effects = find_effects_in_file(file_path)
            for effect in effects:
                results.append((file_path, effect))
    return results

def main():
    # Remplacer '.' par le chemin de votre projet si différent
    project_root = '.'
    results = search_effects_in_project(project_root)
    for file_path, effect in results:
        print(f"Nom du fichier: {file_path}, Valeur: '{effect}'")

if __name__ == "__main__":
    main()
