# 🚨 GUIDE DE DÉPANNAGE - Content Protect Pro

## Problème: "Ça ne fonctionne pas"

Voici les étapes pour diagnostiquer et résoudre les problèmes courants.

---

## 🔍 **ÉTAPE 1: Diagnostic Automatique**

1. **Téléchargez le script de diagnostic** ci-dessus (`diagnostic-complet.php`)
2. **Placez-le dans le répertoire racine** de votre site WordPress
3. **Accédez-y via votre navigateur** : `http://votresite.com/diagnostic-complet.php`
4. **Suivez les recommandations** affichées

---

## 🛠️ **PROBLÈMES COURANTS ET SOLUTIONS**

### ❌ **Problème 1: Plugin non activé**
**Symptômes:** Shortcodes ne fonctionnent pas, pages admin vides

**Solution:**
1. Allez dans **Extensions > Extensions installées**
2. Cherchez "Content Protect Pro"
3. Cliquez sur **Activer**
4. Rafraîchissez la page

---

### ❌ **Problème 2: Tables de base de données manquantes**
**Symptômes:** Erreur "table doesn't exist"

**Solution:**
1. Désactivez le plugin
2. Réactivez-le (les tables se créent automatiquement)
3. Vérifiez dans phpMyAdmin que les tables existent :
   - `wp_cpp_giftcodes`
   - `wp_cpp_protected_videos`
   - `wp_cpp_analytics`
   - `wp_cpp_sessions`

---

### ❌ **Problème 3: Presto Player non configuré**
**Symptômes:** Impossible d'ajouter des vidéos, message "Presto Player not active"

**Solution:**
1. Allez dans **Extensions > Extensions installées**
2. Cherchez "Presto Player"
3. S'il n'est pas installé : **Ajouter nouveau > Rechercher "Presto Player"**
4. Activez-le
5. Allez dans **Content Protect Pro > Settings > Integrations**
6. Cochez "Enable Presto Player integration"

---

### ❌ **Problème 4: Aucune vidéo ajoutée**
**Symptômes:** Bibliothèque vide, shortcode n'affiche rien

**Solution:**
1. **Créez d'abord des vidéos dans Presto Player:**
   - Allez dans **Presto Player > Videos**
   - Ajoutez vos vidéos avec protection par mot de passe
   - Notez les IDs des vidéos

2. **Ajoutez-les dans Content Protect Pro:**
   - Allez dans **Content Protect Pro > Protected Videos**
   - Cliquez **"Add Protected Video"**
   - Remplissez :
     - **Video ID**: ID de votre vidéo Presto Player
     - **Title**: Nom de la vidéo
     - **Integration**: Choisissez "Presto Player"
     - **Gift Code Required**: Cochez si nécessaire
   - Cliquez **"Add Video"**

---

### ❌ **Problème 5: Shortcodes non reconnus**
**Symptômes:** `[cpp_video_library]` s'affiche tel quel

**Solution:**
1. Vérifiez que le plugin est activé
2. Videz le cache WordPress
3. Testez avec un shortcode simple : `[cpp_giftcode_form]`
4. Si ça ne marche pas, vérifiez les erreurs PHP

---

### ❌ **Problème 6: Erreurs JavaScript**
**Symptômes:** Boutons ne fonctionnent pas, modales ne s'ouvrent pas

**Solution:**
1. Ouvrez la **console du navigateur** (F12)
2. Cherchez les erreurs JavaScript
3. Les erreurs communes :
   - `jQuery is not defined` → jQuery n'est pas chargé
   - `ajaxurl is not defined` → Problème de localisation
   - `CPP_PLUGIN_DIR is not defined` → Constantes manquantes

---

## 🧪 **TESTS À EFFECTUER**

### **Test 1: Page de test simple**
Créez une page WordPress avec ce contenu :
```
<h1>Test Content Protect Pro</h1>
[cpp_giftcode_form]
<hr>
[cpp_video_library per_page="3" show_filters="false" show_search="false"]
```

### **Test 2: Vérification des assets**
Vérifiez que ces fichiers sont accessibles :
- `/wp-content/plugins/content-protect-pro/public/css/cpp-public.css`
- `/wp-content/plugins/content-protect-pro/public/js/cpp-public.js`

### **Test 3: Permissions**
Assurez-vous que votre utilisateur WordPress a les droits :
- `manage_options` (pour l'admin)
- `read` (pour voir les vidéos)

---

## 📊 **VÉRIFICATIONS TECHNIQUES**

### **Version PHP minimum**
- Requis: **PHP 7.4+**
- Vérifiez dans **Outils > Santé du site**

### **Version WordPress**
- Requis: **WordPress 5.0+**
- Compatible jusqu'à 6.3

### **Plugins conflictuels**
Désactivez temporairement ces plugins pour tester :
- Cache plugins (WP Rocket, W3 Total Cache)
- Security plugins (Wordfence, iThemes Security)
- Optimization plugins (Autoptimize, WP Super Minify)

---

## 🔧 **ACTIONS AVANCÉES**

### **Vider tous les caches**
1. Cache WordPress : **Réglages > Permaliens** (sauvegarder)
2. Cache du navigateur : Ctrl+F5
3. Cache du plugin de cache

### **Réinitialiser le plugin**
⚠️ **ATTENTION: Supprime toutes les données !**
```php
// Dans wp-config.php, ajoutez temporairement :
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Puis exécutez ce code dans une page de test :
delete_option('cpp_integration_settings');
delete_option('cpp_video_settings');
delete_option('cpp_giftcode_settings');
// Réactivez le plugin
```

### **Logs d'erreur**
Activez les logs dans `wp-config.php` :
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```
Les logs sont dans `/wp-content/debug.log`

---

## 📞 **CONTACT SUPPORT**

Si rien ne fonctionne :

1. **Exécutez le diagnostic complet**
2. **Notez toutes les erreurs**
3. **Fournissez ces informations :**
   - Version WordPress
   - Version PHP
   - Plugins actifs
   - Erreurs dans debug.log
   - Résultats du diagnostic

---

## ✅ **VÉRIFICATION FINALE**

Après avoir appliqué les corrections :

1. ✅ Plugin activé
2. ✅ Tables créées
3. ✅ Intégrations configurées
4. ✅ Vidéos ajoutées
5. ✅ Shortcodes fonctionnels
6. ✅ JavaScript chargé
7. ✅ Pas d'erreurs console

**Le shortcode `[cpp_video_library]` devrait maintenant afficher toutes vos vidéos ! 🎉**