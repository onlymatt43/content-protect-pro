#!/bin/bash
###############################################################################
# Content Protect Pro - Hotfix Deployment Script
# 
# Déploie automatiquement la correction d'urgence sur le serveur WordPress
# 
# Usage: bash deploy-hotfix.sh
###############################################################################

set -e  # Exit on error

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}╔═══════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   Content Protect Pro - Hotfix Deployment            ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════════╝${NC}"
echo ""

# Configuration (À MODIFIER selon votre serveur)
WP_PATH="/home/u948138067/domains/video.onlymatt.ca/public_html"
PLUGIN_NAME="content-protect-pro-main"
PLUGIN_PATH="$WP_PATH/wp-content/plugins/$PLUGIN_NAME"

# Vérification
echo -e "${YELLOW}⚠️  ATTENTION: Ce script va modifier votre plugin WordPress${NC}"
echo -e "   Path: $PLUGIN_PATH"
echo ""
read -p "Continuer? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${RED}❌ Annulé${NC}"
    exit 1
fi

# 1. Backup
echo ""
echo -e "${BLUE}📦 Étape 1: Backup du plugin actuel...${NC}"
BACKUP_DIR="$WP_PATH/wp-content/plugins-backup/$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"
cp -r "$PLUGIN_PATH" "$BACKUP_DIR/"
echo -e "${GREEN}✅ Backup créé: $BACKUP_DIR${NC}"

# 2. Git pull (si le plugin est un repo git)
if [ -d "$PLUGIN_PATH/.git" ]; then
    echo ""
    echo -e "${BLUE}📥 Étape 2: Git pull depuis GitHub...${NC}"
    cd "$PLUGIN_PATH"
    git fetch origin
    git reset --hard origin/main
    echo -e "${GREEN}✅ Code mis à jour depuis GitHub${NC}"
else
    echo ""
    echo -e "${YELLOW}⚠️  Étape 2: Le plugin n'est pas un repo git${NC}"
    echo -e "   Téléchargement du fichier corrigé..."
    
    # Télécharger seulement le fichier corrigé
    curl -o "$PLUGIN_PATH/includes/class-content-protect-pro.php" \
        "https://raw.githubusercontent.com/onlymatt43/content-protect-pro/main/includes/class-content-protect-pro.php"
    echo -e "${GREEN}✅ Fichier corrigé téléchargé${NC}"
fi

# 3. Vérifier la syntaxe
echo ""
echo -e "${BLUE}🔍 Étape 3: Vérification syntaxe PHP...${NC}"
php -l "$PLUGIN_PATH/includes/class-content-protect-pro.php"
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Syntaxe PHP valide${NC}"
else
    echo -e "${RED}❌ ERREUR: Syntaxe PHP invalide !${NC}"
    echo -e "${YELLOW}⚠️  Restauration du backup...${NC}"
    rm -rf "$PLUGIN_PATH"
    cp -r "$BACKUP_DIR/$PLUGIN_NAME" "$PLUGIN_PATH"
    echo -e "${YELLOW}✅ Backup restauré${NC}"
    exit 1
fi

# 4. Permissions
echo ""
echo -e "${BLUE}🔐 Étape 4: Correction des permissions...${NC}"
chown -R www-data:www-data "$PLUGIN_PATH" 2>/dev/null || \
chown -R u948138067:u948138067 "$PLUGIN_PATH" 2>/dev/null || \
echo -e "${YELLOW}⚠️  Impossible de changer les permissions (pas root?)${NC}"
chmod -R 755 "$PLUGIN_PATH"
echo -e "${GREEN}✅ Permissions corrigées${NC}"

# 5. Réactiver le plugin si nécessaire
echo ""
echo -e "${BLUE}🔄 Étape 5: Vérification statut du plugin...${NC}"
if command -v wp &> /dev/null; then
    cd "$WP_PATH"
    WP_STATUS=$(wp plugin status content-protect-pro-main --format=json 2>/dev/null | jq -r '.status' 2>/dev/null || echo "unknown")
    
    if [ "$WP_STATUS" = "inactive" ]; then
        echo -e "${YELLOW}⚠️  Plugin désactivé, activation...${NC}"
        wp plugin activate content-protect-pro-main
        echo -e "${GREEN}✅ Plugin activé${NC}"
    elif [ "$WP_STATUS" = "active" ]; then
        echo -e "${GREEN}✅ Plugin déjà actif${NC}"
    else
        echo -e "${YELLOW}⚠️  Statut inconnu: $WP_STATUS${NC}"
    fi
else
    echo -e "${YELLOW}⚠️  WP-CLI non disponible, activation manuelle requise${NC}"
fi

# 6. Test final
echo ""
echo -e "${BLUE}🧪 Étape 6: Test de chargement...${NC}"
php -r "
define('ABSPATH', '$WP_PATH/');
require_once '$PLUGIN_PATH/content-protect-pro.php';
echo 'Plugin chargé sans erreur\n';
" 2>&1 | grep -q "Plugin chargé" && echo -e "${GREEN}✅ Plugin se charge correctement${NC}" || echo -e "${RED}❌ Erreur de chargement${NC}"

# Résumé
echo ""
echo -e "${BLUE}╔═══════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║                 DÉPLOIEMENT TERMINÉ                   ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${GREEN}✅ Hotfix déployé avec succès !${NC}"
echo ""
echo -e "${YELLOW}📋 Prochaines étapes :${NC}"
echo "   1. Vérifier le site: https://video.onlymatt.ca/wp-admin"
echo "   2. Aller dans Extensions → Content Protect Pro"
echo "   3. Vérifier que le menu 🤖 AI Assistant apparaît"
echo "   4. Tester la création d'un gift code"
echo ""
echo -e "${BLUE}📁 Backup sauvegardé dans :${NC}"
echo "   $BACKUP_DIR"
echo ""
echo -e "${GREEN}🎉 Correction terminée !${NC}"
