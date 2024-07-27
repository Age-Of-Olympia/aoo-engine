import os

def check_htaccess_file(file_path):
    try:
        with open(file_path, 'r', encoding='utf-8') as file:
            content = file.read()
        return "deny from all" in content
    except FileNotFoundError:
        return False

def check_root_directories(root_dir, excluded_dirs):
    missing_htaccess = []
    for entry in os.listdir(root_dir):
        entry_path = os.path.join(root_dir, entry)
        if os.path.isdir(entry_path) and entry not in excluded_dirs:
            htaccess_path = os.path.join(entry_path, '.htaccess')
            if not check_htaccess_file(htaccess_path):
                missing_htaccess.append(entry_path)
    return missing_htaccess

def main():
    project_root = '.'  # Remplacer par le chemin de votre projet si différent
    excluded_dirs = ['img', 'js', 'css', '.git']

    missing_htaccess = check_root_directories(project_root, excluded_dirs)
    if missing_htaccess:
        print("Les dossiers suivants n'ont pas de fichier .htaccess avec 'deny from all' :")
        for directory in missing_htaccess:
            print(f" - {directory}")
    else:
        print("Tous les dossiers à la racine ont un fichier .htaccess valide.")

if __name__ == "__main__":
    main()
