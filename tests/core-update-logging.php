<?php
/**
 * Standalone regression tests for core update logging; no WordPress or database is loaded.
 * Run: php tests/core-update-logging.php [source-root]
 * The optional root allows the same tests to check an earlier revision.
 */

namespace WP_MainWP_Stream {
    class Author {
        public function __construct( $id ) {}
        public function get_current_agent() { return $GLOBALS['fixture_agent']; }
        public function get_display_name() { return 'Fixture'; }
        public function get_role() { return ''; }
    }
}

namespace {
    if ( 'cli' !== PHP_SAPI || defined( 'ABSPATH' ) ) {
        exit( 1 );
    }

    define( 'ABSPATH', __DIR__ . '/unused-wordpress/' );
    define( 'WP_PLUGIN_DIR', __DIR__ . '/unused-plugins' );

    class WP_User {
        public $ID;
        public $roles = array();
        public $user_email = '';
        public $user_login = '';
        public function __construct( $id ) { $this->ID = $id; }
    }

    class WP_Error {}

    class MemoryRecords {
        public $rows = array();
        public $failures_remaining = 0;
        public function insert( $record ) {
            if ( $this->failures_remaining > 0 ) {
                --$this->failures_remaining;
                return false;
            }
            $this->rows[] = $record;
            return count( $this->rows );
        }
    }

    class FixtureTheme {
        public function get( $key ) { return 'Name' === $key ? 'Fixture Theme' : '2.0'; }
    }

    function wp_mainwp_stream_filter_input( $type, $name, $filter ) { return ''; }
    function is_plugin_active_for_network( $plugin = '' ) { return false; }
    function wp_mainwp_stream_get_instance() { return $GLOBALS['fixture_plugin']; }
    function wp_mainwp_stream_get_wordpress_version() { return $GLOBALS['fixture_version']; }
    function get_current_user_id() { return 0; }
    function get_current_blog_id() { return 1; }
    function is_multisite() { return false; }
    function apply_filters( $hook, $value ) {
        return 'wp_mainwp_stream_is_record_excluded' === $hook ? $GLOBALS['fixture_excluded'] : $value;
    }
    function wp_mainwp_stream_get_iso_8601_extended_date( $timestamp, $offset, $extended ) { return '2026-10-23 12:00:00'; }
    function esc_html__( $text, $domain ) { return $text; }
    function __( $text, $domain ) { return $text; }
    function _x( $text, $context, $domain ) { return $text; }
    function get_plugin_data( $file ) { return array( 'Name' => 'Fixture Plugin', 'Version' => '2.0' ); }
    function wp_get_theme( $slug ) { return new FixtureTheme(); }
    function sanitize_key( $key ) { return $key; }
    function wp_unslash( $value ) { return $value; }
    function is_wp_error( $value ) { return $value instanceof WP_Error; }

    $source_root = isset( $argv[1] ) ? $argv[1] : dirname( __DIR__ );
    require $source_root . '/classes/class-connector.php';
    require $source_root . '/classes/class-log.php';
    require $source_root . '/connectors/class-connector-installer.php';

    function setup_case( $cron_tracking, $agent = 'wp_cron' ) {
        $GLOBALS['fixture_agent'] = $agent;
        $GLOBALS['fixture_version'] = '6.8.2';
        $GLOBALS['fixture_excluded'] = false;
        $GLOBALS['pagenow'] = 'wp_cron' === $agent ? 'wp-cron.php' : 'update-core.php';
        $GLOBALS['fixture_plugin'] = (object) array(
            'settings' => (object) array( 'options' => array( 'advanced_wp_cron_tracking' => $cron_tracking ) ),
            'db' => new MemoryRecords(),
        );
        $GLOBALS['fixture_plugin']->log = new \WP_MainWP_Stream\Log( $GLOBALS['fixture_plugin'] );
        return new \WP_MainWP_Stream\Connector_Installer();
    }

    function core_result( $result, $include_result = true, $target = '6.8.3' ) {
        $entry = (object) array( 'item' => (object) array( 'version' => $target ) );
        if ( $include_result ) {
            $entry->result = $result;
        }
        return array( 'core' => array( $entry ) );
    }

    function expect_same( $expected, $actual, $message ) {
        if ( $expected !== $actual ) {
            throw new \Exception( $message . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
        }
    }

    function expect_count( $expected ) {
        expect_same( $expected, count( $GLOBALS['fixture_plugin']->db->rows ), 'record count' );
    }

    function expect_core_record( $automatic = true, $new_version = '6.8.3' ) {
        expect_count( 1 );
        $row = $GLOBALS['fixture_plugin']->db->rows[0];
        expect_same( 'installer', $row['connector'], 'connector' );
        expect_same( 'wordpress', $row['context'], 'context' );
        expect_same( 'updated', $row['action'], 'action' );
        expect_same( '6.8.2', $row['meta']['old_version'], 'old version' );
        expect_same( $new_version, $row['meta']['new_version'], 'new version' );
        expect_same( $automatic, $row['meta']['auto_updated'], 'automatic flag' );
    }

    $case_count = 0;
    $failed_count = 0;
    function run_case( $name, $test ) {
        global $case_count, $failed_count;
        ++$case_count;
        try {
            $test();
            echo "PASS: $name\n";
        } catch ( \Exception $error ) {
            ++$failed_count;
            echo "FAIL: $name — " . $error->getMessage() . "\n";
        }
    }

    foreach ( array( 0, 1 ) as $cron ) {
        run_case( "normal core success, cron=$cron", function () use ( $cron ) {
            $c = setup_case( $cron );
            $c->callback_pre_auto_update( 'core' );
            $GLOBALS['fixture_version'] = '6.8.3';
            $c->callback__core_updated_successfully( '6.8.3' );
            $c->callback_automatic_updates_complete( core_result( '6.8.3' ) );
            expect_core_record();
            // A second completion notification must not duplicate the successful record.
            $c->callback_automatic_updates_complete( core_result( '6.8.3' ) );
            expect_core_record();
            expect_same( '', $c->current_wordpress_version, 'old version cleared after insert' );
        } );

        run_case( "completion-only success, cron=$cron", function () use ( $cron ) {
            $c = setup_case( $cron );
            $c->callback_pre_auto_update( 'core' );
            $c->callback_automatic_updates_complete( core_result( '6.8.3' ) );
            expect_core_record();
        } );

        foreach ( array( 'error' => new WP_Error(), 'false' => false, 'null' => null, 'true' => true, 'empty' => '', 'integer' => 683, 'array' => array() ) as $label => $result ) {
            run_case( "reject $label core result, cron=$cron", function () use ( $cron, $result ) {
                $c = setup_case( $cron );
                $c->callback_pre_auto_update( 'core' );
                $c->callback_automatic_updates_complete( core_result( $result ) );
                expect_count( 0 );
            } );
        }

        run_case( "reject missing core result, cron=$cron", function () use ( $cron ) {
            $c = setup_case( $cron );
            $c->callback_pre_auto_update( 'core' );
            $c->callback_automatic_updates_complete( core_result( null, false ) );
            expect_count( 0 );
        } );

        run_case( "excluded core records stay excluded, cron=$cron", function () use ( $cron ) {
            $c = setup_case( $cron );
            $GLOBALS['fixture_excluded'] = true;
            $c->callback_pre_auto_update( 'core' );
            $GLOBALS['fixture_version'] = '6.8.3';
            $c->callback__core_updated_successfully( '6.8.3' );
            $c->callback_automatic_updates_complete( core_result( '6.8.3' ) );
            expect_count( 0 );
        } );

        run_case( "retry failed insert without losing old version, cron=$cron", function () use ( $cron ) {
            $c = setup_case( $cron );
            $GLOBALS['fixture_plugin']->db->failures_remaining = 1;
            $c->callback_pre_auto_update( 'core' );
            $GLOBALS['fixture_version'] = '6.8.3';
            $c->callback__core_updated_successfully( '6.8.3' );
            expect_same( '6.8.2', $c->current_wordpress_version, 'old version retained after rejected log' );
            $c->callback_automatic_updates_complete( core_result( '6.8.3' ) );
            if ( ! $cron ) {
                expect_count( 0 );
                expect_same( '6.8.2', $c->current_wordpress_version, 'old version retained after failed fallback insert' );
                $c->callback_automatic_updates_complete( core_result( '6.8.3' ) );
            }
            expect_core_record();
            $c->callback_automatic_updates_complete( core_result( '6.8.3' ) );
            expect_count( 1 );
        } );
    }

    run_case( 'use actual successful version rather than offered target', function () {
        $c = setup_case( 0 );
        $c->callback_pre_auto_update( 'core' );
        $c->callback_automatic_updates_complete( core_result( '6.8.3', true, '6.8.4' ) );
        expect_core_record( true, '6.8.3' );
    } );

    foreach ( array( 'same' => '6.8.2', 'older' => '6.8.1' ) as $label => $version ) {
        run_case( "ignore $label core version", function () use ( $version ) {
            $c = setup_case( 0 );
            $c->callback_pre_auto_update( 'core' );
            $c->callback_automatic_updates_complete( core_result( $version, true, $version ) );
            expect_count( 0 );
        } );
    }

    run_case( 'ignore completion without a captured old version', function () {
        $c = setup_case( 0 );
        $c->callback_automatic_updates_complete( core_result( '6.8.3' ) );
        expect_count( 0 );
    } );

    foreach ( array( 'empty' => array(), 'missing core' => array( 'plugin' => array() ), 'invalid core' => array( 'core' => 'invalid' ), 'invalid entry' => array( 'core' => array( null ) ), 'missing target' => array( 'core' => array( (object) array( 'result' => '6.8.3' ) ) ) ) as $label => $results ) {
        run_case( "ignore $label results", function () use ( $results ) {
            $c = setup_case( 0 );
            $c->callback_pre_auto_update( 'core' );
            $c->callback_automatic_updates_complete( $results );
            expect_count( 0 );
        } );
    }

    foreach ( array( 'plugin', 'theme' ) as $type ) {
        foreach ( array( true, false ) as $success ) {
            run_case( $type . ' automatic update, success=' . (int) $success, function () use ( $type, $success ) {
                $c = setup_case( 0 );
                $entry = (object) array( 'item' => (object) array( $type => 'fixture/fixture.php', 'current_version' => '1.0' ), 'result' => $success );
                $c->callback_automatic_updates_complete( array( $type => array( $entry ) ) );
                expect_count( $success ? 1 : 0 );
                if ( $success ) {
                    $row = $GLOBALS['fixture_plugin']->db->rows[0];
                    expect_same( $type . 's', $row['context'], 'context' );
                    expect_same( '1.0', $row['meta']['old_version'], 'old version' );
                    expect_same( '2.0', $row['meta']['version'], 'new version' );
                    expect_same( true, $row['meta']['auto_updated'], 'automatic flag' );
                }
            } );
        }
    }

    run_case( 'manual core update', function () {
        $c = setup_case( 0, 'user' );
        $_GET = array( 'action' => 'do-core-upgrade' );
        $_POST = array( 'upgrade' => 1 );
        $c->callback_load_update_core_php();
        $GLOBALS['fixture_version'] = '6.8.3';
        $c->callback__core_updated_successfully( '6.8.3' );
        expect_core_record( false );
    } );

    echo "$case_count cases, $failed_count failures\n";
    exit( $failed_count ? 1 : 0 );
}
