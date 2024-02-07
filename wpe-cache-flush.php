<?php
/**
 * Plugin Name: Chillybin WP Engine Cache Flush
 * Description: Programmatically flush the WP Engine Cache
 * Version: 1.0.0
 * Author: Kelton Smith, Chillybin
 * Author URI: https://www.chillybin.co/
 */

namespace Chillybin\WPE_Cache_Flush;

function wpe_cache_flush_menu()
{
    add_submenu_page(
        'options-general.php',
        'Flush WPEngine Cache',
        'Flush WPEngine Cache',
        'manage_options',
        'wpe-cache-flush',
        __NAMESPACE__ . '\wpe_cache_flush_page'
    );
}
add_action('admin_menu', __NAMESPACE__ . '\wpe_cache_flush_menu');

function private_key_notice()
{
    if (!defined('WPE_CACHE_FLUSH')) {
        $class = 'notice notice-error';
        $private_key_link = '<a href="https://www.random.org/strings/?num=10&len=20&digits=on&upperalpha=on&loweralpha=on&unique=on&format=html&rnd=new" target="_blank">private key</a>';
        $message = sprintf(
            __('Your private key is empty. Please create a %s in wp-config.php. For example: define( \'WPE_CACHE_FLUSH\', %s );', 'WPE_Cache_Flush'),
            $private_key_link,
            '$private_key'
        );

        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
    }
}
add_action('admin_notices', __NAMESPACE__ . '\private_key_notice');
function wpe_cache_flush_page()
{
    $flush_token = get_flush_token();
    $disable_class = false === $flush_token ? ' disabled' : '';
    ?>
    <div class="wrap">
        <h2>Flush WPEngine Cache</h2>
        <br>
        <form method="get" action="/">
            <input type="hidden" name="wpe-cache-flush" value="<?php echo $flush_token; ?>">
            <button type="submit" class="button button-primary<?php echo $disable_class; ?>">Flush Cache</button>
        </form>
    </div>
    <?php
}

function get_flush_token()
{
    $flush_token = getenv('WPE_CACHE_FLUSH');

    if (!empty($flush_token)) {
        return $flush_token;
    }

    if (defined('WPE_CACHE_FLUSH')) {
        return WPE_CACHE_FLUSH;
    }

    return apply_filters(__NAMESPACE__ . '/wpe_cache_flush_token', false);
}

add_action('init', function () {

    $key = 'wpe-cache-flush';

    if (empty($_GET[$key])) {
        return;
    }

    $flush_token = get_flush_token();

    if (false === $flush_token) {
        return;
    }

    if ($flush_token !== $_GET[$key]) {
        return;
    }

    $error = cache_flush();

    header("Content-Type: text/plain");
    header("X-WPE-Host: " . gethostname() . " " . $_SERVER['SERVER_ADDR']);

    echo "All Caches were purged!";
    echo $error;

    exit(0);
});

/**
 * Allow cache flushing to be called independently of web hook
 *
 * @return string|bool
 */
function cache_flush()
{
    // Don't cause a fatal if there is no WpeCommon class
    if (!class_exists('WpeCommon')) {
        return false;
    }

    if (method_exists('WpeCommon', 'purge_memcached')) {
        \WpeCommon::purge_memcached();
    }

    if (method_exists('WpeCommon', 'clear_maxcdn_cache')) {
        \WpeCommon::clear_maxcdn_cache();
    }

    if (method_exists('WpeCommon', 'purge_varnish_cache')) {
        \WpeCommon::purge_varnish_cache();
    }

    global $wp_object_cache;
    // Check for valid cache. Sometimes this is broken -- we don't know why! -- and it crashes when we flush.
    // If there's no cache, we don't need to flush anyway.
    $error = '';

    if ($wp_object_cache && is_object($wp_object_cache)) {
        try {
            wp_cache_flush();
        } catch (\Exception $ex) {
            $error = "Warning: error flushing WordPress object cache: " . $ex->getMessage();
        }
    }

    return $error;
}
