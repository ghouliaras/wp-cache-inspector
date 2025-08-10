<?php
if (!defined('ABSPATH')) exit;

class WPCI_Admin {

    public static function render() {
        if (!current_user_can('manage_options')) return;

        // Handle actions
        if (isset($_GET['wpci_dev']) && check_admin_referer('wpci_dev_toggle')) {
            if ($_GET['wpci_dev'] === 'on') {
                setcookie(WPCI_DEV_COOKIE, '1', time() + 15*60, COOKIEPATH ?: '/', COOKIE_DOMAIN ?: '', is_ssl(), true);
                $_COOKIE[WPCI_DEV_COOKIE] = '1';
                add_settings_error('wpci', 'devon', 'Dev no-cache mode enabled for this browser (15 minutes).', 'updated');
            } elseif ($_GET['wpci_dev'] === 'off') {
                setcookie(WPCI_DEV_COOKIE, '', time() - 3600, COOKIEPATH ?: '/', COOKIE_DOMAIN ?: '');
                unset($_COOKIE[WPCI_DEV_COOKIE]);
                add_settings_error('wpci', 'devoff', 'Dev no-cache mode disabled for this browser.', 'updated');
            }
        }

        if (isset($_POST['wpci_purge']) && check_admin_referer('wpci_purge')) {
            wpci_purge_known_caches();
            add_settings_error('wpci', 'purged', 'Attempted to purge known caches.', 'updated');
        }

        settings_errors('wpci');

        $tester = new WPCI_Tester();

        $env = self::environment_info();
        $plugins = self::cache_plugins_detect();

        // Run probes
        $suite = $tester->run_suite();

        // Simple verdicts
        $verdicts = self::build_verdicts($suite);

        ?>
        <div class="wrap">
            <h1>WP Cache Inspector</h1>

            <p><strong>Tip:</strong> Enable <em>Dev no-cache mode</em> while editing to bypass most front-end caching in <em>this</em> browser.</p>

            <p>
                <?php if (isset($_COOKIE[WPCI_DEV_COOKIE]) && $_COOKIE[WPCI_DEV_COOKIE] === '1'): ?>
                    <span style="padding:4px 8px;background:#007cba;color:#fff;border-radius:3px;">Dev no-cache: ON</span>
                    <a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg('wpci_dev','off'), 'wpci_dev_toggle')); ?>">Turn off</a>
                <?php else: ?>
                    <span style="padding:4px 8px;background:#777;color:#fff;border-radius:3px;">Dev no-cache: OFF</span>
                    <a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(add_query_arg('wpci_dev','on'), 'wpci_dev_toggle')); ?>">Turn on (15 min)</a>
                <?php endif; ?>
            </p>

            <form method="post" style="margin:1em 0;">
                <?php wp_nonce_field('wpci_purge'); ?>
                <button class="button">Purge known caches</button>
            </form>

            <h2>Environment</h2>
            <table class="widefat striped">
                <tbody>
                    <tr><th>Server Software</th><td><?php echo esc_html($env['server']); ?></td></tr>
                    <tr><th>LiteSpeed Server</th><td><?php echo $env['is_litespeed'] ? 'Yes' : 'No'; ?></td></tr>
                    <tr><th>PHP</th><td><?php echo esc_html(PHP_VERSION); ?></td></tr>
                    <tr><th>OPcache</th><td><?php echo $env['opcache']; ?></td></tr>
                    <tr><th>APCu</th><td><?php echo $env['apcu']; ?></td></tr>
                    <tr><th>External Object Cache</th><td><?php echo $env['ext_obj']; ?></td></tr>
                </tbody>
            </table>

            <h2>Detected caching plugins / layers</h2>
            <table class="widefat striped">
                <thead><tr><th>Layer/Plugin</th><th>Detected</th></tr></thead>
                <tbody>
                    <?php foreach ($plugins as $name => $on): ?>
                        <tr><td><?php echo esc_html($name); ?></td><td><?php echo $on ? 'Yes' : 'No'; ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2>Page cache probe</h2>
            <p>We requested your homepage multiple times and compared headers.</p>
            <?php self::render_probe_table($suite); ?>

            <h2>Verdicts</h2>
            <ul>
                <?php foreach ($verdicts as $v): ?>
                    <li><?php echo esc_html($v); ?></li>
                <?php endforeach; ?>
            </ul>

            <p><em>Note:</em> If the REST <code>/wp-json/wpci/v1/ping</code> endpoint shows any cache HIT or non-zero <code>Age</code>, your server/CDN is caching things it shouldn’t. Enable Dev no-cache and check your CDN/LiteSpeed settings.</p>
        </div>
        <?php
    }

    private static function environment_info() {
        $server = $_SERVER['SERVER_SOFTWARE'] ?? '';
        $is_ls  = wpci_is_litespeed_server();

        // OPcache
        $op = 'Not available';
        if (function_exists('opcache_get_status')) {
            $st = @opcache_get_status(false);
            if (is_array($st) && !empty($st['opcache_enabled'])) {
                $mem = isset($st['memory_usage']['used_memory'], $st['memory_usage']['free_memory']) 
                    ? sprintf(' (mem: used %s / free %s)',
                        size_format((float)$st['memory_usage']['used_memory']),
                        size_format((float)$st['memory_usage']['free_memory']))
                    : '';
                $op = 'Enabled' . $mem;
            } else {
                $op = 'Disabled';
            }
        }

        // APCu
        $apcu = function_exists('apcu_enabled') && apcu_enabled() ? 'Enabled' : (extension_loaded('apcu') ? 'Loaded (may be CLI-only)' : 'Not available');

        // External object cache
        $ext = wp_using_ext_object_cache() ? 'Yes (drop-in active)' : 'No';
        if ($ext === 'Yes (drop-in active)') {
            if (class_exists('Redis') || defined('WP_REDIS_DISABLED')) $ext .= ' - likely Redis';
            if (class_exists('Memcached')) $ext .= ' - Memcached loaded';
        }

        return [
            'server' => $server,
            'is_litespeed' => $is_ls,
            'opcache' => $op,
            'apcu' => $apcu,
            'ext_obj' => $ext,
        ];
    }

    private static function cache_plugins_detect() {
        return [
            'LiteSpeed Cache (plugin or server)' => class_exists('LiteSpeed_Cache') || wpci_is_litespeed_server(),
            'WP Rocket' => function_exists('get_rocket_option'),
            'W3 Total Cache' => defined('W3TC'),
            'WP Super Cache' => function_exists('wp_cache_clean_cache'),
            'Cloudflare (headers)' => isset($_SERVER['HTTP_CF_RAY']) || (isset($_SERVER['SERVER']) && stripos((string)$_SERVER['SERVER'], 'cloudflare') !== false),
            'Varnish/Nginx FastCGI (headers)' => false, // Determined in probes
            'Redis Object Cache (likely)' => class_exists('Redis') || defined('WP_REDIS_DISABLED'),
        ];
    }

    private static function render_probe_table($suite) {
        if (is_wp_error($suite)) {
            echo '<p style="color:#b00;">Probe failed: ' . esc_html($suite->get_error_message()) . '</p>';
            return;
        }
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Request</th>
                    <th>Status</th>
                    <th>Elapsed (ms)</th>
                    <th>Key headers</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($suite['requests'] as $row): ?>
                    <tr>
                        <td><?php echo esc_html($row['label']); ?></td>
                        <td><?php echo esc_html($row['status']); ?></td>
                        <td><?php echo esc_html((int)round($row['elapsed']*1000)); ?></td>
                        <td>
                            <?php
                            $h = $row['headers'];
                            $keys = ['x-litespeed-cache','x-litespeed-cache-control','x-cache','x-cache-status','x-proxy-cache','x-fastcgi-cache','x-varnish','age','cache-control','cf-cache-status','server'];
                            $out = [];
                            foreach ($keys as $k) {
                                if (isset($h[$k])) $out[] = $k . ': ' . (is_array($h[$k]) ? implode(', ', $h[$k]) : $h[$k]);
                            }
                            echo esc_html($out ? implode(' | ', $out) : '—');
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private static function build_verdicts($suite) {
        $v = [];
        if (is_wp_error($suite)) return ['Could not run probes. Loopback HTTP may be blocked.'];

        $r1 = $suite['requests'][0] ?? null; // Home #1
        $r2 = $suite['requests'][1] ?? null; // Home #2
        $rest = $suite['requests'][2] ?? null; // REST ping
        $nocache = $suite['requests'][3] ?? null; // Home with Request no-cache

        // Detect engines by headers
        $h1 = $r2['headers'] ?? [];
        $engine = [];
        if (!empty($h1['x-litespeed-cache'])) $engine[] = 'LiteSpeed';
        if (!empty($h1['cf-cache-status'])) $engine[] = 'Cloudflare';
        if (!empty($h1['x-varnish'])) $engine[] = 'Varnish';
        if (!empty($h1['x-fastcgi-cache'])) $engine[] = 'Nginx FastCGI';
        if (!empty($h1['x-cache']) || !empty($h1['x-cache-status']) || !empty($h1['x-proxy-cache'])) $engine[] = 'Reverse-proxy cache';

        if ($engine) $v[] = 'Server/CDN cache detected: ' . implode(', ', array_unique($engine)) . '.';
        else $v[] = 'No obvious server/CDN cache detected from headers.';

        // Page cache active?
        $hit = (isset($h1['x-litespeed-cache']) && stripos($h1['x-litespeed-cache'],'hit')!==false)
            || (isset($h1['cf-cache-status']) && stripos($h1['cf-cache-status'],'HIT')!==false)
            || (isset($h1['x-cache']) && stripos($h1['x-cache'],'hit')!==false)
            || (!empty($h1['age']) && (int)$h1['age'] > 0);

        if ($hit) $v[] = 'Page cache: likely ACTIVE (saw HIT/AGE>0 on second request).';
        else $v[] = 'Page cache: no HIT observed (could be MISS/BYPASS due to login or cache disabled).';

        // REST should never be cached
        $rh = $rest['headers'] ?? [];
        $rest_cached = (!empty($rh['age']) && (int)$rh['age'] > 0)
            || (isset($rh['cf-cache-status']) && stripos($rh['cf-cache-status'],'HIT')!==false)
            || (isset($rh['x-litespeed-cache']) && stripos($rh['x-litespeed-cache'],'hit')!==false);
        if ($rest_cached) $v[] = 'WARNING: REST endpoint appears cached. Server/CDN may be caching dynamic endpoints incorrectly.';
        else $v[] = 'REST endpoint not cached (good).';

        // Request header no-cache respected?
        $nh = $nocache['headers'] ?? [];
        $nocache_respected = (isset($nh['cf-cache-status']) && stripos($nh['cf-cache-status'], 'BYPASS') !== false)
                          || (isset($nh['x-litespeed-cache']) && stripos($nh['x-litespeed-cache'],'miss')!==false)
                          || (isset($nh['cache-control']) && stripos($nh['cache-control'],'no-cache')!==false)
                          || (empty($nh['age']) || (int)$nh['age']===0);
        $v[] = $nocache_respected ? 'Request "Cache-Control: no-cache" seems respected.' : 'Request "Cache-Control: no-cache" may be ignored by server/CDN.';

        return $v;
    }
}
