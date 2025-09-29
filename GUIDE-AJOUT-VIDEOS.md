# Guide de Configuration - Content Protect Pro

## Problème: Impossible d'ajouter des vidéos

Si vous ne pouvez pas ajouter de vidéos dans l'admin, c'est probablement parce que les **intégrations ne sont pas configurées**.

## Solution: Configurer les Intégrations

### Option 1: Utiliser Bunny CDN (Recommandé)

1. **Allez dans l'admin WordPress** → Content Protect Pro → Settings → Onglet "Integrations"

2. **Activez Bunny CDN**:
   - Cochez "Enable Bunny CDN video protection"
   - Entrez votre **API Key** (de votre compte Bunny)
   - Entrez votre **Library ID** (ID de votre librairie Bunny)
   - Entrez votre **Pull Zone URL** (ex: https://mon-site.b-cdn.net)

3. **Comment obtenir ces informations**:
   - Connectez-vous à [Bunny.net](https://bunny.net)
   - Allez dans Stream → Libraries
   - Créez une librairie ou utilisez une existante
   - Copiez l'API Key et Library ID

### Option 2: Utiliser Presto Player

1. **Installez Presto Player Pro** depuis WordPress.org
2. **Activez-le** dans Plugins
3. **Dans les settings** de Content Protect Pro:
   - Cochez "Enable Presto Player integration"
   - Entrez votre license key si nécessaire

### Option 3: URL Directe (Simple mais moins sécurisé)

Vous pouvez aussi utiliser des URLs directes, mais c'est moins sécurisé.

## Une fois configuré:

1. Allez dans **Content Protect Pro → Protected Videos**
2. Cliquez **"Add Protected Video"**
3. Remplissez:
   - **Video ID**: L'ID de votre vidéo Bunny ou Presto
   - **Title**: Le titre
   - **Required Access (Minutes)**: Durée d'accès requise
   - **Integration**: Choisissez Bunny/Presto/Direct
4. Cliquez **"Add Video"**

## Dépannage:

- **Erreur "Integration not configured"**: Configurez une intégration d'abord
- **Bunny ne fonctionne pas**: Vérifiez API Key et Library ID
- **Presto ne fonctionne pas**: Assurez-vous que le plugin est activé

## Test rapide:

Après configuration, allez dans la page Videos et vérifiez que le statut d'intégration est "Enabled".