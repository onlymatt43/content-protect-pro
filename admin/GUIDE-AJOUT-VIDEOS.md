# Guide de Configuration - Content Protect Pro

## Problème: Impossible d'ajouter des vidéos

Si vous ne pouvez pas ajouter de vidéos dans l'admin, c'est probablement parce que **Presto Player n'est pas configuré**.

## Solution: Configurer Presto Player

### Étapes de configuration:

1. **Installez Presto Player Pro** depuis WordPress.org
2. **Activez-le** dans Plugins
3. **Allez dans l'admin WordPress** → Content Protect Pro → Settings → Onglet "Integrations"
4. **Activez Presto Player**:
   - Cochez "Enable Presto Player integration"
   - Entrez votre license key si nécessaire

### Créer des vidéos dans Presto Player:

1. **Allez dans Presto Player → Videos**
2. **Ajoutez vos vidéos** avec protection par mot de passe
3. **Notez l'ID** de chaque vidéo (visible dans l'URL ou les détails)

## Une fois configuré:

1. Allez dans **Content Protect Pro → Protected Videos**
2. Cliquez **"Add Protected Video"**
3. Remplissez:
   - **Video ID**: L'ID de votre vidéo Presto Player
   - **Title**: Le titre descriptif
   - **Integration**: Sélectionnez "Presto Player"
   - **Gift Code Required**: Cochez si un code cadeau est nécessaire
4. Cliquez **"Add Video"**

## Dépannage:

- **Erreur "Presto Player not active"**: Installez et activez Presto Player
- **Vidéo ne se charge pas**: Vérifiez que l'ID Presto Player est correct
- **Code cadeau ne fonctionne pas**: Créez d'abord des codes dans Gift Codes

## Test rapide:

Après configuration, allez dans Settings → Integrations et vérifiez que Presto Player est marqué comme "Active".