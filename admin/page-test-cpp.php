<?php
/**
 * Template: Test Page for Content Protect Pro
 * Description: Simple test page to verify shortcodes work
 */

get_header(); ?>

<div class="cpp-test-page" style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <h1>🧪 Test Page - Content Protect Pro</h1>

    <div class="cpp-test-section" style="margin: 30px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
        <h2>1. Formulaire de code cadeau</h2>
        <p>Entrez un code cadeau pour accéder aux vidéos :</p>
        <?php echo do_shortcode('[cpp_giftcode_form]'); ?>
    </div>

    <div class="cpp-test-section" style="margin: 30px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
        <h2>2. Bibliothèque vidéo</h2>
        <p>Voici toutes les vidéos disponibles (nécessite un code valide) :</p>
        <?php echo do_shortcode('[cpp_video_library]'); ?>
    </div>

    <div class="cpp-test-section" style="margin: 30px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
        <h2>3. Vérification de code</h2>
        <p>Vérifiez si un code est valide :</p>
        <?php echo do_shortcode('[cpp_giftcode_check]'); ?>
    </div>

    <div class="cpp-test-info" style="margin: 30px 0; padding: 20px; background: #f8f9fa; border-radius: 8px;">
        <h3>ℹ️ Informations de test</h3>
        <ul>
            <li><strong>Plugin actif:</strong> <?php echo is_plugin_active('content-protect-pro/content-protect-pro.php') ? '✅ Oui' : '❌ Non'; ?></li>
            <li><strong>Shortcodes enregistrés:</strong>
                <ul>
                    <li>cpp_giftcode_form: <?php echo shortcode_exists('cpp_giftcode_form') ? '✅' : '❌'; ?></li>
                    <li>cpp_video_library: <?php echo shortcode_exists('cpp_video_library') ? '✅' : '❌'; ?></li>
                    <li>cpp_giftcode_check: <?php echo shortcode_exists('cpp_giftcode_check') ? '✅' : '❌'; ?></li>
                </ul>
            </li>
            <li><strong>Classes chargées:</strong>
                <ul>
                    <li>CPP_Video_Manager: <?php echo class_exists('CPP_Video_Manager') ? '✅' : '❌'; ?></li>
                    <li>CPP_Giftcode_Manager: <?php echo class_exists('CPP_Giftcode_Manager') ? '✅' : '❌'; ?></li>
                </ul>
            </li>
        </ul>

        <h4>Actions recommandées:</h4>
        <ol>
            <li>Si des éléments sont marqués ❌, vérifiez l'activation du plugin</li>
            <li>Allez dans l'admin Content Protect Pro pour configurer les intégrations</li>
            <li>Ajoutez des vidéos et des codes cadeaux</li>
            <li>Revenez tester cette page</li>
        </ol>
    </div>
</div>

<style>
.cpp-test-page h1 { color: #2c3e50; }
.cpp-test-page h2 { color: #34495e; margin-top: 0; }
.cpp-test-section { background: #fff; }
.cpp-test-info { background: #f8f9fa; }
.cpp-test-info ul { margin: 10px 0; }
</style>

<?php get_footer(); ?>