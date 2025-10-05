# Content Protect Pro - Version Simplifiée

## Vue d'ensemble

Content Protect Pro est un plugin WordPress qui protège vos vidéos avec des codes cadeaux, en utilisant exclusivement Presto Player pour la lecture vidéo.

## Installation

1. Installez et activez le plugin Content Protect Pro
2. Installez et activez le plugin [Presto Player](https://wordpress.org/plugins/presto-player/)
3. Allez dans **Content Protect Pro > Settings > Integrations** et activez Presto Player

## Utilisation

### 1. Créer des vidéos dans Presto Player

- Allez dans **Presto Player > Videos**
- Ajoutez vos vidéos avec protection par mot de passe
- Notez l'ID de chaque vidéo

### 2. Créer des codes cadeaux

- Allez dans **Content Protect Pro > Gift Codes**
- Créez des codes pour vos clients

### 3. Intégrer les vidéos dans vos pages

Utilisez le shortcode suivant :

```php
[cpp_protected_video id="VIDEO_ID" code="GIFT_CODE"]
```

**Exemple :**
```php
[cpp_protected_video id="123" code="VIP2024"]
```

### 4. Formulaire de validation des codes

Ajoutez ce shortcode où vous voulez que les utilisateurs entrent leur code :

```php
[cpp_giftcode_form redirect="https://votresite.com/page-protegee"]
```

## Comment ça marche

1. L'utilisateur entre un code cadeau valide
2. Le code est stocké en session
3. Quand il accède à une vidéo protégée, le plugin vérifie le code
4. Si valide, la vidéo Presto Player s'affiche automatiquement

## Avantages de cette approche

- **Simple** : Pas de configuration complexe Bunny CDN
- **Fiable** : S'appuie sur Presto Player éprouvé
- **Léger** : Moins de code, moins de bugs potentiels
- **Maintenance** : Plus facile à maintenir et déboguer

## Support

Pour toute question, consultez la documentation complète ou contactez le support.