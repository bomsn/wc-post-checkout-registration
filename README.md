## One-Click Post Checkout Registration for WooCommerce

This plugin provides seamless registration option for your guest customers. When they reach the order confirmation page after checkout, they’ll be prompted to create an account with a single click, only if they don't have one already.

The plugin will automatically create a user account from the guest details, generate a password for them, logging them in. Moreover, it will trigger a 'new-account' email, and redirect the user to their dashboard. If the user already exists, the plugin will prompt them to login to link their order, or automatically link it for them based on the settings.

### How to use
- Install the plugin and activate it
- Head to WooCommerce settings from WP admin dashboard
- Open the "Accounts & Privacy" tab
- Find and check the "Post-checkout registration" option
- Save changes

### Block Usage

As of version 2.0.0, you can use the Gutenberg block for more flexible placement:

1. Edit your WooCommerce Order Received page
2. Add the "Post Checkout Registration" block
3. Configure display settings in the block sidebar
4. Save your changes

**Block Settings:**
- **Display Mode:** Control when the prompt appears (auto/always/never)
- **Quick Login Form:** Show/hide login form for existing customers

**Note:** The shortcode `[wc_pcr_message]` continues to work for backward compatibility.

## Development

### Contributing

Contributions are welcome! If you'd like to contribute to this plugin:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Run the build process: `npm run build`
5. Test your changes thoroughly
6. Submit a pull request

### Build System

This plugin uses modern WordPress development tools:

- **@wordpress/scripts** for building blocks
- **Composer** for PHP coding standards
- **npm** for JavaScript dependencies

### Development Commands

```bash
# Install dependencies
npm install
composer install

# Development mode (watch for changes)
npm start

# Build for production
npm run build

# Lint JavaScript
npm run lint:js

# Format code
npm run format

# Generate translation file
npm run make-pot

# PHP linting
composer lint

# PHP formatting
composer format
```

### Project Structure

```
wc-post-checkout-registration/
├── blocks/registration-prompt/    # Gutenberg block
│   ├── build/                     # Compiled assets
│   └── src/                       # Source files
├── includes/                      # Core PHP classes
│   ├── class-wc-post-checkout-registration.php
│   └── class-wc-pcr-blocks.php
└── languages/                     # Translation files
```

### Requirements

- Node.js 18+
- PHP 7.4+
- WordPress 6.5+
- WooCommerce 8.2+