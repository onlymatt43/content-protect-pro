<?php
/**
 * Test rapide de la version simplifiée
 * Vérifie que Presto Player est disponible et que les shortcodes fonctionnent
 */

echo "<h1>Test de Content Protect Pro - Version Simplifiée</h1>";

// Vérifier que Presto Player est actif
if (is_plugin_active('presto-player/presto-player.php')) {
    echo "<p style='color: green;'>✓ Presto Player est actif</p>";
} else {
    echo "<p style='color: red;'>✗ Presto Player n'est pas installé/actif</p>";
    echo "<p>Installez Presto Player depuis <a href='" . admin_url('plugin-install.php?s=presto+player&tab=search&type=term') . "' target='_blank'>ici</a></p>";
}

// Tester le shortcode de formulaire
echo "<h2>Test du formulaire de code cadeau</h2>";
echo do_shortcode('[cpp_giftcode_form]');

// Tester un shortcode vidéo (si Presto Player fonctionne)
echo "<h2>Test du shortcode vidéo (si vous avez une vidéo Presto Player)</h2>";
echo "<p>Remplacez VIDEO_ID par l'ID réel d'une vidéo Presto Player :</p>";
echo "<code>[cpp_protected_video id='VIDEO_ID' code='TEST123']</code>";

// Instructions
echo "<h2>Instructions d'utilisation</h2>";
echo "<ol>";
echo "<li>Créez des vidéos dans Presto Player avec protection par mot de passe</li>";
echo "<li>Créez des codes cadeaux dans Content Protect Pro > Gift Codes</li>";
echo "<li>Utilisez le shortcode <code>[cpp_protected_video id='VIDEO_ID' code='GIFT_CODE']</code></li>";
echo "</ol>";

echo "<p><a href='" . admin_url('admin.php?page=content-protect-pro-settings') . "'>Aller aux paramètres</a></p>";
?>