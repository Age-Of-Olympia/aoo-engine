import os
import json

def is_json_valid(file_path):
    try:
        with open(file_path, 'r', encoding='utf-8') as file:
            json.load(file)
        return True
    except json.JSONDecodeError as e:
        print(f"Erreur de JSON dans le fichier {file_path}: {e}")
        return False
    except Exception as e:
        print(f"Erreur lors de la lecture du fichier {file_path}: {e}")
        return False

def check_json_files_in_project(root_dir):
    invalid_files = []
    for subdir, _, files in os.walk(root_dir):
        for file in files:
            if file.endswith('.json'):
                file_path = os.path.join(subdir, file)
                if not is_json_valid(file_path):
                    invalid_files.append(file_path)
    return invalid_files

def main():
    # Remplacer '.' par le chemin de votre projet si différent
    project_root = '.'
    invalid_files = check_json_files_in_project(project_root)
    if invalid_files:
        print("Les fichiers JSON suivants ne sont pas valides :")
        for file_path in invalid_files:
            print(f" - {file_path}")
    else:
        print("Tous les fichiers JSON sont valides.")

if __name__ == "__main__":
    main()

