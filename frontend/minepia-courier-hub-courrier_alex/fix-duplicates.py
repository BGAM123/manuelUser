#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Script pour supprimer les clés dupliquées dans LanguageContext.tsx"""

import re
from collections import OrderedDict

def fix_duplicates(file_path):
    print(f"📖 Lecture du fichier: {file_path}")
    
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Trouver l'objet translations
    match = re.search(r'const translations: Translations = \{(.*?)\n\};', content, re.DOTALL)
    
    if not match:
        print("❌ Impossible de trouver l'objet translations")
        return False
    
    translations_start = match.start(1)
    translations_end = match.end(1)
    translations_content = match.group(1)
    
    # Parser les clés
    lines = translations_content.split('\n')
    seen_keys = OrderedDict()
    current_key = None
    current_block = []
    in_block = False
    brace_count = 0
    
    for line in lines:
        # Détecter le début d'une clé
        key_match = re.match(r'^\s*(\w+):\s*\{', line)
        
        if key_match:
            # Sauvegarder le bloc précédent si existe
            if current_key and current_block:
                if current_key not in seen_keys:
                    seen_keys[current_key] = '\n'.join(current_block)
                else:
                    print(f"🗑️  Suppression du doublon: {current_key}")
            
            # Commencer un nouveau bloc
            current_key = key_match.group(1)
            current_block = [line]
            brace_count = 1
            in_block = True
        elif in_block:
            current_block.append(line)
            # Compter les accolades
            brace_count += line.count('{') - line.count('}')
            
            # Fin du bloc quand brace_count revient à 0
            if brace_count == 0:
                if current_key not in seen_keys:
                    seen_keys[current_key] = '\n'.join(current_block)
                else:
                    print(f"🗑️  Suppression du doublon: {current_key}")
                current_key = None
                current_block = []
                in_block = False
        else:
            # Ligne vide ou commentaire
            if not in_block:
                current_block.append(line)
    
    # Reconstruire le contenu
    new_translations = '\n'.join(seen_keys.values())
    
    # Remplacer dans le contenu original
    new_content = (
        content[:translations_start] +
        new_translations +
        content[translations_end:]
    )
    
    # Écrire le fichier
    print(f"💾 Écriture du fichier nettoyé")
    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(new_content)
    
    print(f"✅ Fichier nettoyé avec succès!")
    print(f"📊 {len(seen_keys)} clés uniques conservées")
    return True

if __name__ == '__main__':
    file_path = 'src/contexts/LanguageContext.tsx'
    fix_duplicates(file_path)
