<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class BBPPP_XProfile {
    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }
    private function __construct() {}
    public function get_groups_with_fields() {
        global $wpdb;
        $groups = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}bp_xprofile_groups ORDER BY group_order ASC" );
        foreach ( $groups as $g ) $g->fields = $this->get_fields( $g->id );
        return $groups;
    }
    public function get_fields( $group_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bp_xprofile_fields WHERE group_id = %d AND parent_id = 0 ORDER BY field_order ASC", $group_id ) );
    }
    public function get_all_fields() {
        global $wpdb;
        return $wpdb->get_results( "SELECT f.*, g.name AS group_name FROM {$wpdb->prefix}bp_xprofile_fields f LEFT JOIN {$wpdb->prefix}bp_xprofile_groups g ON g.id = f.group_id WHERE f.parent_id = 0 ORDER BY g.group_order ASC, f.field_order ASC" );
    }
    public function get_field_options( $field_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bp_xprofile_fields WHERE parent_id = %d ORDER BY option_order ASC", $field_id ) );
    }
    public function get_value( $field_id, $user_id ) {
        global $wpdb;
        $val = $wpdb->get_var( $wpdb->prepare( "SELECT value FROM {$wpdb->prefix}bp_xprofile_data WHERE field_id = %d AND user_id = %d", $field_id, $user_id ) );
        if ( $val && is_serialized( $val ) ) return maybe_unserialize( $val );
        return $val;
    }
    public function set_value( $field_id, $user_id, $value ) {
        global $wpdb;
        $table = $wpdb->prefix . 'bp_xprofile_data';
        $save  = is_array( $value ) ? serialize( $value ) : $value;
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE field_id = %d AND user_id = %d", $field_id, $user_id ) );
        if ( $existing ) {
            $wpdb->update( $table, array( 'value' => $save, 'last_updated' => current_time( 'mysql' ) ), array( 'field_id' => $field_id, 'user_id' => $user_id ), array( '%s', '%s' ), array( '%d', '%d' ) );
        } else {
            $wpdb->insert( $table, array( 'field_id' => $field_id, 'user_id' => $user_id, 'value' => $save, 'last_updated' => current_time( 'mysql' ) ), array( '%d', '%d', '%s', '%s' ) );
        }
    }
    public function get_field_by_name( $name ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bp_xprofile_fields WHERE name = %s AND parent_id = 0 LIMIT 1", $name ) );
    }
    public function render_field( $field, $value = '' ) {
        $key   = 'bbppp_xf_' . $field->id;
        $id    = 'bbppp_field_' . $field->id;
        $req   = $field->is_required ? ' required' : '';
        $rlbl  = $field->is_required ? ' <span class="bbppp-req">*</span>' : '';
        echo '<div class="bbppp-field bbppp-type-' . esc_attr( $field->type ) . '">';
        echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $field->name ) . $rlbl . '</label>';
        switch ( $field->type ) {
            case 'textarea':
                echo '<textarea name="' . esc_attr( $key ) . '" id="' . esc_attr( $id ) . '"' . $req . '>' . esc_textarea( $value ) . '</textarea>';
                break;
            case 'selectbox':
                $opts = $this->get_field_options( $field->id );
                echo '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $id ) . '"' . $req . '>';
                echo '<option value="">' . esc_html__( '-- Select --', 'bbppp' ) . '</option>';
                foreach ( $opts as $o ) {
                    echo '<option value="' . esc_attr( $o->name ) . '"' . selected( $value, $o->name, false ) . '>' . esc_html( $o->name ) . '</option>';
                }
                echo '</select>';
                break;
            case 'multiselectbox':
                $opts = $this->get_field_options( $field->id );
                $arr  = is_array( $value ) ? $value : (array) maybe_unserialize( $value );
                echo '<select name="' . esc_attr( $key ) . '[]" id="' . esc_attr( $id ) . '" multiple' . $req . '>';
                foreach ( $opts as $o ) {
                    echo '<option value="' . esc_attr( $o->name ) . '"' . ( in_array( $o->name, $arr, true ) ? ' selected' : '' ) . '>' . esc_html( $o->name ) . '</option>';
                }
                echo '</select>';
                break;
            case 'radio':
                $opts = $this->get_field_options( $field->id );
                echo '<div class="bbppp-radio-group">';
                foreach ( $opts as $o ) {
                    $oid = $id . '_' . sanitize_html_class( $o->name );
                    echo '<label class="bbppp-radio"><input type="radio" name="' . esc_attr( $key ) . '" id="' . esc_attr( $oid ) . '" value="' . esc_attr( $o->name ) . '"' . checked( $value, $o->name, false ) . $req . '> ' . esc_html( $o->name ) . '</label>';
                }
                echo '</div>';
                break;
            case 'checkbox':
                $opts = $this->get_field_options( $field->id );
                $arr  = is_array( $value ) ? $value : (array) maybe_unserialize( $value );
                echo '<div class="bbppp-checkbox-group">';
                foreach ( $opts as $o ) {
                    $oid = $id . '_' . sanitize_html_class( $o->name );
                    echo '<label class="bbppp-checkbox"><input type="checkbox" name="' . esc_attr( $key ) . '[]" id="' . esc_attr( $oid ) . '" value="' . esc_attr( $o->name ) . '"' . ( in_array( $o->name, $arr, true ) ? ' checked' : '' ) . '> ' . esc_html( $o->name ) . '</label>';
                }
                echo '</div>';
                break;
            case 'datebox':
                echo '<input type="date" name="' . esc_attr( $key ) . '" id="' . esc_attr( $id ) . '" value="' . esc_attr( $value ) . '"' . $req . '>';
                break;
            case 'url':
                echo '<input type="url" name="' . esc_attr( $key ) . '" id="' . esc_attr( $id ) . '" value="' . esc_attr( $value ) . '"' . $req . '>';
                break;
            default:
                echo '<input type="text" name="' . esc_attr( $key ) . '" id="' . esc_attr( $id ) . '" value="' . esc_attr( $value ) . '"' . $req . '>';
        }
        echo '</div>';
    }
    public function sanitize_value( $raw, $type ) {
        switch ( $type ) {
            case 'textarea': return sanitize_textarea_field( $raw );
            case 'url':      return esc_url_raw( $raw );
            case 'checkbox': case 'multiselectbox': return array_map( 'sanitize_text_field', (array) $raw );
            default:         return sanitize_text_field( $raw );
        }
    }
  public function get_field_by_id( $field_id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bp_xprofile_fields WHERE id = %d LIMIT 1", $field_id ) );
  }
}
