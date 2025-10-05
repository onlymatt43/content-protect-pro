# 🚀 Guide Rapide - Déployer la Correction

**Temps estimé:** 2-5 minutes  
**Difficulté:** Facile  
**Prérequis:** Accès SSH ou SFTP au serveur

---

## 🎯 Méthode la Plus Simple

### Via SSH (RECOMMANDÉ)

```bash
# 1. Se connecter au serveur
ssh user@video.onlymatt.ca

# 2. Aller dans le dossier du plugin
cd /home/u948138067/domains/video.onlymatt.ca/public_html/wp-content/plugins/content-protect-pro-main

# 3. Mettre à jour depuis GitHub
git pull origin main

# 4. C'est tout ! ✅
```

---

## 🔧 Si Git N'est Pas Disponible

### Télécharger Juste le Fichier Corrigé

```bash
# 1. Se connecter au serveur
ssh user@video.onlymatt.ca

# 2. Backup l'ancien fichier
cd /home/u948138067/domains/video.onlymatt.ca/public_html/wp-content/plugins/content-protect-pro-main/includes
cp class-content-protect-pro.php class-content-protect-pro.php.backup

# 3. Télécharger le nouveau fichier
wget -O class-content-protect-pro.php https://raw.githubusercontent.com/onlymatt43/content-protect-pro/main/includes/class-content-protect-pro.php

# 4. Vérifier
php -l class-content-protect-pro.php
# Devrait afficher: "No syntax errors detected"
```

---

## 💻 Via SFTP (Sans SSH)

1. **Télécharger le fichier corrigé** sur votre ordinateur :
   https://raw.githubusercontent.com/onlymatt43/content-protect-pro/main/includes/class-content-protect-pro.php
   
2. **Se connecter via SFTP** (FileZilla, Cyberduck, etc.)
   - Host: video.onlymatt.ca
   - User: u948138067
   
3. **Naviguer vers** :
   ```
   /domains/video.onlymatt.ca/public_html/wp-content/plugins/content-protect-pro-main/includes/
   ```

4. **Faire un backup** :
   - Renommer `class-content-protect-pro.php` → `class-content-protect-pro.php.backup`

5. **Uploader le nouveau fichier**

6. **Tester** : Aller sur https://video.onlymatt.ca/wp-admin

---

## 🛡️ Si Le Plugin Est Bloqué

### Désactiver via Base de Données

```sql
-- Via phpMyAdmin
UPDATE wp_options 
SET option_value = REPLACE(
    option_value, 
    'content-protect-pro-main/content-protect-pro.php', 
    ''
) 
WHERE option_name = 'active_plugins';
```

---

## ✅ Vérification

Après le déploiement, vérifier :

1. ✅ Site accessible : https://video.onlymatt.ca/wp-admin
2. ✅ Pas d'erreur fatale PHP
3. ✅ Menu "Content Protect Pro" visible
4. ✅ Sous-menu "🤖 AI Assistant" présent
5. ✅ Logs propres (pas d'erreur wp_get_current_user)

---

## 📞 En Cas de Problème

Si quelque chose ne fonctionne pas :

1. **Restaurer le backup** :
   ```bash
   cd /path/to/plugin/includes/
   mv class-content-protect-pro.php.backup class-content-protect-pro.php
   ```

2. **Vérifier les logs WordPress** :
   ```bash
   tail -100 wp-content/debug.log
   ```

3. **Contacter le support**

---

## 🎉 Succès !

Une fois déployé, le plugin devrait :
- ✅ S'activer sans erreur
- ✅ Afficher tous les menus admin
- ✅ Fonctionner normalement

**Version corrigée:** 3.1.1 (hotfix)  
**Commit:** 9e6cd70
