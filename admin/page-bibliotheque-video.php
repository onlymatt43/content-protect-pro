<?php
/**
 * Template: Bibliothèque Vidéo Complète
 * Description: Page affichant toutes les vidéos avec filtres et recherche
 */

// Page header
get_header();
?>

<div class="cpp-video-library-page">
    <div class="container">

        <!-- Page Header -->
        <header class="page-header">
            <h1><?php _e('Bibliothèque Vidéo', 'content-protect-pro'); ?></h1>
            <p><?php _e('Découvrez notre collection complète de vidéos exclusives', 'content-protect-pro'); ?></p>
        </header>

        <!-- Formulaire de code cadeau (si nécessaire) -->
        <section class="cpp-access-section">
            <h2><?php _e('Accès Premium', 'content-protect-pro'); ?></h2>
            <p><?php _e('Entrez votre code cadeau pour accéder à toutes les vidéos premium.', 'content-protect-pro'); ?></p>

            [cpp_giftcode_form redirect_url="<?php echo get_permalink(); ?>" success_message="Accès accordé à la bibliothèque complète!"]
        </section>

        <!-- Bibliothèque de vidéos -->
        <section class="cpp-videos-section">
            <h2><?php _e('Toutes nos vidéos', 'content-protect-pro'); ?></h2>

            [cpp_video_library per_page="24" columns="3" show_filters="true" show_search="true"]
        </section>

        <!-- Section conditionnelle pour membres -->
        <section class="cpp-members-section">
            [cpp_giftcode_check required_codes="PREMIUM,GOLD,VIP"
                success_content="<div class='cpp-premium-content'>
                    <h2>Contenu Exclusif Membres</h2>
                    <p>Merci d'être membre premium ! Voici du contenu exclusif :</p>
                    <ul>
                        <li>Accès anticipé aux nouvelles vidéos</li>
                        <li>Téléchargements HD disponibles</li>
                        <li>Support prioritaire</li>
                    </ul>
                </div>"
                failure_content="<div class='cpp-upgrade-prompt'>
                    <h3>Devenez Membre Premium</h3>
                    <p>Obtenez l'accès complet à toutes nos vidéos et fonctionnalités premium.</p>
                    <a href='/premium-signup' class='button button-primary'>S'inscrire</a>
                </div>"]
        </section>

        <!-- Statistiques -->
        <section class="cpp-stats-section">
            <h2><?php _e('Statistiques', 'content-protect-pro'); ?></h2>
            <div class="cpp-stats-grid">
                <div class="cpp-stat-card">
                    <span class="cpp-stat-number">[cpp_video_count]</span>
                    <span class="cpp-stat-label">Vidéos disponibles</span>
                </div>
                <div class="cpp-stat-card">
                    <span class="cpp-stat-number">[cpp_member_count]</span>
                    <span class="cpp-stat-label">Membres actifs</span>
                </div>
                <div class="cpp-stat-card">
                    <span class="cpp-stat-number">[cpp_views_count]</span>
                    <span class="cpp-stat-label">Vues totales</span>
                </div>
            </div>
        </section>

    </div>
</div>

<style>
.cpp-video-library-page {
    padding: 40px 0;
    background: #f8f9fa;
}

.cpp-video-library-page .container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.page-header {
    text-align: center;
    margin-bottom: 50px;
}

.page-header h1 {
    font-size: 2.5em;
    color: #333;
    margin-bottom: 10px;
}

.page-header p {
    font-size: 1.2em;
    color: #666;
}

.cpp-access-section,
.cpp-videos-section,
.cpp-members-section,
.cpp-stats-section {
    background: white;
    padding: 40px;
    margin-bottom: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.cpp-access-section h2,
.cpp-videos-section h2,
.cpp-members-section h2,
.cpp-stats-section h2 {
    color: #333;
    margin-bottom: 20px;
    font-size: 1.8em;
}

.cpp-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 30px;
}

.cpp-stat-card {
    text-align: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.cpp-stat-number {
    display: block;
    font-size: 2.5em;
    font-weight: bold;
    color: #007cba;
    margin-bottom: 5px;
}

.cpp-stat-label {
    color: #666;
    font-size: 0.9em;
}

.cpp-premium-content,
.cpp-upgrade-prompt {
    padding: 20px;
    border-radius: 8px;
    margin-top: 20px;
}

.cpp-premium-content {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.cpp-upgrade-prompt {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}

@media (max-width: 768px) {
    .cpp-video-library-page .container {
        padding: 0 15px;
    }

    .cpp-access-section,
    .cpp-videos-section,
    .cpp-members-section,
    .cpp-stats-section {
        padding: 20px;
    }

    .page-header h1 {
        font-size: 2em;
    }

    .cpp-stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
get_footer();
?>