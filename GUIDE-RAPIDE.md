# 🚀 GUIDE RAPIDE - Content Protect Pro

## 📋 Vue d'ensemble
Content Protect Pro est un plugin WordPress qui protège vos contenus (codes cadeaux et vidéos) avec un système de sécurité avancé.

## ⚙️ Configuration initiale

### 1. Activation du plugin
- Allez dans **Extensions > Extensions installées**
- Activez **Content Protect Pro**

### 2. Configuration des intégrations
- Allez dans **Content Protect Pro > Paramètres**
- Configurez **Bunny CDN** ou **Presto Player** :
  - Pour Bunny : Entrez votre API Key et Library ID
  - Pour Presto : Configurez l'intégration Presto Player

### 3. Ajout de contenu

#### Codes cadeaux
- Allez dans **Content Protect Pro > Codes cadeaux**
- Cliquez sur **Ajouter nouveau**
- Remplissez : Code, Description, Nombre d'utilisations max

#### Vidéos
- Allez dans **Content Protect Pro > Vidéos**
- Cliquez sur **Ajouter nouveau**
- Remplissez : Titre, URL Bunny/Presto, Description

## 🎯 Utilisation des shortcodes

### Bibliothèque vidéo
```php
[cpp_video_library]
```
- Affiche une grille de toutes vos vidéos protégées
- Les utilisateurs doivent avoir un code cadeau valide pour accéder

### Formulaire de code cadeau
```php
[cpp_giftcode_form]
```
- Formulaire pour entrer un code cadeau
- Redirige vers la bibliothèque vidéo après validation

### Vérification de code
```php
[cpp_giftcode_check]
```
- Vérifie si un code est valide
- Utile pour les intégrations personnalisées

### Vidéo individuelle
```php
[cpp_protected_video id="123"]
```
- Affiche une vidéo spécifique par ID
- Nécessite un code cadeau valide

## 🧪 Test rapide

### Script de diagnostic
1. Téléchargez `test-rapide.php` dans la racine WordPress
2. Accédez à `votresite.com/test-rapide.php`
3. Vérifiez tous les tests en vert ✅

### Test manuel
1. Créez une page avec : `[cpp_giftcode_form]`
2. Créez une autre page avec : `[cpp_video_library]`
3. Testez le flux complet

## 🔧 Dépannage

### Le shortcode ne s'affiche pas
- ✅ Plugin activé ?
- ✅ Shortcode enregistré ? (voir test-rapide.php)
- ✅ Classes PHP chargées ?

### Les vidéos ne se chargent pas
- ✅ Intégration configurée ?
- ✅ Vidéos ajoutées dans l'admin ?
- ✅ Code cadeau valide ?

### Erreurs JavaScript
- Vérifiez la console du navigateur (F12)
- Assurez-vous que jQuery est chargé

## 📊 Analytics
- Allez dans **Content Protect Pro > Analytics**
- Consultez les statistiques d'utilisation
- Exportez les données si nécessaire

## 🔒 Sécurité
- Les vidéos sont protégées par JWT tokens
- Rate limiting activé par défaut
- Logs de sécurité disponibles

## 📞 Support
Si vous rencontrez des problèmes :
1. Exécutez `test-rapide.php`
2. Consultez `diagnostic-complet.php`
3. Suivez le `GUIDE-DEPANNAGE.md`

---
*Content Protect Pro v1.0.0 - Guide rapide*