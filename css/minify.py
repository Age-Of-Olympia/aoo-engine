import os
from csscompressor import compress

# pip3 install csscompressor

# Liste des fichiers à minifier
files = ["main.css", "dialog.css"]

# Fonction pour minifier un fichier CSS
def minify_css(file_path):
    with open(file_path, 'r') as file:
        css_content = file.read()

    # Minifier le contenu CSS
    minified_css = compress(css_content)

    # Écrire le contenu minifié dans un nouveau fichier
    minified_file_path = file_path.replace('.css', '.min.css')
    with open(minified_file_path, 'w') as minified_file:
        minified_file.write(minified_css)

    print(f"{file_path} a été minifié en {minified_file_path}")

# Minifier chaque fichier de la liste
for file in files:
    if os.path.isfile(file):
        minify_css(file)
    else:
        print(f"{file} n'existe pas dans le répertoire actuel.")
