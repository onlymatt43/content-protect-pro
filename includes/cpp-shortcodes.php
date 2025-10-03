<?php
/**
 * Shortcodes & Frontend rendering for Content Protect Pro
 * - [sv_library]        : Vidéothèque (auto ou custom)
 * - [sv_filters_cloud]  : Nuage de filtres (auto ou custom)
 * - Player modal + WATCH button
 * - CSS & JS injection
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Debug: log when this file is loaded (remove in production)
if (function_exists('error_log')) {
  error_log('[Content Protect Pro][DEBUG] includes/cpp-shortcodes.php loaded');
}

/* =========================
   Shortcode: [sv_library]
   - auto complet (par défaut)
   - filters="off" : sans nuage
   - custom="1" : pas de génération automatique (vidéos placées à la main)
   ========================= */
function sv_shortcode_library($atts = []){
    $a = shortcode_atts([
        'filters' => 'on',
        'custom'  => '0',
    ], $atts, 'sv_library');

    ob_start(); ?>
    <div id="sv-library-wrap">
      <?php
      // Inclure filtres seulement si pas custom et pas off
      if ($a['custom'] !== '1' && $a['filters'] !== 'off') {
          if (function_exists('sv_render_filters_cloud_breakdance')) {
              sv_render_filters_cloud_breakdance();
          }
      }
      ?>
      <?php if ($a['custom'] !== '1'): ?>
        <div id="sv-items"></div>
      <?php else: ?>
        <div id="sv-items"><!-- Custom mode: vidéos ajoutées manuellement dans Breakdance --></div>
      <?php endif; ?>
    </div>

    <!-- Modal player -->
    <div id="sv-player-modal" style="display:none;">
      <div id="sv-player-inner"></div>
      <button id="sv-close-player">Fermer</button>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('sv_library','sv_shortcode_library');

// Debug: confirm sv_library registration
if (function_exists('error_log')) {
  error_log('[Content Protect Pro][DEBUG] shortcode sv_library registered: ' . (shortcode_exists('sv_library') ? 'yes' : 'no'));
}

/* =========================
   Shortcode: [sv_filters_cloud]
   - nuage auto par défaut
   - custom="1" : n’injecte que JS/CSS (tu poses tes propres boutons)
   ========================= */
add_shortcode('sv_filters_cloud', function($atts){
    $a = shortcode_atts(['custom'=>'0'], $atts, 'sv_filters_cloud');
    global $sv_filters_cloud_needed;
    $sv_filters_cloud_needed = true;

    if ($a['custom']==='1') {
        return ''; // rien à afficher, seulement le moteur
    }

    $cats = get_terms(['taxonomy'=>'category','hide_empty'=>false]);
    $tags = get_terms(['taxonomy'=>'post_tag','hide_empty'=>false]);
    $terms = array_merge(is_array($cats)?$cats:[], is_array($tags)?$tags:[]);
    shuffle($terms);

    ob_start(); ?>
    <div id="sv-filters-cloud" data-mode="breakdance">
      <a href="#" class="sv-pill bde-button bde-button--natural sv-reset" data-action="reset"><span>Tout</span></a>
      <?php foreach ($terms as $t):
        $isCat = ($t->taxonomy === 'category');
        $key   = ($isCat ? 'cat-' : 'tag-') . $t->term_id;
      ?>
        <a href="#"
           class="sv-pill bde-button bde-button--natural"
           data-filter="<?php echo esc_attr($key); ?>"
           data-type="<?php echo $isCat ? 'cat':'tag'; ?>">
          <span><?php echo esc_html($t->name); ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
});

/* =========================
   CSS (nuage + vidéos)
   ========================= */
add_action('wp_head', function(){
    if (is_admin()) return;
    global $sv_filters_cloud_needed;
    ?>
    <style>
    /* === Nuage de filtres === */
    #sv-filters-cloud{
      position:sticky; top:12px; z-index:50;
      margin:0 auto 14px; max-width:1200px; padding:10px 12px;
      display:flex; flex-wrap:wrap; gap:10px; justify-content:center;
      background:rgba(0,0,0,0.25); border-radius:16px; backdrop-filter:blur(2px);
      box-shadow:inset 0 8px 30px rgba(0,0,0,0.15);
    }
    #sv-filters-cloud .sv-pill{
      --sv-yellow:#ffd800;
      text-decoration:none;
      border:1px solid rgba(255,255,0,0.6);
      border-radius:999px;
      padding:10px 14px;
      color:var(--sv-yellow);
      background:transparent;
      font-weight:700; font-size:12px;
      display:inline-flex; align-items:center; justify-content:center; gap:8px;
      transition:transform .15s ease, box-shadow .15s ease, background .15s ease, color .15s ease, border-color .15s ease;
    }
    #sv-filters-cloud .sv-pill:hover{ transform:translateY(-1px); box-shadow:0 2px 10px rgba(0,0,0,0.15); }
    #sv-filters-cloud .sv-pill.active{ background:var(--sv-yellow); color:transparent; border-color:var(--sv-yellow); }
    #sv-filters-cloud .sv-pill.sv-reset{ border-color:rgba(255,255,255,0.6); color:#fff; background:rgba(0,0,0,0.5); }
    #sv-filters-cloud .sv-pill.sv-reset:hover{ background:var(--sv-yellow); color:#000; border-color:var(--sv-yellow); }

    /* === Vidéos === */
    #sv-items{display:flex;flex-wrap:wrap;gap:16px;justify-content:center}
    .sv-item{
      width:220px;border:1px solid #eee;padding:8px;text-align:center;
      background:#111; transition:transform .2s ease, box-shadow .2s ease;
      border-radius:8px; overflow:hidden; cursor:pointer; position:relative;
    }
    .sv-thumb{ position:relative; border-radius:6px; overflow:hidden; }
    .sv-thumb img, .sv-thumb video{
      width:100%; height:130px; object-fit:cover; display:block;
      filter:grayscale(40%) brightness(70%);
      transition:filter .2s ease, transform .2s ease, opacity .2s ease;
    }
    .sv-item:hover{ transform:scale(1.03); box-shadow:0 6px 20px rgba(0,0,0,.25); }
    .sv-item:hover .sv-thumb img, .sv-item:hover .sv-thumb video{ filter:none; transform:scale(1.04); }

    /* Overlay + bouton WATCH */
    .sv-hover-btn-wrap{
      position:absolute; inset:0;
      display:flex; align-items:center; justify-content:center;
      opacity:0; pointer-events:none; transition:opacity .25s ease;
      background:rgba(0,0,0,0.25);
    }
    .sv-item:hover .sv-hover-btn-wrap{ opacity:1; pointer-events:auto; }
    .sv-watch-btn{
      text-decoration:none;
      border:1px solid #fff; border-radius:999px;
      padding:10px 18px; background:transparent;
      color:#fff !important; font-weight:700; font-size:13px;
      display:inline-flex; align-items:center; justify-content:center;
      transition:all .2s ease;
    }
    .sv-watch-btn:hover{ background:#fff; color:#000 !important; }
    .sv-title{ color:#f0f0f0; font-size:14px; margin:8px 0 2px; line-height:1.3; }
    </style>
    <?php
});

/* =========================
   JS logique (filtres + player sécurisé)
   ========================= */
add_action('wp_footer', function(){
    if (is_admin()) return;
    ?>
    <script>
    (function(){
      const restBase = '<?php echo esc_url(rest_url("smartvideo/v1")); ?>';

      // ---- Filtres ----
      document.addEventListener('click', function(e){
        const a = e.target.closest('#sv-filters-cloud .sv-pill');
        if (!a) return;
        e.preventDefault();

        // Reset
        if (a.classList.contains('sv-reset') || a.dataset.action === 'reset') {
          document.querySelectorAll('#sv-filters-cloud .sv-pill.active').forEach(el=>el.classList.remove('active'));
          if (window.state) {
            state.selectedCats?.clear?.();
            state.selectedTags?.clear?.();
            state.search = '';
          }
          if (typeof window.applyFilters === 'function') window.applyFilters();
          return;
        }

        a.classList.toggle('active');
        if (window.state) {
          const key  = a.getAttribute('data-filter') || '';
          const type = a.getAttribute('data-type') || '';
          const set  = (type === 'cat') ? state.selectedCats : state.selectedTags;
          if (a.classList.contains('active')) set.add(key); else set.delete(key);
        }
        if (typeof window.applyFilters === 'function') window.applyFilters();
      }, false);

      // ---- Ouverture sécurisée ----
      async function openSecureVideo(videoId){
        let resp = await fetch(restBase + '/request-playback', {
          method:'POST', headers:{'Content-Type':'application/json'},
          body:JSON.stringify({video_id:videoId})
        });
        let data = await resp.json();

        if (data.error) {
          const code = prompt("Entrez votre code d'accès :");
          if (!code) return;
          const r = await fetch(restBase + '/redeem', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body:JSON.stringify({code})
          });
          const rr = await r.json();
          if (rr.error) { alert('Erreur : '+rr.error); return; }
          resp = await fetch(restBase + '/request-playback', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body:JSON.stringify({video_id:videoId})
          });
          data = await resp.json();
          if (data.error) { alert('Erreur lecture : '+data.error); return; }
        }

        const modal = document.getElementById('sv-player-modal');
        const inner = document.getElementById('sv-player-inner');
        if (data.playback_url) {
          inner.innerHTML = '<iframe src="'+data.playback_url+'" frameborder="0" allowfullscreen style="width:80vw;max-width:1200px;height:45vw;max-height:675px;"></iframe>';
        } else if (data.embed) {
          inner.innerHTML = data.embed;
        } else {
          alert('Aucune source de lecture fournie.'); return;
        }
        modal.style.display = 'block';
      }

      // ---- Clic sur bouton WATCH ----
      document.addEventListener('click', function(e){
        const btn = e.target.closest('.sv-watch-btn');
        if (!btn) return;
        e.preventDefault();
        const item = btn.closest('.sv-item');
        if (!item) return;
        openSecureVideo(item.dataset.id);
      });

      // ---- Fermer modal ----
      document.addEventListener('click', function(e){
        if (e.target && e.target.id === 'sv-close-player') {
          document.getElementById('sv-player-inner').innerHTML = '';
          document.getElementById('sv-player-modal').style.display = 'none';
        }
      });
    })();
    </script>
    <?php
});

// Backwards-compatible fallback: if the class-registered shortcode `cpp_video_library`
// is not available for any reason, register it to delegate to the sv_library output.
if (!shortcode_exists('cpp_video_library')) {
  add_shortcode('cpp_video_library', function($atts = []){
    // normalize attributes expected by sv_shortcode_library
    $a = shortcode_atts([
      'filters' => isset($atts['show_filters']) && $atts['show_filters'] === 'true' ? 'on' : 'on',
      'custom'  => '0',
    ], $atts, 'cpp_video_library');

    // Reuse the existing sv_shortcode_library implementation
    return sv_shortcode_library($a);
  });

  // Debug: confirm fallback registration
  if (function_exists('error_log')) {
      error_log('[Content Protect Pro][DEBUG] fallback cpp_video_library registered by includes/cpp-shortcodes.php');
  }
}