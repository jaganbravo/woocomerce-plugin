# Supplementary documentation (monorepo)

**Planning and architecture** for Dataviz AI live in [`../developerDocs/`](../developerDocs/) (e.g. `ARCHITECTURE.md`, `QUICK_START.md`).

**This folder** keeps additional guides: `DATA_FLOW.md`, `PLUGIN_GUIDE.md`, `ACCESS_HOSTED_PLUGINS.md`, `ALERTS_ARCHITECTURE.md`, `SETUP_WOOCOMMERCE_STORE.md`, `DEMO_VIDEO_SCRIPT.md`, `landing-page.html`, and similar. Duplicates of files that also lived at the repository root were removed; the canonical copy is under `../developerDocs/`.

---

# WooCommerce Extension Plugin (legacy template)

The sections below described a generic WooCommerce extension starter and are left for reference.

## Features

- ✅ Proper WooCommerce dependency checking
- ✅ Custom product fields
- ✅ Custom checkout fields
- ✅ Custom cart fees
- ✅ Admin and frontend script/style enqueuing
- ✅ Internationalization ready
- ✅ Security best practices
- ✅ Plugin activation/deactivation hooks

## Installation

1. Upload the plugin files to `/wp-content/plugins/woocommerce-extension-example/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure WooCommerce is installed and activated

## Requirements

- WordPress 5.8 or higher
- PHP 8.3 or higher
- WooCommerce 5.0 or higher

## Development

### File Structure

```
woocommerce-extension-example/
├── woocommerce-extension-example.php (main plugin file)
├── includes/
│   ├── class-wce-admin.php (admin functionality)
│   └── class-wce-frontend.php (frontend functionality)
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css
│   └── js/
│       ├── admin.js
│       └── frontend.js
├── languages/ (for translations)
├── README.md
└── PLUGIN_GUIDE.md
```

### Customization

1. Rename the plugin folder and main file
2. Update plugin header in main file
3. Update constants and class names
4. Customize functionality as needed
5. Add your own hooks and filters

## License

GPL v2 or later

## Support

For support, please contact the plugin author.

