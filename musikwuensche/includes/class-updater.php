<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MW_Updater {

    private $plugin_slug;
    private $plugin_file;
    private $github_repo;
    private $github_token;
    private $current_version;

    public function __construct( $plugin_file, $github_repo, $github_token = '' ) {
        $this->plugin_file     = $plugin_file;
        $this->plugin_slug     = plugin_basename( $plugin_file );
        $this->github_repo     = $github_repo;
        $this->github_token    = $github_token;
        $this->current_version = MW_VERSION;

        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
        add_filter( 'plugins_api',                           array( $this, 'plugin_info' ), 10, 3 );
        add_filter( 'upgrader_post_install',                 array( $this, 'after_install' ), 10, 3 );
        add_action( 'wp_ajax_mw_download_update', array( $this, 'proxy_download' ) );
    }

    private function get_release() {
        $key    = 'mw_github_release_' . md5( $this->github_repo );
        $cached = get_transient( $key );
        if ( $cached !== false ) return $cached;

        $args = array(
            'headers' => array(
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
            ),
            'timeout' => 15,
        );
        if ( $this->github_token ) $args['headers']['Authorization'] = 'Bearer ' . $this->github_token;

        $response = wp_remote_get( "https://api.github.com/repos/{$this->github_repo}/releases/latest", $args );
        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            set_transient( $key, null, HOUR_IN_SECONDS );
            return null;
        }
        $release = json_decode( wp_remote_retrieve_body( $response ) );
        set_transient( $key, $release, 12 * HOUR_IN_SECONDS );
        return $release;
    }

    private function get_github_zip_url( $release ) {
        if ( ! empty( $release->assets ) ) {
            foreach ( $release->assets as $asset ) {
                if ( $asset->content_type === 'application/zip' || substr( $asset->name, -4 ) === '.zip' ) {
                    return $this->github_token ? $asset->url : $asset->browser_download_url;
                }
            }
        }
        return "https://api.github.com/repos/{$this->github_repo}/zipball/{$release->tag_name}";
    }

    private function get_package_url( $release ) {
        if ( $this->github_token ) {
            return add_query_arg( array(
                'action'  => 'mw_download_update',
                'nonce'   => wp_create_nonce( 'mw_download_update' ),
                'version' => ltrim( $release->tag_name, 'vV' ),
            ), admin_url( 'admin-ajax.php' ) );
        }
        return $this->get_github_zip_url( $release );
    }

    public function proxy_download() {
        if ( ! current_user_can( 'update_plugins' ) ) wp_die( 'Forbidden', 403 );
        check_ajax_referer( 'mw_download_update', 'nonce' );

        $release = $this->get_release();
        if ( ! $release ) wp_die( 'Release not found', 404 );

        $args = array(
            'headers' => array(
                'Accept'     => 'application/octet-stream',
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
            ),
            'timeout'  => 60,
            'stream'   => true,
            'filename' => get_temp_dir() . 'mw-update.zip',
        );
        if ( $this->github_token ) $args['headers']['Authorization'] = 'Bearer ' . $this->github_token;

        $response = wp_remote_get( $this->get_github_zip_url( $release ), $args );
        if ( is_wp_error( $response ) ) wp_die( $response->get_error_message(), 500 );

        $file = $response['filename'];
        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="musikwuensche.zip"' );
        header( 'Content-Length: ' . filesize( $file ) );
        readfile( $file );
        @unlink( $file );
        exit;
    }

    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) return $transient;
        $release = $this->get_release();
        if ( ! $release ) return $transient;

        $latest = ltrim( $release->tag_name, 'vV' );
        if ( version_compare( $latest, $this->current_version, '>' ) ) {
            $transient->response[ $this->plugin_slug ] = (object) array(
                'slug'         => dirname( $this->plugin_slug ),
                'plugin'       => $this->plugin_slug,
                'new_version'  => $latest,
                'url'          => "https://github.com/{$this->github_repo}",
                'package'      => $this->get_package_url( $release ),
                'tested'       => get_bloginfo( 'version' ),
                'requires_php' => '7.4',
            );
        }
        return $transient;
    }

    public function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) return $result;
        if ( ! isset( $args->slug ) || $args->slug !== dirname( $this->plugin_slug ) ) return $result;
        $release = $this->get_release();
        if ( ! $release ) return $result;

        return (object) array(
            'name'          => 'Hochzeit Musikwünsche',
            'slug'          => dirname( $this->plugin_slug ),
            'version'       => ltrim( $release->tag_name, 'vV' ),
            'author'        => 'Tobias Hirche',
            'homepage'      => "https://github.com/{$this->github_repo}",
            'requires'      => '5.8',
            'tested'        => get_bloginfo( 'version' ),
            'sections'      => array(
                'description' => 'Sammelt Musikwünsche der Gäste mit Spotify- und Apple-Music-Integration.',
                'changelog'   => nl2br( esc_html( $release->body ?? '' ) ),
            ),
            'download_link' => $this->get_package_url( $release ),
        );
    }

    public function after_install( $response, $hook_extra, $result ) {
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_slug ) return $response;

        global $wp_filesystem;
        $plugin_dir = dirname( $this->plugin_slug );
        $target     = WP_PLUGIN_DIR . '/' . $plugin_dir;
        $extracted  = $result['destination'] ?? '';

        if ( $extracted && $wp_filesystem->exists( $extracted ) ) {
            if ( trailingslashit( $extracted ) !== trailingslashit( $target ) ) {
                if ( $wp_filesystem->exists( $target ) ) $wp_filesystem->delete( $target, true );
                $wp_filesystem->move( $extracted, $target );
                $result['destination']        = $target;
                $result['destination_name']   = $plugin_dir;
                $result['remote_destination'] = $target;
            }
        }

        if ( is_plugin_inactive( $this->plugin_slug ) ) activate_plugin( $this->plugin_slug );
        delete_transient( 'mw_github_release_' . md5( $this->github_repo ) );

        return $result;
    }
}
