<?php
/**
 * Plugin Name: AI Mass Article Creator
 * Plugin URI: https://github.com/portallcomua/ai-mass-article-creator
 * Description: Generate 1-20 unique SEO articles with AI, automatic images, and 3-5 REAL user comments per article
 * Version: 2.3.2
 * Author: UAServer
 * Author URI: https://uaserver.pp.ua
 * License: GPL v2 or later
 * GitHub Plugin URI: portallcomua/ai-mass-article-creator
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * Tested up to: 6.5
 */

if (!defined('ABSPATH')) exit;

define('AMAC_VERSION', '2.3.2');

class AI_Mass_Article_Creator {

    private $payhip_product_url = 'https://payhip.com/b/bw9ly';

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_settings_save'));
        add_action('admin_post_amac_test_api', array($this, 'test_api_connection'));
        add_action('admin_post_amac_license_verify', array($this, 'verify_license'));
        add_action('wp_ajax_amac_generate', array($this, 'generate_posts_ajax'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_notices', array($this, 'show_admin_notices'));

        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_github_updates'));
        add_filter('plugins_api', array($this, 'github_plugin_info'), 10, 3);
    }

    public function enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_ai-mass-article-creator' && $hook !== 'ai-mass-article_page_amac-settings') return;

        wp_add_inline_style('admin-bar', $this->get_admin_css());
        wp_add_inline_script('jquery', $this->get_admin_js());
        wp_localize_script('jquery', 'amac_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('amac_ajax'),
        ));
    }

    private function get_admin_css() {
        return '
        .amac-container { max-width: 1400px; margin: 20px 0; }
        .amac-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .amac-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 12px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .amac-balance { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .amac-balance .balance-amount { font-size: 42px; font-weight: bold; margin: 15px 0; }
        .amac-progress { background: #f0f0f0; border-radius: 8px; overflow: hidden; margin: 15px 0; display: none; }
        .amac-progress-bar { background: #46b450; color: white; padding: 8px; text-align: center; width: 0%; transition: width 0.3s; }
        .amac-log { background: #1e1e1e; color: #ddd; padding: 15px; border-radius: 8px; font-family: monospace; max-height: 400px; overflow-y: auto; font-size: 12px; margin-top: 15px; display: none; }
        .amac-results-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .amac-results-table th, .amac-results-table td { padding: 10px; text-align: left; border-bottom: 1px solid #333; }
        .amac-results-table th { color: #46b450; }
        .amac-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; background: #d4edda; color: #155724; }
        .amac-form-group { margin-bottom: 15px; }
        .amac-form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .amac-form-group input, .amac-form-group select { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; }
        ';
    }

    private function get_admin_js() {
        return '
        jQuery(document).ready(function($) {
            $("#amac-generate-form").on("submit", function(e) {
                e.preventDefault();

                var topic = $("#amac_topic").val();
                if (!topic) { alert("Введіть тему!"); return false; }

                $("#amac-progress").show();
                $("#amac-log").html("").show();
                $("#amac-generate-btn").prop("disabled", true);
                $("#amac-progress-bar").css("width", "10%").text("Генерація...");

                var formData = new FormData(this);
                formData.append("action", "amac_generate");
                formData.append("nonce", amac_ajax.nonce);

                $.ajax({
                    url: amac_ajax.ajax_url,
                    type: "POST",
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            $("#amac-log").append("<div style=\"color:#46b450;padding:5px;\">✅ " + response.data.message + "</div>");
                            if (response.data.results && response.data.results.length) {
                                var html = "<table class=\"amac-results-table\"><thead><tr><th>#</th><th>Заголовок</th><th>Коментарі</th><th>Фото</th><th>Дія</th></td></thead><tbody>";
                                $.each(response.data.results, function(i, post) {
                                    html += "<tr>";
                                    html += "<td>" + (i+1) + "</td>";
                                    html += "<td><strong>" + post.title + "</strong></td>";
                                    html += "<td><span class=\"amac-badge\">" + post.comments_count + " комент.</span></td>";
                                    html += "<td>" + (post.image ? "✅" : "❌") + "</td>";
                                    html += "<td><a href=\"" + post.quetext_url + "\" target=\"_blank\" class=\"button button-small\">🔍 Перевірити</a></td>";
                                    html += "</tr>";
                                });
                                html += "</tbody></table>";
                                $("#amac-log").append(html);
                            }
                        } else {
                            $("#amac-log").append("<div style=\"color:#dc3232;padding:5px;\">❌ " + response.data.message + "</div>");
                        }
                        $("#amac-generate-btn").prop("disabled", false);
                        $("#amac-progress-bar").css("width", "100%").text("100%");
                    },
                    error: function(xhr, status, error) {
                        $("#amac-log").append("<div style=\"color:#dc3232;padding:5px;\">❌ Помилка: " + error + "</div>");
                        $("#amac-generate-btn").prop("disabled", false);
                        $("#amac-progress-bar").css("width", "0%").text("0%");
                    }
                });
                return false;
            });
        });
        ';
    }

    public function add_admin_menu() {
        add_menu_page(
            'AI Mass Article Creator',
            'AI Mass Article',
            'manage_options',
            'ai-mass-article-creator',
            array($this, 'render_admin_page'),
            'dashicons-welcome-write-blog',
            20
        );

        add_submenu_page(
            'ai-mass-article-creator',
            'Settings',
            'Settings',
            'manage_options',
            'amac-settings',
            array($this, 'render_settings_page')
        );
    }

    public function handle_settings_save() {
        if (!isset($_POST['amac_save_settings']) || !check_admin_referer('amac_save')) {
            return;
        }

        if (isset($_POST['api_key'])) {
            update_option('amac_api_key', sanitize_text_field($_POST['api_key']));
        }
        if (isset($_POST['default_category'])) {
            update_option('amac_default_category', intval($_POST['default_category']));
        }
        if (isset($_POST['unsplash_key'])) {
            update_option('amac_unsplash_key', sanitize_text_field($_POST['unsplash_key']));
        }

        set_transient('amac_settings_saved', 'yes', 30);

        wp_redirect(admin_url('admin.php?page=amac-settings'));
        exit;
    }

    public function show_admin_notices() {
        if (get_transient('amac_settings_saved')) {
            delete_transient('amac_settings_saved');
            echo '<div class="notice notice-success is-dismissible"><p>✅ Налаштування збережено!</p></div>';
        }
    }

    public function test_api_connection() {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'amac_test')) {
            wp_die('Security check failed');
        }

        $api_key = get_option('amac_api_key', '');
        if (empty($api_key)) {
            wp_redirect(admin_url('admin.php?page=ai-mass-article-creator&test_result=error&test_message=' . urlencode('API key is empty')));
            exit;
        }

        $response = wp_remote_post('https://api.groq.com/openai/v1/chat/completions', array(
            'timeout' => 15,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body' => json_encode(array(
                'model'      => 'llama-3.1-8b-instant',
                'messages'   => array(array('role' => 'user', 'content' => 'OK')),
                'max_tokens' => 5,
            )),
        ));

        if (is_wp_error($response)) {
            wp_redirect(admin_url('admin.php?page=ai-mass-article-creator&test_result=error&test_message=' . urlencode($response->get_error_message())));
            exit;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['error'])) {
            wp_redirect(admin_url('admin.php?page=ai-mass-article-creator&test_result=error&test_message=' . urlencode($body['error']['message'])));
            exit;
        }

        wp_redirect(admin_url('admin.php?page=ai-mass-article-creator&test_result=success&test_message=' . urlencode('✅ API key is valid!')));
        exit;
    }

    public function verify_license() {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'amac_license')) {
            wp_die('Security check failed');
        }

        $license_key = sanitize_text_field($_POST['license_key']);

        if (!empty($license_key) && strlen($license_key) > 5) {
            update_option('amac_license_valid', true);
            update_option('amac_license_key', $license_key);
            wp_redirect(admin_url('admin.php?page=ai-mass-article-creator&license_result=success&license_message=' . urlencode('✅ License activated!')));
            exit;
        }

        wp_redirect(admin_url('admin.php?page=ai-mass-article-creator&license_result=error&license_message=' . urlencode('❌ Invalid license key')));
        exit;
    }

    public function render_settings_page() {
        $api_key          = get_option('amac_api_key', '');
        $unsplash_key     = get_option('amac_unsplash_key', '');
        $default_category = get_option('amac_default_category', 0);
        $categories       = get_categories(array('hide_empty' => false));
        ?>
        <div class="wrap amac-container">
            <h1>AI Mass Article Creator — Налаштування</h1>

            <div class="amac-grid">
                <div class="amac-card">
                    <h3>🔑 Groq API</h3>
                    <p>Отримайте безкоштовний ключ на <a href="https://console.groq.com/signup" target="_blank">console.groq.com</a></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=amac-settings')); ?>">
                        <?php wp_nonce_field('amac_save'); ?>
                        <input type="hidden" name="amac_save_settings" value="1">
                        <div class="amac-form-group">
                            <label>Groq API Key:</label>
                            <input type="password" name="api_key" value="<?php echo esc_attr($api_key); ?>">
                        </div>
                        <div class="amac-form-group">
                            <label>Unsplash Access Key <small>(для тематичних фото)</small>:</label>
                            <input type="password" name="unsplash_key" value="<?php echo esc_attr($unsplash_key); ?>">
                            <small>Безкоштовно на <a href="https://unsplash.com/developers" target="_blank">unsplash.com/developers</a> → New Application</small>
                        </div>
                        <div class="amac-form-group">
                            <label>Категорія за замовчуванням:</label>
                            <select name="default_category">
                                <option value="0">-- Оберіть категорію --</option>
                                <?php foreach ($categories as $cat) : ?>
                                    <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected($default_category, $cat->term_id); ?>>
                                        <?php echo esc_html($cat->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="button button-primary">Зберегти налаштування</button>
                    </form>
                </div>

                <div class="amac-card">
                    <h3>💰 Ліцензія</h3>
                    <p><strong>Ціна:</strong> $47 (довічна ліцензія)</p>
                    <p><strong>Безкоштовно:</strong> 10 статей для тесту</p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('amac_license'); ?>
                        <input type="hidden" name="action" value="amac_license_verify">
                        <div class="amac-form-group">
                            <label>Ключ ліцензії:</label>
                            <input type="text" name="license_key" placeholder="Введіть ваш ключ">
                        </div>
                        <button type="submit" class="button button-primary">Активувати ліцензію</button>
                    </form>
                    <p><a href="<?php echo esc_url($this->payhip_product_url); ?>" target="_blank">Купити ліцензію на Payhip →</a></p>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_admin_page() {
        $categories         = get_categories(array('hide_empty' => false));
        $api_key            = get_option('amac_api_key', '');
        $license_valid      = get_option('amac_license_valid', false);
        $articles_generated = intval(get_option('amac_total_generated', 0));
        $default_category   = get_option('amac_default_category', 0);
        $test_limit         = 10;
        $remaining_free     = max(0, $test_limit - $articles_generated);
        $can_generate       = $license_valid || $articles_generated < $test_limit;

        if (isset($_GET['test_result'])) {
            $class = ($_GET['test_result'] === 'success') ? 'success' : 'error';
            echo '<div class="notice notice-' . esc_attr($class) . ' is-dismissible"><p>' . esc_html(urldecode($_GET['test_message'])) . '</p></div>';
        }
        if (isset($_GET['license_result'])) {
            $class = ($_GET['license_result'] === 'success') ? 'success' : 'error';
            echo '<div class="notice notice-' . esc_attr($class) . ' is-dismissible"><p>' . esc_html(urldecode($_GET['license_message'])) . '</p></div>';
        }
        ?>
        <div class="wrap amac-container">
            <h1>🤖 AI Mass Article Creator</h1>

            <div class="amac-grid">
                <div class="amac-card amac-balance">
                    <h3>📊 Ваш статус</h3>
                    <div class="balance-amount"><?php echo intval($articles_generated); ?> / <?php echo esc_html($test_limit); ?></div>
                    <p>Статей згенеровано (безкоштовний рівень)</p>
                    <?php if (!$license_valid && $remaining_free > 0) : ?>
                        <p>✅ <strong>Залишилось безкоштовних: <?php echo intval($remaining_free); ?></strong></p>
                    <?php elseif (!$license_valid && $remaining_free === 0) : ?>
                        <p>⚠️ <strong>Безкоштовний ліміт вичерпано! Придбайте ліцензію.</strong></p>
                    <?php else : ?>
                        <p>🎉 <strong>Ліцензія активна! Необмежена кількість статей.</strong></p>
                    <?php endif; ?>
                </div>

                <div class="amac-card">
                    <h3>⚡ Швидкий старт</h3>
                    <ol>
                        <li>Отримайте <a href="https://console.groq.com/signup" target="_blank">безкоштовний Groq API ключ</a></li>
                        <li>Вставте ключ у <strong>Налаштування</strong> → Зберегти</li>
                        <li>Протестуйте ключ кнопкою нижче</li>
                        <li>Введіть тему → Натисніть Генерувати</li>
                    </ol>
                    <p>
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=amac_test_api'), 'amac_test')); ?>" class="button">✅ Протестувати API ключ</a>
                    </p>
                    <?php if (empty($api_key)) : ?>
                        <p style="color:red;">❌ API ключ не налаштовано!</p>
                    <?php else : ?>
                        <p style="color:green;">✅ API ключ налаштовано</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$can_generate) : ?>
                <div class="amac-card" style="background:#fff3cd; border-color:#ffeeba; margin-bottom:20px;">
                    <h3>⚠️ Безкоштовний ліміт вичерпано</h3>
                    <p>Ви використали всі <strong><?php echo esc_html($test_limit); ?></strong> безкоштовних статей.</p>
                    <p>Придбайте ліцензію щоб продовжити генерацію без обмежень.</p>
                    <a href="<?php echo esc_url($this->payhip_product_url); ?>" target="_blank" class="button button-primary">💰 Купити ліцензію ($47)</a>
                </div>
            <?php else : ?>
                <div class="amac-card">
                    <h3>📝 Генерація статей</h3>

                    <form id="amac-generate-form" method="post">
                        <div class="amac-form-group">
                            <label>Тема / Ключове слово:</label>
                            <input type="text" name="main_topic" id="amac_topic" required placeholder="напр. заробіток в інтернеті, веб-дизайн, SEO">
                        </div>

                        <div class="amac-grid" style="grid-template-columns: repeat(3, 1fr);">
                            <div class="amac-form-group">
                                <label>Кількість статей:</label>
                                <select name="post_count">
                                    <?php for ($i = 1; $i <= 20; $i++) : ?>
                                        <option value="<?php echo esc_attr($i); ?>" <?php echo ($i === 3) ? 'selected' : ''; ?>>
                                            <?php echo esc_html($i); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <div class="amac-form-group">
                                <label>Категорія:</label>
                                <select name="category_id">
                                    <?php foreach ($categories as $cat) : ?>
                                        <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected($default_category, $cat->term_id); ?>>
                                            <?php echo esc_html($cat->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="amac-form-group">
                                <label>Довжина статті:</label>
                                <select name="length">
                                    <option value="400">Коротка (~400 слів)</option>
                                    <option value="700" selected>Середня (~700 слів)</option>
                                    <option value="1000">Довга (~1000 слів)</option>
                                </select>
                            </div>
                        </div>

                        <div style="margin: 15px 0;">
                            <label><strong>Функції:</strong></label><br>
                            <label><input type="checkbox" name="add_images" value="1" checked> Додати головне зображення</label><br>
                            <label><input type="checkbox" name="add_inline_images" value="1" checked> Додати 2-3 зображення в тексті</label><br>
                            <label><input type="checkbox" name="add_tags" value="1" checked> Додати теги</label><br>
                            <label><input type="checkbox" name="add_seo" value="1" checked> Додати SEO мета-поля</label><br>
                            <label><input type="checkbox" name="add_comments" value="1" checked> Додати 3-5 унікальних коментарів</label>
                        </div>

                        <div id="amac-progress" class="amac-progress">
                            <div id="amac-progress-bar" class="amac-progress-bar">0%</div>
                        </div>
                        <div id="amac-log" class="amac-log"></div>

                        <button type="submit" id="amac-generate-btn" class="button button-primary button-large">🚀 Генерувати статті</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function generate_posts_ajax() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'amac_ajax')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }

        $api_key = get_option('amac_api_key', '');
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'API ключ не налаштовано. Перейдіть до Налаштувань.'));
        }

        $license_valid      = get_option('amac_license_valid', false);
        $articles_generated = intval(get_option('amac_total_generated', 0));
        $test_limit         = 10;

        if (!$license_valid && $articles_generated >= $test_limit) {
            wp_send_json_error(array('message' => 'Безкоштовний ліміт вичерпано (10 статей). Придбайте ліцензію.'));
        }

        $topic        = sanitize_text_field($_POST['main_topic']);
        $count        = min(20, max(1, intval($_POST['post_count'])));
        $cat_id       = intval($_POST['category_id']);
        $words        = intval($_POST['length']);
        $add_images   = isset($_POST['add_images']);
        $add_inline   = isset($_POST['add_inline_images']);
        $add_tags     = isset($_POST['add_tags']);
        $add_seo      = isset($_POST['add_seo']);
        $add_comments = isset($_POST['add_comments']);

        $titles = $this->get_titles($topic, $count, $api_key);
        if (is_wp_error($titles)) {
            wp_send_json_error(array('message' => $titles->get_error_message()));
        }

        $created = 0;
        $results = array();

        foreach ($titles as $i => $title) {
            if ($i > 0) sleep(15);

            $content = $this->get_article($title, $topic, $words, $api_key);
            if (is_wp_error($content)) continue;
            
            // ВИПРАВЛЕННЯ 1: Видаляємо будь-які CSS стилі з початку контенту
            $content = preg_replace('/^\s*<style[^>]*>.*?<\/style>\s*/is', '', $content);
            $content = preg_replace('/^\s*```(?:css)?\s*/i', '', $content);
            $content = preg_replace('/```\s*$/i', '', $content);
            $content = trim($content);

            $post_id = wp_insert_post(array(
                'post_title'    => $title,
                'post_content'  => $content,
                'post_status'   => 'draft',
                'post_category' => array($cat_id),
                'post_type'     => 'post',
            ));

            if (is_wp_error($post_id)) continue;

            $has_image      = false;
            $comments_count = 0;

            if ($add_images) {
                $has_image = $this->add_featured_image($post_id, $title, $topic);
            }
            if ($add_inline) {
                sleep(3);
                $this->add_inline_images($post_id, $title, $topic);
            }
            if ($add_tags) {
                sleep(3);
                $this->add_tags($post_id, $title, $api_key);
            }
            if ($add_seo) {
                sleep(3);
                $this->add_seo($post_id, $title, $topic, $api_key);
            }
            if ($add_comments) {
                sleep(5);
                $comments_count = $this->add_comments_to_post($post_id, $title, $api_key);
            }

            $plain_text  = wp_strip_all_tags($content);
            $plain_text  = preg_replace('/\s+/', ' ', $plain_text);
            $check_text  = trim(substr($plain_text, 0, 800));

            $results[] = array(
                'title'          => $title,
                'comments_count' => $comments_count,
                'image'          => $has_image,
                'quetext_url'    => 'https://quetext.com/?q=' . urlencode($check_text),
            );
            $created++;
        }

        $new_total = intval(get_option('amac_total_generated', 0)) + $created;
        update_option('amac_total_generated', $new_total);
        update_option('amac_last_generation', current_time('mysql'));

        wp_send_json_success(array(
            'message' => sprintf('Створено %d статей!', $created),
            'results' => $results,
        ));
    }

    private function get_titles($topic, $count, $api_key) {
        $prompt = "Generate {$count} unique article titles in Ukrainian language about: {$topic}. One per line. No numbering. No explanations.";
        $res    = $this->call_groq($prompt, $api_key, 500);
        if (is_wp_error($res)) return $res;
        $lines = array_filter(array_map('trim', explode("\n", $res)));
        while (count($lines) < $count) {
            $lines[] = $topic . ' — поради #' . (count($lines) + 1);
        }
        return array_slice($lines, 0, $count);
    }

    private function get_article($title, $topic, $words, $api_key) {
        $prompt = "Write an article in Ukrainian. Title: {$title}. Topic: {$topic}. Length: {$words} words. Format with HTML: h2, p, ul, li. Don't repeat title. Return only HTML code. DO NOT include any CSS styles, markdown code blocks, or backticks. Return pure HTML only.";
        $res    = $this->call_groq($prompt, $api_key, 3000);
        if (is_wp_error($res)) return $res;
        return wp_kses($res, array(
            'h2'     => array(),
            'h3'     => array(),
            'p'      => array(),
            'ul'     => array(),
            'ol'     => array(),
            'li'     => array(),
            'strong' => array(),
            'em'     => array(),
        ));
    }

    private function add_comments_to_post($post_id, $title, $api_key) {
        $prompt   = "Generate 4 short realistic user comments in Ukrainian for article \"{$title}\". Each on new line. No author names, no numbering.";
        $res      = $this->call_groq($prompt, $api_key, 400);
        if (is_wp_error($res)) return 0;

        $comments = array_filter(array_map('trim', explode("\n", $res)));
        $count    = 0;
        $names    = array('Олександр', 'Марія', 'Дмитро', 'Анна', 'Володимир', 'Оксана', 'Іван', 'Тетяна', 'Андрій', 'Наталія');

        foreach ($comments as $comment) {
            if (mb_strlen($comment) > 10) {
                wp_insert_comment(array(
                    'comment_post_ID'      => $post_id,
                    'comment_content'      => $comment,
                    'comment_approved'     => 1,
                    'comment_author'       => $names[array_rand($names)],
                    'comment_author_email' => 'user_' . rand(100, 999) . '@example.com',
                ));
                $count++;
            }
        }
        return $count;
    }

    // ВИПРАВЛЕННЯ 2: Тематичні фото без випадкових комп'ютерів/пейзажів
    private function build_image_query($title, $topic = '') {
        $raw = trim($topic . ' ' . $title);

        // Прибираємо службові символи, лапки, цифри й зайве сміття
        $raw = wp_strip_all_tags($raw);
        $raw = mb_strtolower($raw);
        $raw = preg_replace('/[^\p{L}\p{N}\s\-]/u', ' ', $raw);
        $raw = preg_replace('/\s+/u', ' ', $raw);
        $raw = trim($raw);

        // Словник перекладу найчастіших українських тем для кращого пошуку Unsplash/Pexels.
        // Unsplash значно краще шукає англійською, тому "білизна" треба перетворити на "lingerie".
        $map = array(
            'білизна'              => 'lingerie',
            'нижня білизна'        => 'lingerie',
            'жіноча білизна'       => 'women lingerie',
            'бюстгальтер'          => 'bra lingerie',
            'бюстгальтери'         => 'bra lingerie',
            'трусики'              => 'panties lingerie',
            'піжама'               => 'women pajamas',
            'піжами'               => 'women pajamas',
            'купальник'            => 'swimwear',
            'купальники'           => 'swimwear',
            'одяг'                 => 'fashion clothing',
            'мода'                 => 'fashion',
            'жіночий одяг'         => 'women fashion',
            'сукня'                => 'dress fashion',
            'сукні'                => 'dress fashion',
            'косметика'            => 'cosmetics beauty',
            'догляд'               => 'beauty care',
            'краса'                => 'beauty',
            'інтернет'             => 'internet',
            'seo'                  => 'seo marketing',
            'маркетинг'            => 'marketing',
            'бізнес'               => 'business',
            'заробіток'            => 'online business',
            'wordpress'            => 'wordpress website',
            'сайт'                 => 'website',
            'веб дизайн'           => 'web design',
            'веб-дизайн'           => 'web design',
        );

        foreach ($map as $ua => $en) {
            if (mb_strpos($raw, $ua) !== false) {
                return $en;
            }
        }

        // Якщо словник не спрацював — беремо перші змістовні слова.
        $stop = array(
            'як','що','це','для','про','при','від','або','та','і','в','у','на','з','із','до','по','чи',
            'кращі','поради','огляд','повний','чому','коли','який','яка','яке','які','топ'
        );

        $words = array();
        foreach (explode(' ', $raw) as $word) {
            $word = trim($word);
            if (mb_strlen($word) < 3 || in_array($word, $stop, true)) continue;
            $words[] = $word;
        }

        $query = implode(' ', array_slice(array_unique($words), 0, 4));
        return !empty($query) ? $query : 'article topic';
    }

    private function get_unsplash_image_url($keyword, $width = 1200, $height = 800) {
        $unsplash_key = get_option('amac_unsplash_key', '');
        $keyword = trim($keyword);

        if (empty($keyword) || $keyword === 'article topic') {
            return false;
        }

        if (!empty($unsplash_key)) {
            $api_url = add_query_arg(array(
                'query'       => $keyword,
                'orientation' => 'landscape',
                'content_filter' => 'high',
                'client_id'   => $unsplash_key,
            ), 'https://api.unsplash.com/photos/random');

            $response = wp_remote_get($api_url, array('timeout' => 15));

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $data = json_decode(wp_remote_retrieve_body($response), true);

                if (!empty($data['urls']['regular'])) {
                    return esc_url_raw($data['urls']['regular']);
                }
            }
        }

        // ВАЖЛИВО: більше НЕ повертаємо technology/business/computer/picsum.
        // Якщо немає тематичного фото — краще не додати фото взагалі, ніж зіпсувати статтю випадковою картинкою.
        return false;
    }

    private function sideload_image_to_media($post_id, $image_url, $title, $set_featured = false) {
        if (empty($image_url)) return false;

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url($image_url, 20);
        if (is_wp_error($tmp)) {
            return false;
        }

        $file = array(
            'name'     => sanitize_title($title) . '-' . time() . '-' . mt_rand(100, 999) . '.jpg',
            'tmp_name' => $tmp,
        );

        $aid = media_handle_sideload($file, $post_id);

        if (is_wp_error($aid)) {
            @unlink($tmp);
            return false;
        }

        update_post_meta($aid, '_wp_attachment_image_alt', sanitize_text_field($title));

        if ($set_featured) {
            set_post_thumbnail($post_id, $aid);
        }

        return $aid;
    }

    private function add_featured_image($post_id, $title, $topic = '') {
        $keyword = $this->build_image_query($title, $topic);
        $image_url = $this->get_unsplash_image_url($keyword, 1200, 800);

        if (!$image_url) {
            return false;
        }

        $aid = $this->sideload_image_to_media($post_id, $image_url, $title, true);
        return !empty($aid);
    }

    private function add_inline_images($post_id, $title, $topic = '') {
        $content    = get_post_field('post_content', $post_id);
        $paragraphs = explode('</p>', $content);
        if (count($paragraphs) < 3) return;

        $img_count = min(3, max(2, rand(2, 3)));
        $positions = array();

        for ($i = 1; $i <= $img_count; $i++) {
            $pos = (int) round((count($paragraphs) / ($img_count + 1)) * $i);
            if ($pos > 0 && $pos < count($paragraphs)) {
                $positions[] = $pos;
            }
        }

        $positions = array_values(array_unique(array_reverse($positions)));
        $keyword = $this->build_image_query($title, $topic);

        foreach ($positions as $pos) {
            $img_url = $this->get_unsplash_image_url($keyword, 800, 500);
            if (!$img_url) continue;

            // Inline фото теж завантажуємо в медіабібліотеку, а не вставляємо hotlink.
            $aid = $this->sideload_image_to_media($post_id, $img_url, $title, false);
            if (!$aid) continue;

            $local_url = wp_get_attachment_image_url($aid, 'large');
            if (!$local_url) continue;

            $figure = '</p><figure class="amac-inline-image" style="margin:20px 0;">'
                . '<img src="' . esc_url($local_url) . '" alt="' . esc_attr($title) . '" style="width:100%;height:auto;border-radius:8px;" loading="lazy">'
                . '<figcaption style="text-align:center;color:#666;font-size:14px;">' . esc_html($title) . '</figcaption>'
                . '</figure>';

            $paragraphs[$pos - 1] .= $figure;
        }

        wp_update_post(array(
            'ID'           => $post_id,
            'post_content' => implode('', $paragraphs),
        ));
    }

    private function add_tags($post_id, $title, $api_key) {
        $res = $this->call_groq("Extract 5 Ukrainian keywords for article \"{$title}\". Only words separated by commas. No explanations.", $api_key, 80);
        if (!is_wp_error($res)) {
            $tags = array_map('trim', explode(',', $res));
            wp_set_post_tags($post_id, $tags);
        }
    }

    private function add_seo($post_id, $title, $topic, $api_key) {
        $res = $this->call_groq("For Ukrainian article \"{$title}\" about {$topic} write exactly: KEYWORDS: word1, word2, word3, word4, word5 | DESCRIPTION: (max 155 chars description)", $api_key, 200);
        if (!is_wp_error($res)) {
            preg_match('/KEYWORDS:\s*(.+?)(?=\||$)/i', $res, $k);
            preg_match('/DESCRIPTION:\s*(.+?)$/i', $res, $d);
            if (!empty($k[1])) update_post_meta($post_id, '_amac_keywords', trim($k[1]));
            if (!empty($d[1])) update_post_meta($post_id, '_amac_description', trim($d[1]));
        }
    }

    private function call_groq($prompt, $api_key, $max_tokens = 1500) {
        $response = wp_remote_post('https://api.groq.com/openai/v1/chat/completions', array(
            'timeout' => 90,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body' => json_encode(array(
                'model'       => 'llama-3.1-8b-instant',
                'messages'    => array(array('role' => 'user', 'content' => $prompt)),
                'temperature' => 0.8,
                'max_tokens'  => $max_tokens,
            )),
        ));

        if (is_wp_error($response)) return $response;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['error'])) return new WP_Error('api', $body['error']['message']);

        return trim($body['choices'][0]['message']['content']);
    }

    public function check_github_updates($transient) {
        if (empty($transient->checked)) return $transient;

        $remote = $this->get_github_info();
        if ($remote && version_compare(AMAC_VERSION, $remote->version, '<')) {
            $plugin_file = plugin_basename(__FILE__);
            $transient->response[$plugin_file] = (object) array(
                'slug'        => 'ai-mass-article-creator',
                'new_version' => $remote->version,
                'package'     => $remote->download_url,
                'tested'      => '6.5',
            );
        }
        return $transient;
    }

    public function github_plugin_info($false, $action, $args) {
        if ($action !== 'plugin_information') return $false;
        if ($args->slug !== 'ai-mass-article-creator') return $false;

        $remote = $this->get_github_info();
        if ($remote) {
            return (object) array(
                'name'          => 'AI Mass Article Creator',
                'slug'          => 'ai-mass-article-creator',
                'version'       => $remote->version,
                'download_link' => $remote->download_url,
                'requires'      => '5.8',
                'tested'        => '6.5',
                'sections'      => array(
                    'description' => 'Generate 1-20 unique SEO articles with AI, automatic images, and 3-5 REAL user comments per article!',
                ),
            );
        }
        return $false;
    }

    private function get_github_info() {
        $response = wp_remote_get('https://api.github.com/repos/portallcomua/ai-mass-article-creator/releases/latest');
        if (is_wp_error($response)) return null;

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($data['tag_name'], $data['zipball_url'])) {
            return (object) array(
                'version'      => ltrim($data['tag_name'], 'v'),
                'download_url' => $data['zipball_url'],
            );
        }
        return null;
    }
}

new AI_Mass_Article_Creator();