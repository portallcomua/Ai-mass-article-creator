<?php
/**
 * Plugin Name: AI Mass Article Creator
 * Plugin URI: https://github.com/portallcomua/Ai-mass-article-creator
 * Description: AI SEO article generator with thematic images, SEO metadata, FAQ Schema, internal linking, developer mode, WooCommerce product URL and GitHub auto-updates.
 * Version: 3.0.4
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

define('AMAC_VERSION', '3.0.4');
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
            if ($idx > 0) sleep(2);
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
        $prompt = "You are a professional Ukrainian SEO editor. Write a natural, useful, unique article, not generic AI text. Topic: {$topic}. Title: {$title}. Length about {$words} words. Return ONLY valid JSON with keys: html, meta_title, meta_description, keywords(array), tags(array), category, faq(array of objects question/answer), score(number 1-100), image_search(string), image_negative(array). image_search must be the best English stock-photo search query using concrete physical subjects, not abstract words. image_negative must include irrelevant objects that must not appear in photos. HTML may include h2,h3,p,ul,ol,li,strong,em,table,thead,tbody,tr,th,td,blockquote. Include useful lists, one pros/cons section if relevant, and practical advice. Do not include markdown fences.";
        $r = $this->ai_call($prompt, 4200);
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
        $html = trim(preg_replace('/^
http://googleusercontent.com/immersive_entry_chip/0
http://googleusercontent.com/immersive_entry_chip/1
