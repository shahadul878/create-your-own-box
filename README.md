# Create Box Builder

Create Box Builder is a WooCommerce companion plugin that lets you curate a “build your own box” shopping experience. Merchants can flag individual products as eligible box items, curate complementary catalog sections, and present customers with a guided flow that enforces bundle rules before checkout.

## Requirements

- WordPress 6.0 or later  
- WooCommerce 7.0 or later  
- PHP 7.4+

## Key Features

- Mark any WooCommerce product as a box candidate through a simple checkbox.
- Configure catalog sections that surface related add-ons beside the box selector.
- Enforce bundle rules such as minimum item count, minimum spend, and box requirement.
- Auto-render the builder on a chosen page without relying on shortcodes.
- REST API endpoint that assembles the cart with a single request and respects store redirects.

## Installation

1. Copy the `create-box` plugin directory into `wp-content/plugins/`.
2. Activate **Create Box Builder** from the WordPress admin Plugins screen.
3. Under **WooCommerce → Create Box**, configure:
   - Builder Page (page ID or slug the experience should appear on)
   - Content Sections (one per line in the format `Label | term-slug`)
   - Bundle Rules (minimum items, minimum total, require box, redirect)

## Usage

1. Edit a WooCommerce product and check **Mark as Create Box product** to include it in the box selector.
2. Add products to the configured content section categories; they will appear below the box options.
3. Visit the configured builder page to see the front-end experience, complete with validation and summary panel.

## REST Endpoints

| Method | Endpoint                | Description                |
| ------ | ----------------------- | -------------------------- |
| GET    | `/wp-json/create-box/v1/catalog` | Fetches configured payload |
| POST   | `/wp-json/create-box/v1/add`     | Validates and adds bundle to cart |

All POST requests require an authenticated `X-WP-Nonce` issued for `wp_rest`.

## Development

- JavaScript bundle lives at `assets/js/create-box.js`
- CSS styles at `assets/css/create-box.css`
- Core PHP classes in `includes/`

The plugin ships without a build step; edits to JS/CSS are referenced directly.

## Contributing

1. Fork the repository.
2. Create a feature branch: `git checkout -b feature/your-feature`.
3. Commit your changes with descriptive messages.
4. Open a pull request describing the change and testing performed.

## License

Released under the GPL v2 or later. See [LICENSE](LICENSE) if supplied, otherwise inherit WordPress.org plugin guidelines.

