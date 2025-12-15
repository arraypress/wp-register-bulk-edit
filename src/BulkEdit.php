<?php
/**
 * Bulk Edit Class
 *
 * A lightweight class for registering custom bulk edit fields on WordPress
 * post list tables. Provides a simple API for common field types with
 * automatic saving and sanitization.
 *
 * @package     ArrayPress\WP\RegisterBulkEdit
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterBulkEdit;

use Exception;

/**
 * Class BulkEdit
 *
 * Manages custom bulk edit field registration for post list tables.
 *
 * @package ArrayPress\RegisterBulkEdit
 */
class BulkEdit {

    /**
     * The post type this instance is registered for.
     *
     * @var string
     */
    protected string $post_type;

    /**
     * Unique identifier for this field group.
     *
     * @var string
     */
    protected string $group_id;

    /**
     * Registered bulk edit fields storage.
     *
     * @var array<string, array<string, mixed>>
     */
    protected static array $fields = [];

    /**
     * Supported field types.
     *
     * @var array<string>
     */
    protected static array $field_types = [
            'text',
            'textarea',
            'number',
            'select',
            'checkbox',
            'url',
            'email',
    ];

    /**
     * BulkEdit constructor.
     *
     * Initializes the bulk edit field registration.
     *
     * @param array  $fields    Array of field configurations keyed by field key.
     * @param string $post_type The post type to register fields for.
     *
     * @throws Exception If a field key is invalid or field type is unsupported.
     */
    public function __construct( array $fields, string $post_type ) {
        $this->post_type = $post_type;
        $this->group_id  = 'bulk_edit_' . $post_type;

        $this->add_fields( $fields );

        // Load hooks immediately if already in admin, otherwise wait
        if ( did_action( 'admin_init' ) ) {
            $this->load_hooks();
        } else {
            add_action( 'admin_init', [ $this, 'load_hooks' ] );
        }
    }

    /**
     * Add fields to the configuration.
     *
     * Parses and validates field configurations, merging with defaults.
     *
     * @param array $fields Array of field configurations keyed by field key.
     *
     * @return void
     * @throws Exception If a field key is invalid or field type is unsupported.
     */
    protected function add_fields( array $fields ): void {
        $defaults = [
                'label'             => '',
                'type'              => 'text',
                'description'       => '',
                'options'           => [],
                'meta_key'          => '',
                'min'               => null,
                'max'               => null,
                'step'              => null,
                'sanitize_callback' => null,
                'capability'        => 'edit_posts',
                'no_change'         => true,
                'clear_option'      => false,
                'attrs'             => [],
        ];

        foreach ( $fields as $key => $field ) {
            if ( ! is_string( $key ) || empty( $key ) ) {
                throw new Exception( 'Invalid field key provided. It must be a non-empty string.' );
            }

            // Validate field type
            $type = $field['type'] ?? 'text';
            if ( ! in_array( $type, self::$field_types, true ) ) {
                throw new Exception( sprintf( 'Invalid field type "%s" for field "%s".', $type, $key ) );
            }

            // Auto-set meta_key if not provided
            if ( empty( $field['meta_key'] ) ) {
                $field['meta_key'] = $key;
            }

            self::$fields[ $this->group_id ][ $key ] = wp_parse_args( $field, $defaults );
        }
    }

    /**
     * Get all registered fields for this group.
     *
     * @return array Array of field configurations.
     */
    public function get_fields(): array {
        return self::$fields[ $this->group_id ] ?? [];
    }

    /**
     * Get all registered fields across all groups.
     *
     * @return array Array of all field configurations.
     */
    public static function get_all_fields(): array {
        return self::$fields;
    }

    /**
     * Get a specific field configuration by key.
     *
     * @param string $key The field key.
     *
     * @return array|null The field configuration or null if not found.
     */
    public function get_field( string $key ): ?array {
        return self::$fields[ $this->group_id ][ $key ] ?? null;
    }

    /**
     * Load WordPress hooks.
     *
     * Registers actions for rendering fields and saving data.
     *
     * @return void
     */
    public function load_hooks(): void {
        add_action( 'bulk_edit_custom_box', [ $this, 'render_fields' ], 10, 2 );
        add_action( 'save_post_' . $this->post_type, [ $this, 'save_fields' ] );
    }

    /**
     * Render fields in the bulk edit form.
     *
     * @param string $column_name The column name being rendered.
     * @param string $post_type   The post type.
     *
     * @return void
     */
    public function render_fields( string $column_name, string $post_type ): void {
        if ( $post_type !== $this->post_type ) {
            return;
        }

        $fields = $this->get_fields();

        foreach ( $fields as $key => $field ) {
            if ( $column_name !== $key ) {
                continue;
            }

            if ( ! $this->check_permission( $field ) ) {
                continue;
            }

            $this->render_field( $key, $field );
        }
    }

    /**
     * Render a single field in the bulk edit form.
     *
     * @param string $key   The field key.
     * @param array  $field The field configuration.
     *
     * @return void
     */
    protected function render_field( string $key, array $field ): void {
        $field_name = '_bulk_edit_' . esc_attr( $key );
        $options    = $this->get_select_options( $field );
        ?>
        <fieldset class="inline-edit-col-right">
            <div class="inline-edit-col">
                <label>
                    <span class="title"><?php echo esc_html( $field['label'] ); ?></span>
                    <?php $this->render_input( $field_name, $field, $options ); ?>
                </label>
                <?php if ( ! empty( $field['description'] ) ) : ?>
                    <p class="description"><?php echo esc_html( $field['description'] ); ?></p>
                <?php endif; ?>
            </div>
        </fieldset>
        <?php
    }

    /**
     * Render the appropriate input element based on field type.
     *
     * @param string $name    The field name attribute.
     * @param array  $field   The field configuration.
     * @param array  $options The select options if applicable.
     *
     * @return void
     */
    protected function render_input( string $name, array $field, array $options ): void {
        $attrs = $this->build_attrs( $field );

        switch ( $field['type'] ) {
            case 'select':
                $this->render_select( $name, $field, $options );
                break;

            case 'checkbox':
                $this->render_checkbox( $name, $field );
                break;

            case 'number':
                $this->render_number( $name, $field, $attrs );
                break;

            case 'textarea':
                $this->render_textarea( $name, $field, $attrs );
                break;

            case 'url':
            case 'email':
            case 'text':
            default:
                $this->render_text( $name, $field, $attrs );
                break;
        }
    }

    /**
     * Build HTML attributes string from field configuration.
     *
     * @param array $field The field configuration.
     *
     * @return string The HTML attributes string.
     */
    protected function build_attrs( array $field ): string {
        $attrs_array = $field['attrs'] ?? [];

        // Add min/max/step for number fields
        if ( $field['type'] === 'number' ) {
            if ( isset( $field['min'] ) ) {
                $attrs_array['min'] = $field['min'];
            }
            if ( isset( $field['max'] ) ) {
                $attrs_array['max'] = $field['max'];
            }
            if ( isset( $field['step'] ) ) {
                $attrs_array['step'] = $field['step'];
            }
        }

        $attrs = '';
        foreach ( $attrs_array as $attr => $value ) {
            $attrs .= sprintf( ' %s="%s"', esc_attr( $attr ), esc_attr( $value ) );
        }

        return $attrs;
    }

    /**
     * Render a text input field.
     *
     * @param string $name  The field name attribute.
     * @param array  $field The field configuration.
     * @param string $attrs Additional HTML attributes.
     *
     * @return void
     */
    protected function render_text( string $name, array $field, string $attrs ): void {
        $type = in_array( $field['type'], [ 'url', 'email' ], true ) ? $field['type'] : 'text';
        ?>
        <input type="<?php echo esc_attr( $type ); ?>"
               name="<?php echo esc_attr( $name ); ?>"
               value=""
               class="regular-text"<?php echo $attrs; ?>>
        <?php if ( $field['no_change'] ) : ?>
            <span class="description"><?php esc_html_e( 'Leave empty for no change', 'arraypress' ); ?></span>
        <?php endif; ?>
        <?php
    }

    /**
     * Render a number input field.
     *
     * @param string $name  The field name attribute.
     * @param array  $field The field configuration.
     * @param string $attrs Additional HTML attributes.
     *
     * @return void
     */
    protected function render_number( string $name, array $field, string $attrs ): void {
        ?>
        <input type="number"
               name="<?php echo esc_attr( $name ); ?>"
               value=""
               class="small-text"<?php echo $attrs; ?>>
        <?php if ( $field['no_change'] ) : ?>
            <span class="description"><?php esc_html_e( 'Leave empty for no change', 'arraypress' ); ?></span>
        <?php endif; ?>
        <?php
    }

    /**
     * Render a textarea field.
     *
     * @param string $name  The field name attribute.
     * @param array  $field The field configuration.
     * @param string $attrs Additional HTML attributes.
     *
     * @return void
     */
    protected function render_textarea( string $name, array $field, string $attrs ): void {
        ?>
        <textarea name="<?php echo esc_attr( $name ); ?>"
                  rows="3"
                  class="regular-text"<?php echo $attrs; ?>></textarea>
        <?php if ( $field['no_change'] ) : ?>
            <span class="description"><?php esc_html_e( 'Leave empty for no change', 'arraypress' ); ?></span>
        <?php endif; ?>
        <?php
    }

    /**
     * Render a select dropdown field.
     *
     * @param string $name    The field name attribute.
     * @param array  $field   The field configuration.
     * @param array  $options The select options.
     *
     * @return void
     */
    protected function render_select( string $name, array $field, array $options ): void {
        ?>
        <select name="<?php echo esc_attr( $name ); ?>">
            <?php if ( $field['no_change'] ) : ?>
                <option value="__no_change__"><?php esc_html_e( '— No Change —', 'arraypress' ); ?></option>
            <?php endif; ?>
            <?php if ( $field['clear_option'] ) : ?>
                <option value="__clear__"><?php esc_html_e( '— Clear —', 'arraypress' ); ?></option>
            <?php endif; ?>
            <?php foreach ( $options as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>">
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Render a checkbox field as a select dropdown.
     *
     * Checkboxes are rendered as selects in bulk edit to support "no change" option.
     *
     * @param string $name  The field name attribute.
     * @param array  $field The field configuration.
     *
     * @return void
     */
    protected function render_checkbox( string $name, array $field ): void {
        ?>
        <select name="<?php echo esc_attr( $name ); ?>">
            <option value="__no_change__"><?php esc_html_e( '— No Change —', 'arraypress' ); ?></option>
            <option value="1"><?php esc_html_e( 'Yes', 'arraypress' ); ?></option>
            <option value="0"><?php esc_html_e( 'No', 'arraypress' ); ?></option>
        </select>
        <?php
    }

    /**
     * Get options for a select field.
     *
     * Handles both static arrays and callable options.
     *
     * @param array $field The field configuration.
     *
     * @return array Array of options as value => label pairs.
     */
    protected function get_select_options( array $field ): array {
        $options = $field['options'] ?? [];

        if ( is_callable( $options ) ) {
            $options = call_user_func( $options );
        }

        return is_array( $options ) ? $options : [];
    }

    /**
     * Save field values when posts are bulk edited.
     *
     * @param int $post_id The post ID being saved.
     *
     * @return void
     */
    public function save_fields( int $post_id ): void {
        // Check if this is a bulk edit
        if ( ! isset( $_REQUEST['bulk_edit'] ) && ! isset( $_REQUEST['_inline_edit'] ) ) {
            return;
        }

        // Verify the post type
        if ( get_post_type( $post_id ) !== $this->post_type ) {
            return;
        }

        // Check permission
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $fields = $this->get_fields();

        foreach ( $fields as $key => $field ) {
            if ( ! $this->check_permission( $field ) ) {
                continue;
            }

            $field_name = '_bulk_edit_' . $key;

            if ( ! isset( $_REQUEST[ $field_name ] ) ) {
                continue;
            }

            $value = $_REQUEST[ $field_name ];

            // Skip no change
            if ( $value === '__no_change__' ) {
                continue;
            }

            // For text/number fields, empty string means no change
            if ( in_array( $field['type'], [ 'text', 'textarea', 'number', 'url', 'email' ], true ) && $value === '' ) {
                continue;
            }

            // Handle clear
            if ( $value === '__clear__' ) {
                delete_post_meta( $post_id, $field['meta_key'] );
                continue;
            }

            // Sanitize the value
            $value = $this->sanitize_value( $value, $field );

            // Save or delete based on value
            if ( $value === '' || $value === null ) {
                delete_post_meta( $post_id, $field['meta_key'] );
            } else {
                update_post_meta( $post_id, $field['meta_key'], $value );
            }
        }
    }

    /**
     * Sanitize a field value based on its type and configuration.
     *
     * @param mixed $value The raw value to sanitize.
     * @param array $field The field configuration.
     *
     * @return mixed The sanitized value.
     */
    protected function sanitize_value( $value, array $field ) {
        // Use custom sanitize callback if provided
        if ( is_callable( $field['sanitize_callback'] ) ) {
            return call_user_func( $field['sanitize_callback'], $value );
        }

        $type = $field['type'];

        switch ( $type ) {
            case 'checkbox':
                return $value ? 1 : 0;

            case 'number':
                // Use floatval if step allows decimals, otherwise intval
                $step = $field['step'] ?? 1;
                if ( is_numeric( $step ) && floor( (float) $step ) != (float) $step ) {
                    $value = floatval( $value );
                } else {
                    $value = intval( $value );
                }

                // Apply min/max constraints
                if ( isset( $field['min'] ) && $value < $field['min'] ) {
                    $value = $field['min'];
                }
                if ( isset( $field['max'] ) && $value > $field['max'] ) {
                    $value = $field['max'];
                }

                return $value;

            case 'select':
                // Validate against options
                $options = $this->get_select_options( $field );
                if ( ! array_key_exists( $value, $options ) ) {
                    return null;
                }

                return $value;

            case 'url':
                return esc_url_raw( $value );

            case 'email':
                return sanitize_email( $value );

            case 'textarea':
                return sanitize_textarea_field( $value );

            case 'text':
            default:
                return sanitize_text_field( $value );
        }
    }

    /**
     * Check if the current user has permission to edit the field.
     *
     * @param array $field The field configuration.
     *
     * @return bool True if user has permission, false otherwise.
     */
    protected function check_permission( array $field ): bool {
        return current_user_can( $field['capability'] );
    }

}
