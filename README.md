# WP Register Bulk Edit

A lightweight library for registering custom bulk edit fields on WordPress post list tables.

## Features

- Simple API for registering bulk edit fields
- Supports posts and custom post types
- Automatic sanitization based on field type
- Permission checking via capabilities
- Multiple field types: text, textarea, number, select, checkbox, url, email
- Callable options for dynamic select values
- No dependencies

## Installation

Install via Composer:

```bash
composer require arraypress/wp-register-bulk-edit
```

## Usage

### Basic Example

```php
register_bulk_edit_fields( 'download', [
    'tax_class' => [
        'label'        => __( 'Tax Class', 'my-plugin' ),
        'type'         => 'select',
        'options'      => [
            ''  => __( '— Use Default —', 'my-plugin' ),
            '1' => __( 'Reduced Rate', 'my-plugin' ),
            '2' => __( 'Zero Rate', 'my-plugin' ),
        ],
        'meta_key'     => '_tax_class_id',
        'clear_option' => true,
    ],
    'sale_percent' => [
        'label'    => __( 'Sale Discount %', 'my-plugin' ),
        'type'     => 'number',
        'meta_key' => '_sale_percent',
        'min'      => 0,
        'max'      => 100,
        'step'     => 1,
    ],
]);
```

### Field Configuration Options

| Option              | Type             | Default        | Description                               |
|---------------------|------------------|----------------|-------------------------------------------|
| `label`             | string           | `''`           | Field label displayed in the UI           |
| `type`              | string           | `'text'`       | Field type (see below)                    |
| `description`       | string           | `''`           | Help text displayed below the field       |
| `options`           | array\|callable  | `[]`           | Options for select fields                 |
| `meta_key`          | string           | Field key      | The meta key to save to                   |
| `min`               | int\|float\|null | `null`         | Minimum value for number fields           |
| `max`               | int\|float\|null | `null`         | Maximum value for number fields           |
| `step`              | int\|float\|null | `null`         | Step value for number fields              |
| `sanitize_callback` | callable\|null   | `null`         | Custom sanitization callback              |
| `capability`        | string           | `'edit_posts'` | Required capability to see/edit field     |
| `no_change`         | bool             | `true`         | Show "— No Change —" option               |
| `clear_option`      | bool             | `false`        | Show "— Clear —" option for select fields |
| `attrs`             | array            | `[]`           | Additional HTML attributes                |

### Supported Field Types

| Type       | Description         | Auto-Sanitization                        |
|------------|---------------------|------------------------------------------|
| `text`     | Standard text input | `sanitize_text_field()`                  |
| `textarea` | Multi-line text     | `sanitize_textarea_field()`              |
| `number`   | Numeric input       | `intval()` or `floatval()` based on step |
| `select`   | Dropdown select     | Validates against options                |
| `checkbox` | Boolean toggle      | Cast to 0 or 1                           |
| `url`      | URL input           | `esc_url_raw()`                          |
| `email`    | Email input         | `sanitize_email()`                       |

### Dynamic Options

Use a callable to generate options dynamically:

```php
register_bulk_edit_fields( 'product', [
    'category' => [
        'label'   => __( 'Category', 'my-plugin' ),
        'type'    => 'select',
        'options' => function() {
            $categories = get_terms( [
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
            ] );
            
            $options = [ '' => __( '— Select —', 'my-plugin' ) ];
            foreach ( $categories as $cat ) {
                $options[ $cat->term_id ] = $cat->name;
            }
            
            return $options;
        },
        'meta_key' => '_product_category',
    ],
]);
```

### Multiple Post Types

```php
register_bulk_edit_fields( [ 'post', 'page', 'product' ], [
    'featured' => [
        'label'    => __( 'Featured', 'my-plugin' ),
        'type'     => 'checkbox',
        'meta_key' => '_is_featured',
    ],
]);
```

### Custom Sanitization

```php
register_bulk_edit_fields( 'product', [
    'price' => [
        'label'             => __( 'Price', 'my-plugin' ),
        'type'              => 'number',
        'meta_key'          => '_price',
        'step'              => '0.01',
        'sanitize_callback' => function( $value ) {
            return round( floatval( $value ), 2 );
        },
    ],
]);
```

## Requirements

- PHP 7.4 or later
- WordPress 5.0 or later

## License

GPL-2.0-or-later
