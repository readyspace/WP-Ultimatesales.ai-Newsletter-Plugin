<?php
/**
 * Plugin Name: ReadySpace Newsletter for UltimateSales.AI
 * Description: Direct WordPress-to-UltimateSales.AI article newsletters, with consent checks and a durable send ledger.
 * Version: 0.3.0-alpha.3
 * Plugin URI: https://github.com/readyspace/WP-Ultimatesales.ai-Newsletter-Plugin
 * Requires at least: 6.9
 * Requires PHP: 8.1
 * Author: ReadySpace
 * Author URI: https://readyspace.com
 * Text Domain: readyspace-newsletter-for-ultimatesales-ai
 */
namespace CleverSpeed\Newsletter;
defined('ABSPATH') || exit;
require_once __DIR__ . '/includes/policy.php';
require_once __DIR__ . '/includes/credential.php';
require_once __DIR__ . '/includes/client.php';

final class Plugin {
    public const HOOK = 'cleverspeed_newsletter_tick';
    public static function table(): string { global $wpdb; return $wpdb->prefix . 'cs_newsletter_jobs'; }
    public static function settings(): array {
        return array_merge(['mode' => 'off', 'user_id' => '', 'cutover' => 0, 'verified' => false], get_option('cs_newsletter_settings', []));
    }
    public static function verified(): bool {
        $s = self::settings();
        return Config::ready() && $s['verified'] === true && !empty($s['user_id']) &&
            hash_equals(Config::fingerprint(), (string)($s['verified_config'] ?? ''));
    }
    public static function activate(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        dbDelta("CREATE TABLE $table (
            post_id bigint(20) unsigned NOT NULL,
            state varchar(32) NOT NULL,
            due_at bigint(20) unsigned NOT NULL DEFAULT 0,
            campaign_id varchar(100) NOT NULL DEFAULT '',
            source_id varchar(100) NOT NULL DEFAULT '',
            content_hash varchar(64) NOT NULL DEFAULT '',
            recipient_count int unsigned NOT NULL DEFAULT 0,
            attempts int unsigned NOT NULL DEFAULT 0,
            note text NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (post_id),
            KEY due_state (state,due_at)
        ) {$wpdb->get_charset_collate()};");
        $existingTable = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        if (!is_string($existingTable) || strcasecmp($existingTable, $table) !== 0) {
            throw new \RuntimeException('Cannot create newsletter ledger; activation stopped.');
        }
        if (!get_option('cs_newsletter_installed')) {
            // Seed every already-published article. Activation must not email the archive.
            if ($wpdb->query($wpdb->prepare("INSERT IGNORE INTO %i (post_id,state,note,updated_at)
                SELECT ID,'excluded','Published before integration installation',UTC_TIMESTAMP()
                FROM %i WHERE post_type='post' AND post_status='publish'", $table, $wpdb->posts)) === false) {
                throw new \RuntimeException('Cannot exclude the existing article archive; activation stopped.');
            }
            add_option('cs_newsletter_installed', time(), '', false);
            add_option('cs_newsletter_settings', ['mode'=>'off','user_id'=>'','cutover'=>time(),'verified'=>false], '', false);
        }
        if (!wp_next_scheduled(self::HOOK)) wp_schedule_event(time() + 60, 'cs_newsletter_minute', self::HOOK);
    }
    public static function deactivate(): void { wp_clear_scheduled_hook(self::HOOK); }
    public static function record(int $id): ?array {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE post_id=%d', self::table(), $id), ARRAY_A) ?: null;
    }
    public static function set(int $id, array $data): void {
        global $wpdb;
        $data['updated_at'] = gmdate('Y-m-d H:i:s');
        if ($wpdb->update(self::table(), $data, ['post_id'=>$id]) !== 1) throw new \RuntimeException('Cannot save newsletter ledger; stopped.');
    }
    public static function transition(string $new, string $old, \WP_Post $post): void {
        if ($new !== 'publish' || $old === 'publish' || $post->post_type !== 'post') return;
        global $wpdb;
        $s = self::settings();
        $state = $s['mode'] === 'off' ? 'excluded' : 'queued';
        if ($post->post_password !== '' || get_post_meta($post->ID, '_cs_newsletter_skip', true)) $state = 'excluded';
        $wpdb->query($wpdb->prepare('INSERT IGNORE INTO %i (post_id,state,due_at,note,updated_at) VALUES (%d,%s,%d,%s,%s)',
            self::table(),$post->ID,$state,time()+300,$state === 'excluded' ? 'Not eligible at first publication' : 'Waiting five minutes for public publication',gmdate('Y-m-d H:i:s')));
    }
    public static function article(int $id): array {
        $post = get_post($id);
        if (!$post || $post->post_type !== 'post' || $post->post_status !== 'publish' || $post->post_password !== '' ||
            get_post_meta($id, '_cs_newsletter_skip', true)) throw new \RuntimeException('Article is not public or newsletter is excluded.');
        $excerpt = Policy::excerpt($post->post_excerpt);
        $title = Policy::plain($post->post_title);
        $url = Policy::publicUrl(get_permalink($post));
        $response = wp_safe_remote_get($url, ['timeout'=>20,'redirection'=>0,'limit_response_size'=>2000000]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) throw new \RuntimeException('Public article is not available yet; email held.');
        if (stripos((string)wp_remote_retrieve_header($response, 'x-robots-tag'), 'noindex') !== false) throw new \RuntimeException('Public article has a noindex header.');
        Policy::assertPublicArticle(wp_remote_retrieve_body($response), $url, $title);
        return compact('title','excerpt','url');
    }
    public static function tick(): void {
        global $wpdb;
        update_option('cs_newsletter_last_tick', time(), false);
        $s = self::settings();
        if ($s['mode'] === 'off' || !self::verified() || !Client::credentialReady()) return;
        $ids = $wpdb->get_col($wpdb->prepare("SELECT post_id FROM %i WHERE state='queued' AND due_at<=%d ORDER BY due_at LIMIT 3",self::table(),time()));
        foreach ($ids as $id) self::process((int)$id);
    }
    public static function process(int $id): void {
        global $wpdb;
        $lock = 'cs_newsletter_' . md5(DB_NAME . $wpdb->prefix . $id);
        if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)', $lock)) !== 1) return;
        try {
            $s = self::settings();
            $job = self::record($id);
            if (!$job || $job['state'] !== 'queued' || $job['campaign_id'] || $job['due_at'] > time() ||
                $s['mode'] === 'off' || !self::verified()) return;
            $a = self::article($id);
            $client = new Client();
            $recipients = $client->recipients();
            if (!$recipients) {
                self::set($id, ['state'=>'skipped','note'=>'No confirmed email-eligible subscribers at publication']);
                return;
            }
            $html = Policy::content($a['title'],$a['excerpt'],$a['url']);
            $name = Config::get('brand') . ' WordPress post ' . $id . ' — ' . $a['title'];
            $hash = hash('sha256',$html);
            // Persist BEFORE each mutating request. A crash never permits an automatic repeat.
            self::set($id,['state'=>'creating','content_hash'=>$hash,'recipient_count'=>count($recipients),
                'attempts'=>(int)$job['attempts']+1,'note'=>'Creating campaign; interrupted requests require reconciliation']);
            $created = $client->create($name,$html,$s['user_id']);
            if (empty($created['id']) || !preg_match('/^[a-zA-Z0-9_-]+$/',$created['id'])) throw new \RuntimeException('Campaign creation outcome requires review.');
            $campaign = $created['id'];
            self::set($id,['state'=>'created','campaign_id'=>$campaign,'note'=>'Remote draft created; verifying saved content']);
            $remote = $client->campaign($campaign);
            $client->verifyDraft($remote,$name,$a['title'],$a['excerpt'],$a['url']);
            if ($s['mode'] !== 'live') {
                self::set($id,['state'=>'draft_ready','note'=>'Draft-only mode: reviewed campaign created; no send requested']);
                return;
            }
            // Re-read mode, content and subscribers immediately before send.
            if (self::settings()['mode'] !== 'live' || !self::verified()) throw new \RuntimeException('Sending or verification changed; draft retained.');
            $latest = self::article($id);
            if (hash('sha256',Policy::content($latest['title'],$latest['excerpt'],$latest['url'])) !== $hash) throw new \RuntimeException('Article changed during preparation; draft retained for review.');
            $recipients = $client->recipients();
            if (!$recipients) throw new \RuntimeException('No eligible subscribers remain; draft retained.');
            self::set($id,['state'=>'sending','recipient_count'=>count($recipients),'note'=>'Send requested; never retry blindly']);
            $sent = $client->send($campaign,$a['title'],$recipients,$s['user_id']);
            if (($sent['campaignId'] ?? '') !== $campaign) throw new \RuntimeException('Send acknowledgement does not match; reconcile before any further action.');
            self::set($id,['state'=>'submitted','source_id'=>$sent['sourceId'] ?? '', 'note'=>'UltimateSales.AI accepted the campaign. Delivery is tracked in UltimateSales.AI; acceptance is not delivery.']);
        } catch (\Throwable $e) {
            $job = self::record($id);
            $state = in_array($job['state'] ?? '',['creating','sending'],true) ? 'uncertain' : 'held';
            self::set($id,['state'=>$state,'note'=>$e->getMessage()]);
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));
        }
    }

    public static function menu(): void { add_management_page('ReadySpace Newsletter Bridge','ReadySpace Newsletter','manage_options','cs-newsletter',[self::class,'page']); }
    public static function page(): void {
        if (!current_user_can('manage_options')) return;
        $s = self::settings();
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM %i WHERE state<>'excluded' ORDER BY updated_at DESC LIMIT 30",self::table()),ARRAY_A);
        echo '<div class="wrap"><h1>ReadySpace Newsletter for UltimateSales.AI</h1><p>By ReadySpace · Version 0.3.0-alpha.3 · Developer preview</p><p>WordPress → UltimateSales.AI. No Next.js email logic and no WordPress SMTP.</p>';
        echo '<p>Private per-site configuration: ' . (Config::ready() ? 'valid' : 'missing or invalid; see the installation guide') . '.</p>';
        echo '<p>Mode: <strong>' . esc_html($s['mode']) . '</strong>. Private credential: ' . (Client::credentialReady() ? 'saved securely' : 'not ready') .
            '. Connection verification: ' . (self::verified() ? 'passed' : 'pending') . '.</p>';
        echo '<p>Last queue check (UTC): ' . esc_html(gmdate('Y-m-d H:i:s',(int)get_option('cs_newsletter_last_tick',0))) . '.</p>';
        echo '<p>Each new public post needs an Excerpt (10–150 words; aim for 60–90). The queue waits five minutes. Old articles, edits and republishes are not automatically emailed. Missing excerpts and unavailable public pages are held.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('cs_newsletter_settings');
        echo '<input type="hidden" name="action" value="cs_newsletter_settings"><label>Mode <select name="mode">';
        foreach (['off'=>'Off','draft'=>'Create drafts only','live'=>'Automatic sending'] as $v=>$label) echo '<option value="' . esc_attr($v) . '" ' . selected($s['mode'],$v,false) . '>' . esc_html($label) . '</option>';
        echo '</select></label> ';
        submit_button('Save mode','primary','submit',false);
        echo '</form><h2>Integration credential</h2><p>Only administrators can replace this credential. The saved value is never displayed. Saving a replacement switches sending off and resets connection verification. It does not send a test email.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('rs_newsletter_credential');
        echo '<input type="hidden" name="action" value="rs_newsletter_credential"><label for="rs-token">UltimateSales.AI private integration token</label><br><input id="rs-token" type="password" name="token" value="" autocomplete="new-password" spellcheck="false" class="large-text" maxlength="4096" required><p>Enter it here directly. Do not send it by chat or email. Saving requires HTTPS.</p>';
        submit_button('Save credential and keep sending off','secondary');
        echo '</form><p>The credential is encrypted in the WordPress database using keys from wp-config.php. This protects a database-only copy, not a compromised server. Changing those keys requires re-entering the credential. Stop queue workers before rotating credentials during live operation.</p>';
        $preview = Config::ready() ? Policy::content('An example article', 'This is an illustrative newsletter excerpt. Describe the useful ideas in your article and give readers a reason to visit your website. Keep this text clear and concise. The plugin uses the WordPress Excerpt field rather than sending the full article. Check the title, public link and consent settings before enabling automatic delivery.', Config::get('public_origin') . '/example-article/') : '<p>Complete private configuration to preview your email design.</p>';
        $preview = preg_replace('/ href="[^"]*"/', '', $preview);
        echo '<details><summary style="cursor:pointer;font-size:18px;font-weight:600;margin:20px 0">Newsletter design preview</summary><p>Illustrative content only. No email is sent. Preview links are disabled; mailbox rendering still needs testing.</p><iframe title="Newsletter design preview" sandbox="" style="width:100%;max-width:660px;height:950px;border:1px solid #ccd0d4" srcdoc="' . esc_attr($preview) . '"></iframe></details>';
        echo '<h2>Recent newsletter jobs</h2><table class="widefat"><thead><tr><th>Post</th><th>Status</th><th>Campaign</th><th>Recipients</th><th>Note</th><th>Action</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr><td><a href="' . esc_url(get_edit_post_link($row['post_id'])) . '">' . (int)$row['post_id'] . '</a></td><td>' . esc_html($row['state']) . '</td><td>' . esc_html($row['campaign_id']) . '</td><td>' . (int)$row['recipient_count'] . '</td><td>' . esc_html($row['note']) . '</td><td>';
            if ($row['state'] === 'held' && !$row['campaign_id']) {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                wp_nonce_field('cs_newsletter_retry_' . $row['post_id']);
                echo '<input type="hidden" name="action" value="cs_newsletter_retry"><input type="hidden" name="post_id" value="' . (int)$row['post_id'] . '"><button class="button">Recheck after correction</button></form>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table><p>Creating, sending or uncertain jobs require reconciliation in UltimateSales.AI. There is deliberately no blind-resend button. Disabling this plugin stops new queue work; it cannot recall an email already accepted by UltimateSales.AI.</p></div>';
    }
    public static function saveSettings(): void {
        if (!current_user_can('manage_options')) wp_die('Forbidden', '', ['response'=>403]);
        check_admin_referer('cs_newsletter_settings');
        if (sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') wp_die('Use POST to save settings.');
        if (isset($_POST['mode']) && !is_string($_POST['mode'])) wp_die('Invalid mode');
        $s = self::settings();
        $mode = sanitize_key(wp_unslash($_POST['mode'] ?? 'off'));
        if (!in_array($mode,['off','draft','live'],true)) wp_die('Invalid mode');
        if ($mode !== 'off' && (!self::verified() || !Client::credentialReady())) wp_die('Complete configuration and connection verification first.');
        $s['mode']=$mode;
        update_option('cs_newsletter_settings',$s,false);
        wp_safe_redirect(admin_url('tools.php?page=cs-newsletter')); exit;
    }
    public static function saveCredential(): void {
        if (!current_user_can('manage_options')) wp_die('Forbidden', '', ['response'=>403]);
        check_admin_referer('rs_newsletter_credential');
        if (sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST' || !is_ssl()) wp_die('Use HTTPS to save credentials.');
        // Fail closed before replacement. Existing accepted campaigns cannot be recalled.
        $s = self::settings(); $s['mode']='off'; $s['verified']=false; $s['user_id']='';
        update_option('cs_newsletter_settings',$s,false);
        if (self::settings()['mode'] !== 'off' || self::settings()['verified']) wp_die('Could not disable sending. Credential was not changed.');
        try {
            if (!isset($_POST['token']) || !is_string($_POST['token'])) throw new \RuntimeException('Enter a valid private integration token.');
            // Credential::save validates the complete ASCII token; generic text sanitization would silently alter it.
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Strict format and length validation happens in Credential::save before encryption.
            Credential::save(wp_unslash($_POST['token']));
        } catch (\Throwable $e) {
            unset($_POST['token']);
            wp_die('Credential was not saved. Check the token format, OpenSSL and WordPress security keys. Sending remains off.');
        }
        unset($_POST['token']);
        wp_safe_redirect(admin_url('tools.php?page=cs-newsletter')); exit;
    }
    public static function retry(): void {
        if (isset($_POST['post_id']) && !is_scalar($_POST['post_id'])) wp_die('Invalid post.');
        $id = absint(wp_unslash($_POST['post_id'] ?? 0));
        if (!current_user_can('manage_options')) wp_die('Forbidden', '', ['response'=>403]);
        check_admin_referer('cs_newsletter_retry_' . $id);
        if (sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') wp_die('Use POST to recheck a post.');
        global $wpdb;
        $wpdb->query($wpdb->prepare("UPDATE %i SET state='queued',due_at=%d,note='Editor requested a safe pre-creation recheck' WHERE post_id=%d AND state='held' AND campaign_id=''",self::table(),time()+300,$id));
        wp_safe_redirect(admin_url('tools.php?page=cs-newsletter')); exit;
    }
    public static function box(\WP_Post $post): void {
        wp_nonce_field('cs_newsletter_post','cs_newsletter_nonce');
        $job = self::record($post->ID);
        echo '<p>First publication can send the Excerpt through UltimateSales.AI after five minutes. Updates do not resend.</p>';
        echo '<label><input type="checkbox" name="cs_newsletter_skip" value="1" ' . checked(get_post_meta($post->ID,'_cs_newsletter_skip',true),'1',false) . '> Exclude this article from the newsletter</label>';
        echo '<p>Newsletter status: ' . esc_html($job['state'] ?? 'Not published') . '</p><p>Add the teaser in WordPress’s Excerpt field. No full-content fallback.</p>';
    }
    public static function savePost(int $id): void {
        if (wp_is_post_revision($id) || wp_is_post_autosave($id) || !current_user_can('edit_post',$id) ||
            !isset($_POST['cs_newsletter_nonce']) || !is_string($_POST['cs_newsletter_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cs_newsletter_nonce'])),'cs_newsletter_post')) return;
        update_post_meta($id,'_cs_newsletter_skip',isset($_POST['cs_newsletter_skip']) ? '1' : '');
    }
}
register_activation_hook(__FILE__,[Plugin::class,'activate']);
register_deactivation_hook(__FILE__,[Plugin::class,'deactivate']);
add_filter('cron_schedules',static function($s){$s['cs_newsletter_minute']=['interval'=>60,'display'=>'ReadySpace newsletter queue']; return $s;});
add_action('transition_post_status',[Plugin::class,'transition'],10,3);
add_action(Plugin::HOOK,[Plugin::class,'tick']);
add_action('admin_menu',[Plugin::class,'menu']);
add_action('admin_post_cs_newsletter_settings',[Plugin::class,'saveSettings']);
add_action('admin_post_rs_newsletter_credential',[Plugin::class,'saveCredential']);
add_action('admin_post_cs_newsletter_retry',[Plugin::class,'retry']);
add_action('add_meta_boxes',static function(){add_meta_box('cs-newsletter','ReadySpace newsletter',[Plugin::class,'box'],'post','side');});
add_action('save_post_post',[Plugin::class,'savePost']);
