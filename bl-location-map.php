<?php
/**
 * Plugin Name:       BL Location Map
 * Plugin URI:        https://boartlongyearproducts.com/
 * Description:       Boart Longyear global locations map — interactive dark-mode map of corporate offices and authorized distributors. Usage: [bl_location_map] or [bl_location_map header="false"] to suppress the built-in title header on pages that already carry their own heading.
 * Version:           1.0.6
 * Author:            Boart Longyear Drilling Products
 * Author URI:        https://boartlongyearproducts.com/
 * License:           Proprietary
 * Text Domain:       bl-location-map
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'BL_LOCMAP_VERSION' ) ) {
    define( 'BL_LOCMAP_VERSION', '1.0.6' );
}
define( 'BL_LOCMAP_FILE', __FILE__ );
define( 'BL_LOCMAP_DIR',  plugin_dir_path( __FILE__ ) );
define( 'BL_LOCMAP_URL',  plugin_dir_url( __FILE__ ) );

// ── Load + cache the locations dataset ───────────────────────
function bl_locmap_get_locations() {
    static $cache = null;
    if ( null !== $cache ) return $cache;

    $path = BL_LOCMAP_DIR . 'locations.json';
    if ( ! file_exists( $path ) ) { $cache = []; return $cache; }

    $raw = file_get_contents( $path );
    $data = json_decode( $raw, true );
    if ( ! is_array( $data ) ) { $cache = []; return $cache; }

    // Drop rows without coordinates
    $cache = array_values( array_filter( $data, function( $r ) {
        return isset( $r['lat'], $r['lng'] ) && is_numeric( $r['lat'] ) && is_numeric( $r['lng'] );
    } ) );
    return $cache;
}

// ── Enqueue assets only on pages that use the shortcode ──────
add_action( 'wp_enqueue_scripts', 'bl_locmap_enqueue' );
function bl_locmap_enqueue() {
    global $post;
    if ( ! is_a( $post, 'WP_Post' ) ) return;

    $has_shortcode =
        has_shortcode( $post->post_content, 'bl_location_map' ) ||
        ( false !== strpos(
            (string) get_post_meta( $post->ID, '_elementor_data', true ),
            'bl_location_map'
        ) );
    if ( ! $has_shortcode ) return;

    // Leaflet core
    wp_enqueue_style(
        'bl-leaflet',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
        [],
        '1.9.4'
    );
    wp_enqueue_script(
        'bl-leaflet',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
        [],
        '1.9.4',
        true
    );

    // Marker cluster plugin
    wp_enqueue_style(
        'bl-leaflet-cluster',
        'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css',
        [ 'bl-leaflet' ],
        '1.5.3'
    );
    wp_enqueue_script(
        'bl-leaflet-cluster',
        'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js',
        [ 'bl-leaflet' ],
        '1.5.3',
        true
    );

    // Plugin CSS + JS
    wp_enqueue_style(
        'bl-location-map',
        BL_LOCMAP_URL . 'assets/css/location-map.css',
        [ 'bl-leaflet', 'bl-leaflet-cluster' ],
        BL_LOCMAP_VERSION
    );
    wp_enqueue_script(
        'bl-location-map',
        BL_LOCMAP_URL . 'assets/js/location-map.js',
        [ 'bl-leaflet', 'bl-leaflet-cluster' ],
        BL_LOCMAP_VERSION,
        true
    );

    // Inject location data as a global so the JS does not need fetch().
    wp_localize_script(
        'bl-location-map',
        'BL_LOCATIONS',
        bl_locmap_get_locations()
    );
}

// ── Shortcode ────────────────────────────────────────────────
// [bl_location_map]                  — includes the title header
// [bl_location_map header="false"]   — omits the header
// [bl_location_map height="600px"]   — override map height (default 78vh)

add_shortcode( 'bl_location_map', 'bl_locmap_shortcode' );
function bl_locmap_shortcode( $atts ) {
    $atts = shortcode_atts(
        [
            'header' => 'true',
            'height' => '',
            'class'  => '',
        ],
        $atts,
        'bl_location_map'
    );

    $show_header = filter_var( $atts['header'], FILTER_VALIDATE_BOOLEAN );
    $extra_class = sanitize_html_class( $atts['class'] );
    $style       = '';
    if ( $atts['height'] ) {
        $h = preg_match( '/^[0-9.]+(px|vh|rem|em|%)$/', $atts['height'] )
            ? $atts['height'] : '';
        if ( $h ) $style = ' style="--map-h:' . esc_attr( $h ) . ';"';
    }

    $locations = bl_locmap_get_locations();
    $json      = wp_json_encode( $locations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

    ob_start();
    ?>
    <div class="bl-locmap <?php echo esc_attr( $extra_class ); ?>"<?php echo $style; ?>>

      <?php /* Inline data fallback if wp_localize_script did not run
               (e.g. cached page with stale assets). The JS reads either source. */ ?>
      <script type="application/json" class="bl-lm-data"><?php echo $json; ?></script>

      <?php if ( $show_header ) : ?>
      <header class="bl-lm-header">
        <h2><?php esc_html_e( 'Global Locations', 'bl-location-map' ); ?></h2>
        <span class="sub"><?php esc_html_e( 'Corporate offices & authorized distributors worldwide', 'bl-location-map' ); ?></span>
      </header>
      <?php endif; ?>

      <div class="bl-lm-toolbar">
        <input type="search" class="bl-lm-search"
               placeholder="<?php esc_attr_e( 'Search company, country, contact…', 'bl-location-map' ); ?>"
               autocomplete="off">
        <select class="bl-lm-region">
          <option value=""><?php esc_html_e( 'All regions', 'bl-location-map' ); ?></option>
        </select>
        <div class="bl-lm-toggle" role="tablist" aria-label="<?php esc_attr_e( 'Filter by type', 'bl-location-map' ); ?>">
          <button data-type="all" class="active"><?php esc_html_e( 'All', 'bl-location-map' ); ?></button>
          <button data-type="Corporate"><span class="dot corp"></span><?php esc_html_e( 'Corporate', 'bl-location-map' ); ?></button>
          <button data-type="Distributor"><span class="dot dist"></span><?php esc_html_e( 'Distributors', 'bl-location-map' ); ?></button>
        </div>
        <div class="bl-lm-count">
          <strong class="bl-lm-count-num">0</strong> <?php esc_html_e( 'showing', 'bl-location-map' ); ?>
        </div>
      </div>

      <div class="bl-lm-map-wrap">
        <div class="bl-lm-map"></div>

        <aside class="bl-lm-panel" aria-hidden="true">
          <div class="bl-lm-panel-head">
            <div>
              <div class="bl-lm-p-type bl-lm-panel-type"></div>
              <h3 class="bl-lm-p-name bl-lm-panel-name"></h3>
              <div class="bl-lm-p-region bl-lm-panel-region"></div>
            </div>
            <button class="bl-lm-panel-close" aria-label="<?php esc_attr_e( 'Close', 'bl-location-map' ); ?>">×</button>
          </div>
          <div class="bl-lm-panel-body"></div>
        </aside>
      </div>

    </div><!-- /.bl-locmap -->
    <?php
    return ob_get_clean();
}

// ── Plugin auto-updater ──────────────────────────────────────
// Mirrors the bl-diamond-bit-configurator pattern. WordPress checks
// update.json on GitHub; bump BL_LOCMAP_VERSION + update.json to release.

if ( ! class_exists( 'BL_LocMap_Updater' ) ) :

class BL_LocMap_Updater {

    const UPDATE_URL  = 'https://raw.githubusercontent.com/lichthaus/bl-location-map/main/update.json';
    const PLUGIN_SLUG = 'bl-location-map';
    const CACHE_KEY   = 'bl_locmap_update_data';
    const CACHE_TTL   = 12 * HOUR_IN_SECONDS;

    public function __construct() {
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
        add_filter( 'plugins_api',                           [ $this, 'plugin_info'       ], 10, 3 );
        add_action( 'upgrader_process_complete',             [ $this, 'clear_cache'       ], 10, 2 );
    }

    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) return $transient;

        $remote = $this->get_remote_data();
        if ( ! $remote || empty( $remote->version ) ) return $transient;

        if ( version_compare( BL_LOCMAP_VERSION, $remote->version, '<' ) ) {
            $plugin_basename = plugin_basename( BL_LOCMAP_FILE );
            $transient->response[ $plugin_basename ] = (object) [
                'slug'        => self::PLUGIN_SLUG,
                'plugin'      => $plugin_basename,
                'new_version' => sanitize_text_field( $remote->version ),
                'tested'      => sanitize_text_field( $remote->tested      ?? '6.5' ),
                'requires'    => sanitize_text_field( $remote->requires    ?? '5.0' ),
                'package'     => esc_url_raw( $remote->download_url        ?? ''    ),
                'url'         => 'https://github.com/lichthaus/bl-location-map',
            ];
        }
        return $transient;
    }

    public function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action ) return $result;
        if ( ! isset( $args->slug ) || $args->slug !== self::PLUGIN_SLUG ) return $result;

        $remote = $this->get_remote_data();
        if ( ! $remote ) return $result;

        return (object) [
            'name'          => 'BL Location Map',
            'slug'          => self::PLUGIN_SLUG,
            'version'       => sanitize_text_field( $remote->version      ?? BL_LOCMAP_VERSION ),
            'tested'        => sanitize_text_field( $remote->tested        ?? '6.5' ),
            'requires'      => sanitize_text_field( $remote->requires      ?? '5.0' ),
            'author'        => 'Boart Longyear Drilling Products',
            'homepage'      => 'https://boartlongyearproducts.com/',
            'last_updated'  => sanitize_text_field( $remote->last_updated  ?? '' ),
            'sections'      => [
                'changelog' => wp_kses_post( $remote->changelog ?? '' ),
            ],
            'download_link' => esc_url_raw( $remote->download_url ?? '' ),
        ];
    }

    public function clear_cache( $upgrader, $hook_extra ) {
        if ( isset( $hook_extra['plugin'] ) &&
             $hook_extra['plugin'] === plugin_basename( BL_LOCMAP_FILE ) ) {
            delete_transient( self::CACHE_KEY );
        }
    }

    private function get_remote_data() {
        $cached = get_transient( self::CACHE_KEY );
        if ( false !== $cached ) return $cached;

        $response = wp_remote_get( self::UPDATE_URL, [
            'timeout'    => 10,
            'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) ||
             200 !== wp_remote_retrieve_response_code( $response ) ) {
            return false;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ) );
        if ( ! is_object( $data ) ) return false;

        set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );
        return $data;
    }
}

new BL_LocMap_Updater();

endif;
