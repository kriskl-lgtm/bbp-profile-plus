<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class BBPPP_Router {
  private static $instance = null;
  private $route   = null;
  private $member  = null;
  private $action  = 'view';
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
    add_filter( 'register_url',      array( $this, 'get_register_url' ) );
    add_filter( 'bbp_get_user_profile_url', array( $this, 'bbp_member_url' ), 10, 3 );
    add_filter( 'bp_loggedin_user_link',    array( $this, 'bp_loggedin_link' ) );
  }
  public static function register_rewrite_rules() {
    add_rewrite_rule( '^members/([^/]+)/?$',           'index.php?' . self::QV_MEMBER . '=$matches[1]&' . self::QV_ACTION . '=view',       'top' );
    add_rewrite_rule( '^members/([^/]+)/([^/]+)/?$',   'index.php?' . self::QV_MEMBER . '=$matches[1]&' . self::QV_ACTION . '=$matches[2]', 'top' );
    add_rewrite_rule( '^members/([^/]+)/account/?$',   'index.php?' . self::QV_MEMBER . '=$matches[1]&' . self::QV_ACTION . '=account',     'top' );
    add_rewrite_rule( '^members/([^/]+)/account/([^/]+)/?$', 'index.php?' . self::QV_MEMBER . '=$matches[1]&' . self::QV_ACTION . '=account&bbppp_tab=$matches[2]', 'top' );
  }
  public function query_vars( $vars ) {
    $vars[] = self::QV_MEMBER;
    $vars[] = self::QV_ACTION;
    $vars[] = 'bbppp_tab';
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
        $this->action   = in_array( $a, array( 'view', 'topics', 'replies', 'account' ), true ) ? $a : 'view';
        $this->is_bbppp = true;
        $wp_query->is_404 = false;
        $wp_query->is_page = true;
        status_header( 200 );
      }
    }
  }
  public function template_include( $template ) {
    if ( 'member' !== $this->route ) return $template;
    // Account settings - only for own profile
    if ( 'account' === $this->action ) {
      if ( ! is_user_logged_in() || ( get_current_user_id() !== $this->member->ID && ! current_user_can( 'manage_options' ) ) ) {
        wp_safe_redirect( $this->member_url( $this->member ) ); exit;
      }
      $t = BBPPP_TPL_DIR . 'account.php';
      if ( file_exists( $t ) ) {
        $bbppp_user = $this->member;
        $bbppp_tab  = sanitize_key( get_query_var( 'bbppp_tab' ) ) ?: 'general';
        set_query_var( 'bbppp_user', $bbppp_user );
        set_query_var( 'bbppp_tab',  $bbppp_tab );
        return $t;
      }
      return $template;
    }
    // Profile view (view / topics / replies)
    $t = BBPPP_TPL_DIR . 'profile.php';
    if ( file_exists( $t ) ) {
      $bbppp_user = $this->member;
      $bbppp_tab  = $this->action;
      set_query_var( 'bbppp_user', $bbppp_user );
      set_query_var( 'bbppp_tab',  $bbppp_tab );
      return $t;
    }
    return $template;
  }
  public function redirects() {
    // Nothing required currently
    		// Redirect /members/ to user's profile or registration
		if ( is_page() && get_query_var( self::QV_MEMBER ) === '' && $_SERVER['REQUEST_URI'] === '/members/' ) {
			if ( is_user_logged_in() ) {
				$user = wp_get_current_user();
				wp_safe_redirect( $this->member_url( $user ) );
				exit;
			} else {
				wp_safe_redirect( wp_registration_url() );
				exit;
			}
		}
  }
  public function get_route()    { return $this->route; }
  public function get_member()   { return $this->member; }
  public function get_action()   { return $this->action; }
  public function is_bbppp_page(){ return $this->is_bbppp; }
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
// ---- Template helper functions ----
if ( ! function_exists( 'bbppp_get_profile_url' ) ) {
  function bbppp_get_profile_url( $user_id_or_obj, $tab = '' ) {
    $user = is_object( $user_id_or_obj ) ? $user_id_or_obj : get_userdata( $user_id_or_obj );
    if ( ! $user ) return home_url( '/' );
    $base = home_url( '/members/' . $user->user_nicename . '/' );
    return $tab ? trailingslashit( $base . $tab ) : $base;
  }
}
if ( ! function_exists( 'bbppp_get_account_url' ) ) {
  function bbppp_get_account_url( $tab = '' ) {
    $u = wp_get_current_user();
    if ( ! $u->exists() ) return wp_login_url();
    $base = home_url( '/members/' . $u->user_nicename . '/account/' );
    return $tab ? trailingslashit( $base . $tab ) : $base;
  }
}
if ( ! function_exists( 'bbppp_render_user_topics' ) ) {
  function bbppp_render_user_topics( $user_id ) {
    if ( function_exists( 'bbp_get_user_topics_started' ) ) {
      echo bbp_get_user_topics_started( $user_id );
    } else {
      $q = new WP_Query( array(
        'post_type'      => bbp_get_topic_post_type(),
        'author'         => $user_id,
        'posts_per_page' => 20,
        'no_found_rows'  => true,
      ) );
      if ( $q->have_posts() ) {
        echo '<ul class="bbppp-topic-list">';
        while ( $q->have_posts() ) { $q->the_post();
          echo '<li><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></li>';
        }
        echo '</ul>';
        wp_reset_postdata();
      } else {
        echo '<p>' . esc_html__( 'No topics started yet.', 'bbp-profile-plus' ) . '</p>';
      }
    }
  }
}
if ( ! function_exists( 'bbppp_render_user_replies' ) ) {
  function bbppp_render_user_replies( $user_id ) {
    $q = new WP_Query( array(
      'post_type'      => bbp_get_reply_post_type(),
      'author'         => $user_id,
      'posts_per_page' => 20,
      'no_found_rows'  => true,
    ) );
    if ( $q->have_posts() ) {
      echo '<ul class="bbppp-reply-list">';
      while ( $q->have_posts() ) { $q->the_post();
        echo '<li><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a> &mdash; <small>' . esc_html( get_the_date() ) . '</small></li>';
      }
      echo '</ul>';
      wp_reset_postdata();
    } else {
      echo '<p>' . esc_html__( 'No replies yet.', 'bbp-profile-plus' ) . '</p>';
    }
  }
}
