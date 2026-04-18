<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
$xprofile   = BBPPP_XProfile::instance();
$groups     = $xprofile->get_groups_with_fields();
$profile_id = isset( $bbppp_user->ID ) ? $bbppp_user->ID : get_current_user_id();
$is_own     = ( get_current_user_id() === $profile_id );
$avatar_url = get_user_meta( $profile_id, 'bbppp_avatar_url', true );
if ( ! $avatar_url ) $avatar_url = get_avatar_url( $profile_id, array( 'size' => 150 ) );
$user       = get_userdata( $profile_id );
?>
<div id="bbppp-profile" class="bbppp-wrap" data-user="<?php echo esc_attr( $profile_id ); ?>">
  <!-- Profile Header -->
  <div class="bbppp-profile-header">
    <div class="bbppp-avatar-wrap">
      <img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $user->display_name ); ?>" class="bbppp-avatar" width="150" height="150" />
      <?php if ( $is_own ) : ?>
        <a href="<?php echo esc_url( bbppp_get_account_url( 'general' ) ); ?>" class="bbppp-change-avatar" title="<?php esc_attr_e( 'Change avatar', 'bbp-profile-plus' ); ?>"><span class="dashicons dashicons-camera"></span></a>
      <?php endif; ?>
    </div>
    <div class="bbppp-profile-info">
      <h1 class="bbppp-display-name"><?php echo esc_html( $user->display_name ); ?></h1>
      <p class="bbppp-username">@<?php echo esc_html( $user->user_login ); ?></p>
      <p class="bbppp-member-since"><?php printf( esc_html__( 'Member since %s', 'bbp-profile-plus' ), esc_html( date_i18n( get_option( 'date_format' ), strtotime( $user->user_registered ) ) ) ); ?></p>
      <?php if ( $is_own ) : ?>
        <a href="<?php echo esc_url( bbppp_get_account_url() ); ?>" class="bbppp-btn bbppp-btn-secondary"><?php esc_html_e( 'Edit Profile', 'bbp-profile-plus' ); ?></a>
      <?php endif; ?>
    </div>
  </div>
  <!-- Profile Nav -->
  <?php
  $nav_items = apply_filters( 'bbppp_profile_nav', array(
    'profile' => array( 'label' => __( 'Profile', 'bbp-profile-plus' ), 'url' => bbppp_get_profile_url( $profile_id ) ),
    'topics'  => array( 'label' => __( 'Forum Topics', 'bbp-profile-plus' ), 'url' => bbppp_get_profile_url( $profile_id, 'topics' ) ),
    'replies' => array( 'label' => __( 'Replies', 'bbp-profile-plus' ), 'url' => bbppp_get_profile_url( $profile_id, 'replies' ) ),
  ) );
  $current_tab = isset( $bbppp_tab ) ? $bbppp_tab : 'profile';
  ?>
  <nav class="bbppp-profile-nav">
    <ul>
      <?php foreach ( $nav_items as $slug => $item ) : ?>
        <li class="<?php echo ( $current_tab === $slug ) ? 'current' : ''; ?>">
          <a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
        </li>
      <?php endforeach; ?>
    </ul>
  </nav>
  <!-- Profile Content -->
  <div class="bbppp-profile-content">
    <?php if ( 'profile' === $current_tab ) : ?>
      <?php if ( empty( $groups ) ) : ?>
        <p class="bbppp-no-fields"><?php esc_html_e( 'No profile fields found.', 'bbp-profile-plus' ); ?></p>
      <?php else : ?>
        <?php foreach ( $groups as $group ) : ?>
          <?php if ( empty( $group->fields ) ) continue; ?>
          <div class="bbppp-profile-group" id="bbppp-group-<?php echo esc_attr( $group->id ); ?>">
            <h2 class="bbppp-group-title"><?php echo esc_html( $group->name ); ?></h2>
            <table class="bbppp-profile-fields">
              <tbody>
              <?php foreach ( $group->fields as $field ) : ?>
                <?php
                $value = $xprofile->get_value( $field->id, $profile_id );
                if ( $value === '' || $value === null || $value === false ) continue;
                $display = is_array( $value ) ? implode( ', ', array_map( 'esc_html', $value ) ) : esc_html( $value );
                ?>
                <tr class="bbppp-field-row bbppp-field-type-<?php echo esc_attr( $field->type ); ?>">
                  <td class="bbppp-field-label"><?php echo esc_html( $field->name ); ?></td>
                  <td class="bbppp-field-value">
                    <?php if ( $field->type === 'url' ) : ?>
                      <a href="<?php echo esc_url( $value ); ?>" target="_blank" rel="nofollow"><?php echo esc_html( $value ); ?></a>
                    <?php elseif ( $field->type === 'textarea' ) : ?>
                      <?php echo wp_kses_post( wpautop( $value ) ); ?>
                    <?php else : ?>
                      <?php echo $display; ?>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    <?php elseif ( 'topics' === $current_tab ) : ?>
      <?php bbppp_render_user_topics( $profile_id ); ?>
    <?php elseif ( 'replies' === $current_tab ) : ?>
      <?php bbppp_render_user_replies( $profile_id ); ?>
    <?php else : ?>
      <?php do_action( 'bbppp_profile_tab_content', $current_tab, $profile_id ); ?>
    <?php endif; ?>
  </div>
</div>
