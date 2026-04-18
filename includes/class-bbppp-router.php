<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class BBPPP_Router {
    private static $instance = null;
    private $route = null;
    private $member = null;
    private $action = 'view';
    private $bp_pages = array();
    private $is_bbppp = false;
    const QV_MEMBER = 'bbppp_member';
    const QV_ACTION = 'bbppp_action';
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }
    private function __construct() {
        $this->bp_pages = (array) get_option( 'bp-pages', array() );
        add_action( 'init',              array( __CLASS__, 'register_rewrite_rules' ) );
        add_filter( 'query_vars',        array( $this, 'query_vars' ) );
        add_action( 'wp',                array( $this, 'resolve' ) );
        add_filter( 'template_include',  array( $this, 'template_include' ), 99 );
        add_action( 'template_redirect', array( $this, 'redirects' ) );
        add_filter( 'register_url',             array( $this, 'get_register_url' ) );
        add_filter( 'bbp_get_user_profile_url', array( $this, 'bbp_member_url' ), 10, 3 );
        add_filter( 'bp_loggedin_user_link',    array( $this, 'bp_loggedin_link' ) );
    }
    public static function register_rewrite_rules() {
        add_rewrite_rule( '^members/([^/]+)/?$',         'index.php?' . self::QV_MEMBER . '=$matches[1]&' . self::QV_ACTION . '=view',       'top' );
        add_rewrite_rule( '^members/([^/]+)/([^/]+)/?$', 'index.php?' . self::QV_MEMBER . '=$matches[1]&' . self::QV_ACTION . '=$matches[2]', 'top' );
    }
    public function query_vars( $vars ) {
        $vars[] = self::QV_MEMBER;
        $vars[] = self::QV_ACTION;
        return $vars;
    }
    public function resolve() {
        global $wp_query;
        $slug = get_query_var( self::QV_MEMBER );
        if ( $slug ) {
            $user = get_user_by( 'slug', sanitize_title( $slug ) );
            if ( ! $user ) $user = get_user_by( 'login', sanitize_user( $slug ) );
            if ( $user ) {
                $this->route  = 'member';
                $this->member = $user;
                $a = sanitize_key( get_query_var( self::QV_ACTION ) );
                $this->action = in_array( $a, array( 'view','edit','settings','forums' ), true ) ? $a : 'view';
                $this->is_bbppp    = true;
                $wp_query->is_404  = false;
                $wp_query->is_page = true;
                status_header( 200 );
            }
            return;
        }
        if ( ! empty( $this->bp_pages['register'] ) && is_page( $this->bp_pages['register'] ) ) {
            $this->route = 'register'; $this->is_bbppp = true; return;
        }
        if ( ! empty( $this->bp_pages['activate'] ) && is_page( $this->bp_pages['activate'] ) ) {
            $this->route = 'activate'; $this->is_bbppp = true; return;
        }
    }
    public function template_include( $template ) {
        switch ( $this->route ) {
            case 'register':
                $t = BBPPP_TPL_DIR . 'register.php';
                return file_exists( $t ) ? $t : $template;
            case 'activate':
                $t = BBPPP_TPL_DIR . 'activate.php';
                return file_exists( $t ) ? $t : $template;
            case 'member':
                if ( in_array( $this->action, array( 'edit','settings' ), true ) ) {
                    if ( ! is_user_logged_in() || ( get_current_user_id() !== $this->member->ID && ! current_user_can( 'manage_options' ) ) ) {
                        wp_safe_redirect( home_url( '/members/' . $this->member->user_nicename . '/' ) ); exit;
                    }
                }
                $t = BBPPP_TPL_DIR . 'members/single/' . $this->action . '.php';
                return file_exists( $t ) ? $t : $template;
        }
        return $template;
    }
    public function redirects() {
        if ( 'register' === $this->route && is_user_logged_in() ) {
            wp_safe_redirect( home_url( '/members/' . wp_get_current_user()->user_nicename . '/' ) ); exit;
        }
    }
    public function get_route()     { return $this->route; }
    public function get_member()    { return $this->member; }
    public function get_action()    { return $this->action; }
    public function is_bbppp_page() { return $this->is_bbppp; }
    public function member_url( $user, $action = '' ) {
        $base = home_url( '/members/' . $user->user_nicename . '/' );
        return $action ? trailingslashit( $base . $action ) : $base;
    }
    public function get_register_url( $url = '' ) {
        if ( ! empty( $this->bp_pages['register'] ) ) return get_permalink( $this->bp_pages['register'] );
        return $url;
    }
    public function bbp_member_url( $url, $user_id, $nicename ) {
        return home_url( '/members/' . $nicename . '/' );
    }
    public function bp_loggedin_link( $url ) {
        $u = wp_get_current_user();
        return $u->exists() ? home_url( '/members/' . $u->user_nicename . '/' ) : $url;
    }
}
