<?php
/**
 * Plugin Name: AI Mass Article Creator
 * Plugin URI: https://github.com/portallcomua/Ai-mass-article-creator
 * Description: AI SEO article generator with thematic images, SEO metadata, FAQ Schema, internal linking, developer mode, WooCommerce product URL and GitHub auto-updates.
 * Version: 3.0.6.1
 * Author: UAServer
 * Author URI: https://uaserver.pp.ua
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * Tested up to: 6.5
 * Text Domain: ai-mass-article-creator
 * Domain Path: /languages
 * GitHub Plugin URI: portallcomua/Ai-mass-article-creator
 */

if (!defined('ABSPATH')) exit;

define('AMAC_VERSION', '3.0.6.1');
define('AMAC_PLUGIN_FILE', __FILE__);
define('AMAC_PLUGIN_BASENAME', plugin_basename(__FILE__));

final class AI_Mass_Article_Creator_3 {
    private $repo = 'portallcomua/Ai-mass-article-creator';
    private $free_limit = 20;
    private $default_product_url = 'https://uaserver.pp.ua/';

    public function __construct() {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'save_settings'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('wp_ajax_amac_generate', array($this, 'ajax_generate'));
        add_action('wp_ajax_amac_test_provider', array($this, 'ajax_test_provider'));
        add_action('admin_post_amac_license_verify', array($this, 'verify_license'));
        add_action('admin_notices', array($this, 'notices'));
        add_action('wp_head', array($this, 'print_schema_for_single'), 20);
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_github_updates'));
        add_filter('plugins_api', array($this, 'github_plugin_info'), 10, 3);
        
        // Дії для ручної перевірки оновлень
        add_action('admin_post_amac_check_updates', array($this, 'force_check_updates'));
        add_action('admin_post_amac_clear_update_cache', array($this, 'clear_update_cache'));

        // Перейменування папки після завантаження ZIP з GitHub
        add_filter('upgrader_source_selection', array($this, 'fix_github_plugin_folder'), 10, 4);
    }

    private function settings() {
        return array(
            'ai_provider' => get_option('amac_ai_provider', 'groq'),
            'groq_key' => get_option('amac_api_key', ''),
            'openai_key' => get_option('amac_openai_key', ''),
            'pexels_key' => get_option('amac_pexels_key', ''),
            'pixabay_key' => get_option('amac_pixabay_key', ''),
            'unsplash_key' => get_option('amac_unsplash_key', ''),
            'default_category' => (int) get_option('amac_default_category', 0),
            'license_key' => get_option('amac_license_key', ''),
            'license_valid' => (bool) get_option('amac_license_valid', false),
            'dev_mode' => (bool) get_option('amac_dev_mode', false),
            'product_url' => get_option('amac_product_url', $this->default_product_url),
            'language' => get_option('amac_language', 'uk'),
        );
    }

    public function force_check_updates() {
        if (!current_user_can('manage_options') || !check_admin_referer('amac_update_tools')) {
            wp_die('Security check failed');
        }

        delete_site_transient('update_plugins');
        wp_update_plugins();

        set_transient('amac_saved', 1, 30);
        wp_safe_redirect(admin_url('admin.php?page=amac-settings'));
        exit;
    }

    public function clear_update_cache() {
        if (!current_user_can('manage_options') || !check_admin_referer('amac_update_tools')) {
            wp_die('Security check failed');
        }

        delete_site_transient('update_plugins');
        delete_transient('amac_github_info');

        set_transient('amac_saved', 1, 30);
        wp_safe_redirect(admin_url('admin.php?page=amac-settings'));
        exit;
    }

    public function admin_assets($hook) {
        if (strpos($hook, 'ai-mass-article-creator') === false && strpos($hook, 'amac-settings') === false && strpos($hook, 'amac-license') === false) return;
        wp_add_inline_style('admin-bar', $this->css());
        wp_add_inline_script('jquery', $this->js());
        wp_localize_script('jquery', 'AMAC3', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('amac3_nonce'),
        ));
    }

    private function css() {
        return '.amac-wrap{max-width:1320px}.amac-hero{background:linear-gradient(135deg,#111827,#2563eb);color:#fff;border-radius:18px;padding:26px;margin:20px 0}.amac-hero h1{color:#fff;margin:0;font-size:32px}.amac-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:18px}.amac-card{background:#fff;border:1px solid #dcdcde;border-radius:16px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.04)}.amac-card h2,.amac-card h3{margin-top:0}.amac-field{margin:0 0 14px}.amac-field label{display:block;font-weight:600;margin-bottom:6px}.amac-field input,.amac-field select,.amac-field textarea{width:100%;max-width:100%;border-radius:8px;border:1px solid #c3c4c7;padding:8px 10px}.amac-muted{color:#646970;font-size:12px}.amac-badge{display:inline-block;background:#e7f5ff;color:#075985;border-radius:99px;padding:4px 10px;font-size:12px;font-weight:600}.amac-ok{color:#008a20}.amac-bad{color:#d63638}.amac-progress{display:none;background:#f0f0f1;border-radius:99px;overflow:hidden;margin:16px 0}.amac-progress span{display:block;background:#22c55e;color:#fff;text-align:center;padding:7px;width:0}.amac-log{display:none;background:#111827;color:#d1d5db;border-radius:14px;padding:14px;max-height:420px;overflow:auto;font-family:monospace;font-size:12px}.amac-table{width:100%;border-collapse:collapse}.amac-table td,.amac-table th{padding:9px;border-bottom:1px solid #374151;text-align:left}.amac-help{background:#f8fafc;border-left:4px solid #2563eb;padding:10px;border-radius:6px}.amac-checkboxes label{display:block;margin:5px 0}.amac-two{display:grid;grid-template-columns:1fr 1fr;gap:12px}.amac-error{color:#fca5a5}.amac-success{color:#86efac}@media(max-width:782px){.amac-two{grid-template-columns:1fr}}';
    }

    private function js() {
        return 'jQuery(function($){
            $("#amac-generate-form").on("submit",function(e){
                e.preventDefault();
                $("#amac-log").html("").show();
                $("#amac-progress").show().find("span").css("width","12%").text("Старт...");
                $("#amac-generate-btn").prop("disabled",true);
                var fd=new FormData(this); fd.append("action","amac_generate"); fd.append("nonce",AMAC3.nonce);
                $.ajax({url:AMAC3.ajax_url,type:"POST",data:fd,contentType:false,processData:false,
                    success:function(r){
                        if(r.success){
                            $("#amac-progress span").css("width","100%").text("Готово");
                            $("#amac-log").append("<div class=\\"amac-success\\">✅ "+r.data.message+"</div>");
                            if(r.data.results&&r.data.results.length){
                                var h="<table class=\\"amac-table\\"><tr><th>#</th><th>Стаття</th><th>SEO</th><th>Фото</th><th>Дія</th></tr>";
                                $.each(r.data.results,function(i,p){h+="<tr><td>"+(i+1)+"</td><td>"+p.title+(p.error?"<br><span class=\\"amac-error\\">"+p.error+"</span>":"")+"</td><td>"+p.score+"/100</td><td>"+(p.image?"✅":"❌")+"</td><td>"+(p.edit_url&&p.edit_url!=="#"?"<a target=\\"_blank\\" href=\\""+p.edit_url+"\\">Редагувати</a>":"—")+"</td></tr>"});
                                h+="</table>"; $("#amac-log").append(h);
                            }
                        } else {
                            $("#amac-progress span").css("width","100%").text("Помилка");
                            $("#amac-log").append("<div class=\\"amac-error\\">❌ "+(r.data&&r.data.message?r.data.message:"Помилка")+"</div>");
                        }
                        $("#amac-generate-btn").prop("disabled",false);
                    },
                    error:function(x){$("#amac-progress span").css("width","100%").text("AJAX error");$("#amac-log").append("<div class=\\"amac-error\\">❌ AJAX error: "+x.status+" "+x.statusText+"</div>");$("#amac-generate-btn").prop("disabled",false);}
                });
            });
            $(".amac-test-provider").on("click",function(e){e.preventDefault();var p=$(this).data("provider"),el=$(this);el.text("...");$.post(AMAC3.ajax_url,{action:"amac_test_provider",provider:p,nonce:AMAC3.nonce},function(r){el.text(r.success?"✅ OK":"❌ Error");alert(r.data.message)})});
        });';
    }

    public function admin_menu() {
        add_menu_page('AI Mass Article Creator', 'AI Mass Article', 'manage_options', 'ai-mass-article-creator', array($this, 'page_dashboard'), 'dashicons-welcome-write-blog', 20);
        add_submenu_page('ai-mass-article-creator', 'Generate', 'Generate', 'manage_options', 'ai-mass-article-creator', array($this, 'page_dashboard'));
        add_submenu_page('ai-mass-article-creator', 'Settings', 'Settings', 'manage_options', 'amac-settings', array($this, 'page_settings'));
        add_submenu_page('ai-mass-article-creator', 'License', 'License', 'manage_options', 'amac-license', array($this, 'page_license'));
    }

    public function save_settings() {
        if (!isset($_POST['amac_save_settings']) || !check_admin_referer('amac_save_settings')) return;
        $fields = array('amac_ai_provider','amac_api_key','amac_openai_key','amac_pexels_key','amac_pixabay_key','amac_unsplash_key','amac_product_url','amac_language');
        foreach ($fields as $field) {
            if (isset($_POST[$field])) update_option($field, sanitize_text_field(wp_unslash($_POST[$field])));
        }
        update_option('amac_default_category', isset($_POST['amac_default_category']) ? (int) $_POST['amac_default_category'] : 0);
        update_option('amac_dev_mode', !empty($_POST['amac_dev_mode']) ? 1 : 0);
        set_transient('amac_saved', 1, 30);
        wp_safe_redirect(admin_url('admin.php?page=amac-settings'));
        exit;
    }

    public function notices() {
        if (get_transient('amac_saved')) {
            delete_transient('amac_saved');
            echo '<div class="notice notice-success is-dismissible"><p>✅ AI Mass Article Creator: налаштування збережено.</p></div>';
        }
    }

    private function header_html($title='AI Mass Article Creator') {
        echo '<div class="wrap amac-wrap"><div class="amac-hero"><h1>'.esc_html($title).'</h1><p>Version '.esc_html(AMAC_VERSION).' — AI Content Studio Engine</p><span class="amac-badge">Free: '.$this->free_limit.' articles</span> <span class="amac-badge">SEO + FAQ Schema</span> <span class="amac-badge">Smart thematic images</span></div>';
    }

    public function page_dashboard() {
        $s = $this->settings();
        $generated = (int) get_option('amac_total_generated', 0);
        $remaining = $s['license_valid'] || $s['dev_mode'] ? '∞' : max(0, $this->free_limit - $generated);
        $cats = get_categories(array('hide_empty'=>false));
        $this->header_html('AI Mass Article Creator 3.0'); ?>
        <div class="amac-grid">
            <div class="amac-card"><h3>Статус</h3><p><strong>Згенеровано:</strong> <?php echo intval($generated); ?></p><p><strong>Залишилось:</strong> <?php echo esc_html($remaining); ?></p><p><strong>AI:</strong> <?php echo esc_html(strtoupper($s['ai_provider'])); ?></p><p><strong>Ліцензія:</strong> <?php echo $s['license_valid'] ? '<span class="amac-ok">активна</span>' : '<span class="amac-bad">free/test</span>'; ?></p></div>
            <div class="amac-card"><h3>Швидкий старт</h3><ol><li>Введи Groq або OpenAI key у Settings.</li><li>Для якісних фото додай Pexels/Pixabay/Unsplash.</li><li>Увімкни Developer/Test Mode для тестування без ліміту.</li><li>Згенеруй 1 статтю в чернетку.</li></ol><p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=amac-settings')); ?>">Налаштування</a></p></div>
        </div>
        <div class="amac-card" style="margin-top:18px"><h2>Генерація статей</h2>
            <form id="amac-generate-form">
                <div class="amac-field"><label>Тема / ключове слово</label><input name="topic" required placeholder="Напр. жіноча білизна, спорт, авто, книги, промисловість, WordPress SEO"></div>
                <div class="amac-two">
                    <div class="amac-field"><label>Кількість</label><select name="count"><?php for($i=1;$i<=20;$i++) echo '<option value="'.esc_attr($i).'" '.selected($i,1,false).'>'.esc_html($i).'</option>'; ?></select></div>
                    <div class="amac-field"><label>Довжина</label><select name="words"><option value="600">600 слів</option><option value="900" selected>900 слів</option><option value="1300">1300 слів</option></select></div>
                </div>
                <div class="amac-two">
                    <div class="amac-field"><label>Категорія</label><select name="category"><option value="0">AI створить/обере</option><?php foreach($cats as $c) echo '<option value="'.esc_attr($c->term_id).'">'.esc_html($c->name).'</option>'; ?></select></div>
                    <div class="amac-field"><label>Статус</label><select name="status"><option value="draft">Чернетка</option><option value="publish">Опублікувати</option></select></div>
                </div>
                <div class="amac-checkboxes"><strong>Функції</strong><label><input type="checkbox" name="featured" checked> Головне фото</label><label><input type="checkbox" name="inline" checked> 2–3 фото в статті</label><label><input type="checkbox" name="seo" checked> SEO Rank Math / Yoast</label><label><input type="checkbox" name="faq" checked> FAQ + JSON-LD Schema</label><label><input type="checkbox" name="links" checked> Внутрішня перелінковка</label><label><input type="checkbox" name="comments"> Реалістичні коментарі</label></div>
                <div id="amac-progress" class="amac-progress"><span>0%</span></div><div id="amac-log" class="amac-log"></div><p><button id="amac-generate-btn" class="button button-primary button-large">🚀 Генерувати</button></p>
            </form>
        </div></div><?php
    }

    public function page_settings() {
        $s = $this->settings();
        $cats = get_categories(array('hide_empty'=>false));
        $this->header_html('Settings'); ?>
        <form method="post" class="amac-grid"><?php wp_nonce_field('amac_save_settings'); ?><input type="hidden" name="amac_save_settings" value="1">
            <div class="amac-card"><h3>AI Provider</h3><div class="amac-field"><label>Provider</label><select name="amac_ai_provider"><option value="groq" <?php selected($s['ai_provider'],'groq'); ?>>Groq</option><option value="openai" <?php selected($s['ai_provider'],'openai'); ?>>OpenAI</option></select></div><div class="amac-field"><label>Groq API Key</label><input type="password" name="amac_api_key" value="<?php echo esc_attr($s['groq_key']); ?>"><p><a href="#" class="button amac-test-provider" data-provider="groq">Перевірити Groq</a></p></div><div class="amac-field"><label>OpenAI API Key</label><input type="password" name="amac_openai_key" value="<?php echo esc_attr($s['openai_key']); ?>"><p><a href="#" class="button amac-test-provider" data-provider="openai">Перевірити OpenAI</a></p></div></div>
            <div class="amac-card"><h3>Фото</h3><div class="amac-help">Порядок: Pexels → Pixabay → Unsplash → Openverse. AI сам створює англомовний фотозапит і negative keywords. Якщо фото не релевантне — краще не вставити фото, ніж вставити комп’ютер або корову.</div><div class="amac-field"><label>Pexels API Key</label><input type="password" name="amac_pexels_key" value="<?php echo esc_attr($s['pexels_key']); ?>"><span class="amac-muted">pexels.com/api</span></div><div class="amac-field"><label>Pixabay API Key</label><input type="password" name="amac_pixabay_key" value="<?php echo esc_attr($s['pixabay_key']); ?>"><span class="amac-muted">pixabay.com/api/docs</span></div><div class="amac-field"><label>Unsplash Access Key</label><input type="password" name="amac_unsplash_key" value="<?php echo esc_attr($s['unsplash_key']); ?>"><span class="amac-muted">unsplash.com/developers</span></div></div>
            <div class="amac-card"><h3>Системні</h3><div class="amac-field"><label>Категорія за замовчуванням</label><select name="amac_default_category"><option value="0">AI/не вибрано</option><?php foreach($cats as $c) echo '<option value="'.esc_attr($c->term_id).'" '.selected($s['default_category'],$c->term_id,false).'>'.esc_html($c->name).'</option>'; ?></select></div><div class="amac-field"><label>Мова інтерфейсу/контенту</label><select name="amac_language"><option value="uk" <?php selected($s['language'],'uk'); ?>>Українська</option><option value="en" <?php selected($s['language'],'en'); ?>>English</option></select></div><div class="amac-field"><label>Посилання на товар WooCommerce</label><input name="amac_product_url" value="<?php echo esc_attr($s['product_url']); ?>"></div><label><input type="checkbox" name="amac_dev_mode" value="1" <?php checked($s['dev_mode']); ?>> Developer/Test Mode без ліміту</label><p><button class="button button-primary">Зберегти</button></p>
            <hr>
            <h3>GitHub Updates</h3>
            <p>
                <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=amac_check_updates'), 'amac_update_tools')); ?>">
                    Перевірити оновлення
                </a>
                <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=amac_clear_update_cache'), 'amac_update_tools')); ?>">
                    Очистити кеш оновлень
                </a>
            </p>
            </div>
        </form></div><?php
    }

    public function page_license() {
        $s = $this->settings();
        $this->header_html('License'); ?>
        <div class="amac-grid"><div class="amac-card"><h3>Ліцензія</h3><p>Продаж через WooCommerce/PayPal. Повна перевірка через UAServer Hub буде додана наступним релізом без зміни інтерфейсу.</p><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="amac_license_verify"><?php wp_nonce_field('amac_license'); ?><div class="amac-field"><label>License Key</label><input name="license_key" value="<?php echo esc_attr($s['license_key']); ?>"></div><p><button class="button button-primary">Активувати</button> <a class="button" target="_blank" href="<?php echo esc_url($s['product_url']); ?>">Купити</a></p></form></div><div class="amac-card"><h3>Умови</h3><ul><li>Free: <?php echo intval($this->free_limit); ?> статей.</li><li>Pro: до 3 доменів.</li><li>localhost не рахується.</li></ul></div></div></div><?php
    }

    public function verify_license() {
        if (!current_user_can('manage_options') || !check_admin_referer('amac_license')) wp_die('Security check failed');
        $key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';
        if ($key && strlen($key) >= 6) {
            update_option('amac_license_key', $key);
            update_option('amac_license_valid', 1);
            wp_safe_redirect(admin_url('admin.php?page=amac-license'));
            exit;
        }
        wp_safe_redirect(admin_url('admin.php?page=amac-license'));
        exit;
    }

    public function ajax_test_provider() {
        check_ajax_referer('amac3_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(array('message'=>'No access'));
        $p = sanitize_text_field($_POST['provider'] ?? 'groq');
        $r = $this->ai_call('Reply OK only.', 10, $p);
        if (is_wp_error($r)) wp_send_json_error(array('message'=>$r->get_error_message()));
        wp_send_json_success(array('message'=>'API працює: '.$r));
    }

    public function ajax_generate() {
        check_ajax_referer('amac3_nonce','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(array('message'=>'No access'));
        $s = $this->settings();
        if (!$s['groq_key'] && !$s['openai_key']) wp_send_json_error(array('message'=>'Введіть Groq або OpenAI API Key у Settings.'));
        $generated = (int) get_option('amac_total_generated', 0);
        if (!$s['license_valid'] && !$s['dev_mode'] && $generated >= $this->free_limit) wp_send_json_error(array('message'=>'Free-ліміт 20 статей вичерпано. Активуйте ліцензію або увімкніть Developer Mode для тесту.'));

        $topic = sanitize_text_field(wp_unslash($_POST['topic'] ?? ''));
        if (!$topic) wp_send_json_error(array('message'=>'Тема порожня'));
        $count = min(20, max(1, (int)($_POST['count'] ?? 1)));
        if (!$s['license_valid'] && !$s['dev_mode']) $count = min($count, max(0, $this->free_limit - $generated));
        $words = (int)($_POST['words'] ?? 900);
        $status = in_array($_POST['status'] ?? 'draft', array('draft','publish'), true) ? $_POST['status'] : 'draft';
        $category = (int)($_POST['category'] ?? 0);
        $results = array();
        $errors = array();

        $titles = $this->generate_titles($topic, $count);
        if (is_wp_error($titles)) wp_send_json_error(array('message'=>$titles->get_error_message()));

        // Обов'язкова затримка після першого запиту тем, щоб скинути вікно TPM у Groq
        sleep(4);

        $cluster_posts = array();
        foreach ($titles as $idx => $title) {
            $data = $this->generate_article_package($topic, $title, $words);
            if (is_wp_error($data)) {
                $errors[] = $title . ': ' . $data->get_error_message();
                $results[] = array('title'=>esc_html($title),'score'=>0,'image'=>false,'edit_url'=>'#','error'=>$data->get_error_message());
                continue;
            }
            $cat_id = $category ?: $this->ensure_category(!empty($data['category']) ? $data['category'] : $topic);
            $post_id = wp_insert_post(array(
                'post_title' => $title,
                'post_content' => $data['html'],
                'post_status' => $status,
                'post_type' => 'post',
                'post_category' => array($cat_id),
                'meta_input' => array('_amac_generated'=>1,'_amac_quality_score'=>(int)$data['score'])
            ));
            if (is_wp_error($post_id) || !$post_id) {
                $msg = is_wp_error($post_id) ? $post_id->get_error_message() : 'wp_insert_post returned empty ID';
                $errors[] = $title . ': ' . $msg;
                $results[] = array('title'=>esc_html($title),'score'=>0,'image'=>false,'edit_url'=>'#','error'=>$msg);
                continue;
            }
            $cluster_posts[] = $post_id;
            if (!empty($_POST['seo'])) $this->apply_seo($post_id, $data, $topic);
            if (!empty($_POST['faq'])) $this->apply_faq_schema($post_id, $data['faq']);
            if (!empty($data['tags'])) wp_set_post_tags($post_id, array_map('sanitize_text_field', $data['tags']));
            $image = false;
            $image_plan = $this->image_plan($topic, $title, $data);
            if (!empty($_POST['featured'])) $image = (bool) $this->add_featured_image($post_id, $image_plan, $title);
            if (!empty($_POST['inline'])) $this->add_inline_images($post_id, $image_plan, $title);
            if (!empty($_POST['comments'])) $this->add_comments($post_id, $title);
            $results[] = array('title'=>esc_html($title),'score'=>(int)$data['score'],'image'=>$image,'edit_url'=>get_edit_post_link($post_id,'raw'));
            update_option('amac_total_generated', (int)get_option('amac_total_generated',0) + 1);
            
            // Якщо є наступні статті, збільшуємо затримку для стабільності
            if ($idx < count($titles) - 1) {
                sleep(5);
            }
        }

        if (!empty($_POST['links']) && count($cluster_posts) > 1) $this->link_cluster($cluster_posts);
        $created_count = count($cluster_posts);
        if ($created_count === 0) {
            $msg = $errors ? $errors[0] : 'невідома помилка';
            wp_send_json_error(array('message'=>'Статті не створені. Причина: '.$msg, 'results'=>$results));
        }
        wp_send_json_success(array('message'=>'Створено '.$created_count.' статей.', 'results'=>$results));
    }

    private function generate_titles($topic, $count) {
        $p = "Create {$count} unique Ukrainian SEO article titles for topic: {$topic}. No numbering. One per line. Specific, commercial/useful, not clickbait.";
        $r = $this->ai_call($p, 600);
        if (is_wp_error($r)) return $r;
        $lines = array_values(array_filter(array_map(function($v){
            return trim(preg_replace('/^[0-9\.\-\)\s]+/u', '', $v), " \t\n\r\0\x0B\"«»");
        }, explode("\n", $r))));
        while (count($lines) < $count) $lines[] = $topic . ' — практичні поради #' . (count($lines)+1);
        return array_slice($lines, 0, $count);
    }

    private function generate_article_package($topic, $title, $words) {
        // Оптимізований коротший промпт для збереження токенів (TPM)
        $prompt = "You are a Ukrainian SEO editor. Write a natural article. Topic: {$topic}. Title: {$title}. Length: {$words} words. Return ONLY valid JSON: {html, meta_title, meta_description, keywords(array), tags(array), category, faq(array of question/answer), score(1-100), image_search(string), image_negative(array)}. Use HTML tags (h2,h3,p,ul,ol,li,strong,em,table). No markdown formatting or markdown fences.";
        $r = $this->ai_call($prompt, 4000);
        if (is_wp_error($r)) return $r;
        $json = $this->extract_json($r);
        if (!$json) return $this->generate_article_html_fallback($topic, $title, $words);
        return $this->normalize_article_data($json, $topic, $title);
    }

    private function generate_article_html_fallback($topic, $title, $words) {
        $prompt = "Write a high-quality natural Ukrainian SEO article. Topic: {$topic}. Title: {$title}. Length about {$words} words. Return HTML only. Use h2,h3,p,ul,ol,li,strong,em,table,blockquote. No markdown fences, no CSS, no scripts, no images.";
        $r = $this->ai_call($prompt, 3000);
        if (is_wp_error($r)) return $r;
        $html = $this->sanitize_article_html($r);
        if (!$html) return new WP_Error('empty', 'AI повернув порожню статтю навіть у fallback HTML режимі.');
        $image = $this->generate_image_query_ai($topic, $title);
        return $this->normalize_article_data(array(
            'html' => $html,
            'meta_title' => $title,
            'meta_description' => mb_substr(wp_strip_all_tags($html), 0, 155),
            'keywords' => array($topic),
            'tags' => array($topic),
            'category' => $topic,
            'faq' => array(),
            'score' => 75,
            'image_search' => $image['search'],
            'image_negative' => $image['negative'],
        ), $topic, $title);
    }

    private function normalize_article_data($json, $topic, $title) {
        $json['html'] = $this->sanitize_article_html($json['html'] ?? '');
        if (!$json['html']) return new WP_Error('empty', 'Порожня стаття після очищення HTML.');
        $json['meta_title'] = sanitize_text_field($json['meta_title'] ?? $title);
        $json['meta_description'] = sanitize_text_field(mb_substr(wp_strip_all_tags($json['meta_description'] ?? wp_strip_all_tags($json['html'])), 0, 155));
        foreach (array('keywords','tags','image_negative') as $k) {
            if (!isset($json[$k]) || !is_array($json[$k])) $json[$k] = array();
            $json[$k] = array_values(array_filter(array_map('sanitize_text_field', $json[$k])));
        }
        if (!isset($json['faq']) || !is_array($json['faq'])) $json['faq'] = array();
        $json['category'] = sanitize_text_field($json['category'] ?? $topic);
        $json['score'] = max(1, min(100, (int)($json['score'] ?? 75)));
        $image_search = isset($json['image_search']) ? sanitize_text_field($json['image_search']) : '';
        if (!$image_search || $this->is_bad_image_query($image_search)) {
            $image = $this->generate_image_query_ai($topic, $title);
            $image_search = $image['search'];
            $json['image_negative'] = array_unique(array_merge($json['image_negative'], $image['negative']));
        }
        $json['image_search'] = $this->clean_image_query($image_search);
        $json['image_negative'] = array_unique(array_merge($json['image_negative'], $this->default_negative_keywords($topic)));
        return $json;
    }

    private function sanitize_article_html($html) {
        $html = trim((string)$html);
        // Замінено небезпечний рядок на регулярний вираз без використання «голих» зворотних лапок у коді
        $html = preg_replace('/^`{3}(?:html)?|`{3}$/m', '', $html);
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        $allowed = array('h2'=>array(),'h3'=>array(),'p'=>array(),'ul'=>array(),'ol'=>array(),'li'=>array(),'strong'=>array(),'em'=>array(),'table'=>array(),'thead'=>array(),'tbody'=>array(),'tr'=>array(),'th'=>array(),'td'=>array(),'blockquote'=>array(),'br'=>array());
        return wp_kses($html, $allowed);
    }

    private function extract_json($text) {
        $text = trim((string)$text);
        $text = preg_replace('/^`{3}(?:json)?|`{3}$/m', '', $text);
        $data = json_decode($text, true);
        if (is_array($data)) return $data;
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $data = json_decode($m[0], true);
            if (is_array($data)) return $data;
        }
        return null;
    }

    private function ai_call($prompt, $max = 1500, $provider = null) {
        $s = $this->settings();
        $provider = $provider ?: $s['ai_provider'];
        
        $max_retries = 3;
        $retry_delay = 5; // Початкова затримка у секундах при 429
        
        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            if ($provider === 'openai' && $s['openai_key']) {
                $res = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
                    'timeout'=>100,
                    'headers'=>array('Content-Type'=>'application/json','Authorization'=>'Bearer '.$s['openai_key']),
                    'body'=>wp_json_encode(array('model'=>'gpt-4o-mini','messages'=>array(array('role'=>'user','content'=>$prompt)),'temperature'=>0.65,'max_tokens'=>$max))
                ));
            } else {
                if (!$s['groq_key']) return new WP_Error('api','Groq key missing');
                $res = wp_remote_post('https://api.groq.com/openai/v1/chat/completions', array(
                    'timeout'=>100,
                    'headers'=>array('Content-Type'=>'application/json','Authorization'=>'Bearer '.$s['groq_key']),
                    'body'=>wp_json_encode(array('model'=>'llama-3.1-8b-instant','messages'=>array(array('role'=>'user','content'=>$prompt)),'temperature'=>0.65,'max_tokens'=>$max))
                ));
            }

            if (is_wp_error($res)) {
                return $res;
            }

            $code = wp_remote_retrieve_response_code($res);
            $raw = wp_remote_retrieve_body($res);
            $body = json_decode($raw, true);

            // Якщо зловили 429 (Rate limit)
            if ($code === 429 && $attempt < $max_retries) {
                // Використовуємо експоненціальний відкат (delay * attempt)
                sleep($retry_delay * $attempt);
                continue;
            }

            if ($code < 200 || $code >= 300) {
                return new WP_Error('api', 'AI API HTTP '.$code.': '.mb_substr($raw, 0, 300));
            }
            if (isset($body['error']['message'])) {
                return new WP_Error('api', $body['error']['message']);
            }
            $content = trim($body['choices'][0]['message']['content'] ?? '');
            if ($content === '') {
                return new WP_Error('api', 'AI повернув порожню відповідь.');
            }
            return $content;
        }
        
        return new WP_Error('api', 'Превищено ліміт запитів до AI API. Спробуйте ще раз за хвилину.');
    }

    private function ensure_category($name) {
        $name = sanitize_text_field($name);
        if (!$name) $name = 'AI Articles';
        $term = term_exists($name, 'category');
        if ($term) return (int)(is_array($term) ? $term['term_id'] : $term);
        $term = wp_insert_term($name, 'category');
        return is_wp_error($term) ? 1 : (int)$term['term_id'];
    }

    private function apply_seo($post_id, $data, $topic) {
        $desc = mb_substr(wp_strip_all_tags($data['meta_description'] ?? ''), 0, 155);
        $keys = implode(', ', array_slice($data['keywords'] ?? array($topic), 0, 8));
        update_post_meta($post_id, '_amac_description', $desc);
        update_post_meta($post_id, '_amac_keywords', $keys);
        update_post_meta($post_id, 'rank_math_description', $desc);
        update_post_meta($post_id, 'rank_math_focus_keyword', $keys);
        update_post_meta($post_id, '_yoast_wpseo_metadesc', $desc);
        update_post_meta($post_id, '_yoast_wpseo_focuskw', $topic);
    }

    private function apply_faq_schema($post_id, $faq) {
        if ($faq) update_post_meta($post_id, '_amac_faq', $faq);
    }

    public function print_schema_for_single() {
        if (!is_single()) return;
        $faq = get_post_meta(get_the_ID(), '_amac_faq', true);
        if (!$faq || !is_array($faq)) return;
        $entities = array();
        foreach ($faq as $f) {
            if (empty($f['question']) || empty($f['answer'])) continue;
            $entities[] = array('@type'=>'Question','name'=>wp_strip_all_tags($f['question']),'acceptedAnswer'=>array('@type'=>'Answer','text'=>wp_strip_all_tags($f['answer'])));
        }
        if (!$entities) return;
        echo "\n<script type=\"application/ld+json\">" . wp_json_encode(array('@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>$entities), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "</script>\n";
    }

    private function generate_image_query_ai($topic, $title) {
        $prompt = "For stock photo search, convert this Ukrainian topic/title into ONE best English search query with concrete visual objects. Avoid abstract words. Return JSON only: {\"search\":\"...\",\"negative\":[\"...\"]}. Topic: {$topic}. Title: {$title}. Examples: жіноча білизна => women lingerie lace underwear fashion. спорт => fitness gym workout sports equipment. авто ремонт => car repair mechanic garage. книги для дітей => children books reading education. промисловість => industrial factory manufacturing. Never use blog, style, design, professional, business, office, computer, laptop unless topic is really computers.";
        $r = $this->ai_call($prompt, 300);
        $fallback = array('search'=>$this->fallback_image_query($topic.' '.$title), 'negative'=>$this->default_negative_keywords($topic));
        if (is_wp_error($r)) return $fallback;
        $j = $this->extract_json($r);
        if (!$j || empty($j['search'])) return $fallback;
        return array('search'=>$this->clean_image_query($j['search']), 'negative'=>is_array($j['negative'] ?? null) ? $j['negative'] : $fallback['negative']);
    }

    private function image_plan($topic, $title, $data = array()) {
        $search = !empty($data['image_search']) ? $data['image_search'] : '';
        if (!$search || $this->is_bad_image_query($search)) $search = $this->fallback_image_query($topic.' '.$title);
        $negative = !empty($data['image_negative']) && is_array($data['image_negative']) ? $data['image_negative'] : array();
        return array('search'=>$this->clean_image_query($search), 'negative'=>array_unique(array_merge($negative, $this->default_negative_keywords($topic))));
    }

    private function fallback_image_query($source) {
        $source_l = mb_strtolower($source, 'UTF-8');
        $map = array(
            'жіноча білизна'=>'women lingerie lace underwear fashion','нижня білизна'=>'women lingerie lace underwear','білизна'=>'lingerie lace underwear','трусики'=>'women panties underwear fashion','бюстгальтер'=>'bra lingerie fashion','купальник'=>'women swimsuit beachwear','піжама'=>'silk pajamas sleepwear','корсет'=>'corset lingerie fashion','панчохи'=>'women stockings fashion',
            'спорт'=>'fitness gym workout sports equipment','фітнес'=>'fitness gym workout','тренування'=>'fitness workout exercise','йога'=>'yoga mat exercise','футбол'=>'football soccer sport','баскетбол'=>'basketball court sport',
            'авто'=>'car automobile vehicle','автомобіль'=>'car automobile vehicle','ремонт авто'=>'car repair mechanic garage','шини'=>'car tires garage','двигун'=>'car engine mechanic',
            'книги'=>'books reading library','книга'=>'book reading library','дітей'=>'children books reading education','освіта'=>'education classroom learning',
            'промисловість'=>'industrial factory manufacturing','завод'=>'industrial factory manufacturing','виробництво'=>'manufacturing factory production','обладнання'=>'industrial equipment machinery',
            'елеватор'=>'grain elevator silo agriculture','агро'=>'agriculture field farming','зерно'=>'grain wheat agriculture','ферма'=>'farm agriculture field',
            'сімейний budget'=>'family budget personal finance','бюджет'=>'personal finance budget','фінанси'=>'personal finance money','гроші'=>'money personal finance',
            'wordpress'=>'wordpress website','seo'=>'seo marketing analytics','сайт'=>'website design screen'
        );
        foreach ($map as $k=>$v) if (mb_strpos($source_l, $k) !== false) return $v;
        if (preg_match('/[А-Яа-яІіЇїЄєҐґ]/u', $source_l)) return 'relevant editorial photo';
        return $this->clean_image_query($source_l ?: 'relevant editorial photo');
    }

    private function clean_image_query($q) {
        $q = strtolower(trim(wp_strip_all_tags((string)$q)));
        $q = preg_replace('/[^a-z0-9\s\-]/i', ' ', $q);
        $q = preg_replace('/\s+/', ' ', $q);
        $bad = $this->bad_words();
        $parts = array_filter(explode(' ', $q), function($w) use ($bad){ return $w !== '' && !in_array($w, $bad, true); });
        $out = implode(' ', array_slice($parts, 0, 8));
        return $out ?: 'relevant editorial photo';
    }

    private function is_bad_image_query($q) {
        $q = strtolower((string)$q);
        if (trim($q) === '' || strlen(trim($q)) < 4) return true;
        foreach (array('blog','style','design','professional','business','office','computer','laptop','desktop','coffee','cow','landscape') as $bad) {
            if (preg_match('/\b'.preg_quote($bad,'/').'\b/i', $q)) return true;
        }
        return false;
    }

    private function bad_words() {
        return array('blog','style','design','professional','business','office','computer','laptop','desktop','coffee','cow','landscape','random','abstract');
    }

    private function default_negative_keywords($topic='') {
        $base = array('computer','laptop','desktop','office','business','coffee','cow','landscape','random','abstract','cartoon','illustration');
        $t = mb_strtolower((string)$topic, 'UTF-8');
        if (mb_strpos($t, 'авто') !== false || mb_strpos($t, 'машин') !== false) return array_diff($base, array());
        if (mb_strpos($t, 'комп') !== false || mb_strpos($t, 'ноут') !== false || mb_strpos($t, 'офіс') !== false) return array('cow','landscape','random','abstract');
        return $base;
    }

    private function fetch_image_url($plan, $exclude = array()) {
        $query = is_array($plan) ? $plan['search'] : $plan;
        $negative = is_array($plan) ? ($plan['negative'] ?? array()) : array();
        foreach (array('pexels','pixabay','unsplash','openverse') as $p) {
            $u = $this->{'image_'.$p}($query, $exclude, $negative);
            if ($u) return $u;
        }
        return false;
    }

    private function photo_is_allowed($text, $negative) {
        $text = strtolower((string)$text);
        foreach ((array)$negative as $bad) {
            $bad = strtolower(trim((string)$bad));
            if ($bad && preg_match('/\b'.preg_quote($bad,'/').'\b/i', $text)) return false;
        }
        return true;
    }

    private function image_pexels($q, $exclude=array(), $negative=array()) {
        $k = get_option('amac_pexels_key',''); if (!$k) return false;
        $r = wp_remote_get(add_query_arg(array('query'=>$q,'orientation'=>'landscape','per_page'=>15),'https://api.pexels.com/v1/search'), array('timeout'=>15,'headers'=>array('Authorization'=>$k)));
        if (is_wp_error($r) || wp_remote_retrieve_response_code($r) != 200) return false;
        $d = json_decode(wp_remote_retrieve_body($r), true);
        foreach (($d['photos'] ?? array()) as $p) {
            $txt = ($p['alt'] ?? '') . ' ' . ($p['photographer'] ?? '');
            $u = $p['src']['large2x'] ?? $p['src']['large'] ?? '';
            if ($u && !in_array($u, $exclude, true) && $this->photo_is_allowed($txt, $negative)) return esc_url_raw($u);
        }
        return false;
    }

    private function image_pixabay($q, $exclude=array(), $negative=array()) {
        $k = get_option('amac_pixabay_key',''); if (!$k) return false;
        $r = wp_remote_get(add_query_arg(array('key'=>$k,'q'=>$q,'image_type'=>'photo','orientation'=>'horizontal','safesearch'=>'true','per_page'=>15,'lang'=>'en'),'https://pixabay.com/api/'), array('timeout'=>15));
        if (is_wp_error($r) || wp_remote_retrieve_response_code($r) != 200) return false;
        $d = json_decode(wp_remote_retrieve_body($r), true);
        foreach (($d['hits'] ?? array()) as $p) {
            $txt = ($p['tags'] ?? '');
            $u = $p['largeImageURL'] ?? $p['webformatURL'] ?? '';
            if ($u && !in_array($u, $exclude, true) && $this->photo_is_allowed($txt, $negative)) return esc_url_raw($u);
        }
        return false;
    }

    private function image_unsplash($q, $exclude=array(), $negative=array()) {
        $k = get_option('amac_unsplash_key',''); if (!$k) return false;
        $r = wp_remote_get(add_query_arg(array('query'=>$q,'orientation'=>'landscape','content_filter'=>'high','client_id'=>$k),'https://api.unsplash.com/photos/random'), array('timeout'=>15));
        if (is_wp_error($r) || wp_remote_retrieve_response_code($r) != 200) return false;
        $d = json_decode(wp_remote_retrieve_body($r), true);
        $txt = ($d['description'] ?? '') . ' ' . ($d['alt_description'] ?? '');
        $u = $d['urls']['regular'] ?? '';
        return ($u && !in_array($u, $exclude, true) && $this->photo_is_allowed($txt, $negative)) ? esc_url_raw($u) : false;
    }

    private function image_openverse($q, $exclude=array(), $negative=array()) {
        $r = wp_remote_get(add_query_arg(array('q'=>$q,'page_size'=>15,'license_type'=>'commercial','extension'=>'jpg'),'https://api.openverse.engineering/v1/images/'), array('timeout'=>15));
        if (is_wp_error($r) || wp_remote_retrieve_response_code($r) != 200) return false;
        $d = json_decode(wp_remote_retrieve_body($r), true);
        foreach (($d['results'] ?? array()) as $p) {
            $txt = ($p['title'] ?? '') . ' ' . ($p['tags'] ?? '') . ' ' . ($p['creator'] ?? '');
            $u = $p['url'] ?? '';
            if ($u && !in_array($u, $exclude, true) && $this->photo_is_allowed($txt, $negative)) return esc_url_raw($u);
        }
        return false;
    }

    private function sideload($post_id, $url, $title, $featured=false) {
        if (!$url) return false;
        require_once ABSPATH.'wp-admin/includes/media.php';
        require_once ABSPATH.'wp-admin/includes/file.php';
        require_once ABSPATH.'wp-admin/includes/image.php';
        $tmp = download_url($url, 20);
        if (is_wp_error($tmp)) return false;
        $file = array('name'=>sanitize_title($title).'-'.time().'-'.mt_rand(100,999).'.jpg','tmp_name'=>$tmp);
        $id = media_handle_sideload($file, $post_id);
        if (is_wp_error($id)) { @unlink($tmp); return false; }
        update_post_meta($id, '_wp_attachment_image_alt', sanitize_text_field($title));
        wp_update_post(array('ID'=>$id, 'post_title'=>sanitize_text_field($title), 'post_excerpt'=>sanitize_text_field($title)));
        if ($featured) set_post_thumbnail($post_id, $id);
        return $id;
    }

    private function add_featured_image($post_id, $plan, $title) {
        $u = $this->fetch_image_url($plan);
        return $this->sideload($post_id, $u, $title, true);
    }

    private function add_inline_images($post_id, $plan, $title) {
        $content = get_post_field('post_content', $post_id);
        $parts = explode('</p>', $content);
        if (count($parts) < 4) return;
        $used = array();
        $positions = array_unique(array((int)floor(count($parts)*.35), (int)floor(count($parts)*.65)));
        rsort($positions);
        foreach ($positions as $pos) {
            $u = $this->fetch_image_url($plan, $used);
            if (!$u) continue;
            $used[] = $u;
            $id = $this->sideload($post_id, $u, $title, false);
            if (!$id) continue;
            $src = wp_get_attachment_image_url($id, 'large');
            if (!$src) continue;
            $parts[$pos] .= '</p><figure class="amac-inline-image"><img src="'.esc_url($src).'" alt="'.esc_attr($title).'" loading="lazy" style="width:100%;height:auto;border-radius:10px"><figcaption>'.esc_html($title).'</figcaption></figure>';
        }
        wp_update_post(array('ID'=>$post_id,'post_content'=>implode('', $parts)));
    }

    private function add_comments($post_id, $title) {
        $r = $this->ai_call("Generate 4 short realistic Ukrainian comments for article '{$title}'. One per line. No numbering.", 400);
        if (is_wp_error($r)) return;
        $names = array('Олександр','Марія','Дмитро','Анна','Оксана','Іван');
        foreach (array_slice(array_filter(array_map('trim', explode("\n", $r))), 0, 4) as $c) {
            $c = preg_replace('/^[0-9\.\-\)\s]+/u', '', $c);
            if (mb_strlen($c) < 10) continue;
            wp_insert_comment(array('comment_post_ID'=>$post_id,'comment_content'=>wp_kses_post($c),'comment_approved'=>1,'comment_author'=>$names[array_rand($names)],'comment_author_email'=>'user'.rand(100,999).'@example.com'));
        }
    }

    private function link_cluster($ids) {
        foreach ($ids as $id) {
            $content = get_post_field('post_content', $id);
            $links = '';
            foreach ($ids as $other) {
                if ($other == $id) continue;
                $links .= '<li><a href="'.esc_url(get_permalink($other)).'">'.esc_html(get_the_title($other)).'</a></li>';
            }
            if ($links) wp_update_post(array('ID'=>$id,'post_content'=>$content.'<h2>Читайте також</h2><ul>'.$links.'</ul>'));
        }
    }

    public function check_github_updates($transient) {
        if (empty($transient->checked)) return $transient;

        $remote = $this->github_info();

        if ($remote && version_compare(AMAC_VERSION, $remote->version, '<')) {
            $transient->response[AMAC_PLUGIN_BASENAME] = (object) array(
                'slug' => 'ai-mass-article-creator',
                'plugin' => AMAC_PLUGIN_BASENAME,
                'new_version' => $remote->version,
                'package' => $remote->download_url,
                'tested' => '6.5',
                'url' => 'https://github.com/'.$this->repo,
            );
        }

        return $transient;
    }

    public function github_plugin_info($false, $action, $args) {
        if ($action !== 'plugin_information' || $args->slug !== 'ai-mass-article-creator') return $false;
        $r = $this->github_info();
        if (!$r) return $false;
        return (object) array(
            'name'=>'AI Mass Article Creator',
            'slug'=>'ai-mass-article-creator',
            'version'=>$r->version,
            'download_link'=>$r->download_url,
            'requires'=>'5.8',
            'tested'=>'6.5',
            'sections'=>array('description'=>'AI SEO article generator with thematic images, SEO, FAQ Schema and internal linking.','changelog'=>$r->body),
        );
    }

    private function github_info() {
        $cached = get_transient('amac_github_info');
        if ($cached) return $cached;

        $res = wp_remote_get('https://api.github.com/repos/'.$this->repo.'/releases/latest', array(
            'timeout' => 15,
            'headers' => array('Accept' => 'application/vnd.github+json')
        ));

        if (is_wp_error($res)) return null;

        $d = json_decode(wp_remote_retrieve_body($res), true);

        if (empty($d['tag_name'])) return null;

        $version = ltrim($d['tag_name'], 'v');
        $download_url = '';

        if (!empty($d['assets']) && is_array($d['assets'])) {
            foreach ($d['assets'] as $asset) {
                if (!empty($asset['name']) && preg_match('/ai-mass-article-creator.*\.zip$/i', $asset['name'])) {
                    $download_url = $asset['browser_download_url'];
                    break;
                }
            }
        }

        if (!$download_url && !empty($d['zipball_url'])) {
            $download_url = $d['zipball_url'];
        }

        if (!$download_url) return null;

        $info = (object) array(
            'version' => $version,
            'download_url' => $download_url,
            'body' => isset($d['body']) ? $d['body'] : ''
        );

        set_transient('amac_github_info', $info, HOUR_IN_SECONDS);

        return $info;
    }

    public function fix_github_plugin_folder($source, $remote_source, $upgrader, $hook_extra) {
        global $wp_filesystem;

        if (empty($hook_extra['plugin']) || $hook_extra['plugin'] !== AMAC_PLUGIN_BASENAME) {
            return $source;
        }

        $correct_source = trailingslashit($remote_source) . 'ai-mass-article-creator';

        if ($source === $correct_source) {
            return $source;
        }

        if ($wp_filesystem->exists($correct_source)) {
            $wp_filesystem->delete($correct_source, true);
        }

        if ($wp_filesystem->move($source, $correct_source, true)) {
            return $correct_source;
        }

        return $source;
    }
}

new AI_Mass_Article_Creator_3();

// End і версія 3.0.6.1
