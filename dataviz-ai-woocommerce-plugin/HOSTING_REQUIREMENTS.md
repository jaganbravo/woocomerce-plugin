# WooCommerce Plugin Hosting Requirements

This document outlines the requirements for hosting your Dataviz AI for WooCommerce plugin on various platforms.

## Table of Contents
1. [WooCommerce.com Marketplace](#woocommercecom-marketplace)
2. [WordPress.org Repository (Alternative)](#wordpressorg-repository)
3. [Self-Hosting Requirements](#self-hosting-requirements)
4. [Current Plugin Status](#current-plugin-status)
5. [Checklist](#checklist)

---

## WooCommerce.com Marketplace

### Technical Requirements

#### PHP Version
- ✅ **Minimum**: PHP 8.1+ (recommended: PHP 8.3+)
- Current plugin header specifies PHP 7.4 (needs update to 8.1+)

#### WordPress & WooCommerce Compatibility
- ✅ **WordPress**: Latest stable version (currently 6.7+)
- ✅ **WooCommerce**: Must support latest WooCommerce version (currently 8.5+)
- Current plugin header is compatible

#### Code Quality Standards
- ✅ Follow WordPress Coding Standards (WPCS)
- ✅ Follow WooCommerce Coding Standards
- ✅ No deprecated functions or features
- ✅ Proper error handling
- ✅ Security best practices (nonces, sanitization, escaping)

### User Experience Requirements

#### UI/UX Guidelines
- ✅ Use WordPress and WooCommerce UI components
- ✅ Consistent with WordPress admin design
- ✅ Mobile responsive interface
- ❌ Minimal branding (no logos/promotional content in plugin UI)
- ✅ Accessible (WCAG 2.1 AA compliance)

#### Performance
- ✅ Optimized code (minimal database queries)
- ✅ Proper script/style enqueuing
- ✅ No conflicts with other plugins
- ✅ Fast page load times

### Documentation Requirements

#### Required Files
- ✅ **README.md**: Plugin description, installation, usage
- ✅ **CHANGELOG.md** or **changelog.txt**: Version history
- ✅ **README.txt**: WordPress.org format (if submitting there)
- ⚠️ Need to create proper changelog file

#### Code Documentation
- ✅ PHPDoc comments for all functions and classes
- ✅ Inline comments for complex logic
- ✅ Hook and filter documentation

### Security Requirements

#### Must Have
- ✅ Input sanitization (`sanitize_text_field()`, `sanitize_email()`, etc.)
- ✅ Output escaping (`esc_html()`, `esc_attr()`, `esc_url()`, etc.)
- ✅ Nonce verification for all forms
- ✅ Capability checks (`current_user_can()`)
- ✅ Prepared SQL statements (if using custom queries)
- ✅ CSRF protection

#### Security Audit
- ✅ No hardcoded credentials
- ✅ Secure API key storage
- ✅ Proper permission handling

### Business Requirements

#### Review Process
1. **Business Review**: Market fit, business model assessment
2. **Code Review**: Original code, security, standards compliance
3. **UX Review**: User experience evaluation
4. **Launch Preparation**: Final approval and listing

#### Support Requirements
- ✅ Support channel (email, forum, etc.)
- ✅ Response time commitment
- ✅ Documentation availability

---

## WordPress.org Repository (Alternative)

If you prefer the free WordPress.org plugin repository:

### Requirements
- ✅ **License**: Must be GPL v2 or later (your plugin is GPL-2.0-or-later ✅)
- ✅ **README.txt**: WordPress.org format readme
- ✅ **SVN Access**: Subversion repository (WordPress provides this)
- ✅ **Free/Open Source**: Plugin must be free and open source
- ✅ **No Commercial Links**: Limited promotional content

### WordPress.org-Specific Files Needed
- `readme.txt` (WordPress.org format)
- `.pot` file for translations
- Screenshots (at least 1, up to 5)

---

## Self-Hosting Requirements

If hosting on your own website:

### Technical Setup
- ✅ Web server (Apache/Nginx)
- ✅ PHP 8.1+ with required extensions
- ✅ MySQL/MariaDB database
- ✅ WordPress 6.0+ installed
- ✅ WooCommerce 6.0+ installed

### Distribution
- ✅ Plugin ZIP file for download
- ✅ Installation instructions
- ✅ Update mechanism (if providing auto-updates)

---

## Current Plugin Status

### ✅ Already Implemented
- ✅ Proper plugin header with WooCommerce compatibility tags
- ✅ WooCommerce dependency checking
- ✅ Activation/deactivation hooks
- ✅ Security practices (sanitization, nonces, capabilities)
- ✅ Internationalization (i18n) ready
- ✅ Proper file structure
- ✅ README.md file
- ✅ GPL v2 license compatible

### ⚠️ Needs Updates
- ⚠️ **PHP Version**: Update from 7.4 to 8.1+ in plugin header
- ⚠️ **CHANGELOG.md**: Create changelog file
- ⚠️ **README.txt**: Create WordPress.org format readme (if submitting there)
- ⚠️ **Code Documentation**: Add more PHPDoc comments
- ⚠️ **Plugin URI**: Update from "https://example.com" to actual URL
- ⚠️ **Author Information**: Update from "Your Name" to actual author

### ❌ Missing/To Do
- ❌ **Screenshots**: Create plugin screenshots
- ❌ **Translation .pot file**: Generate for i18n
- ❌ **Testing**: Comprehensive testing across WP/WC versions
- ❌ **Accessibility audit**: WCAG compliance check
- ❌ **Performance testing**: Load time optimization
- ❌ **Browser compatibility**: Cross-browser testing

---

## Checklist

### Pre-Submission Checklist

#### Code Quality
- [ ] Update PHP requirement to 8.1+ in plugin header
- [ ] Update Plugin URI to actual website
- [ ] Update Author information
- [ ] Run PHPCS (WordPress Coding Standards)
- [ ] Fix all PHP 8.1+ deprecation warnings
- [ ] Remove all debug code and console.logs
- [ ] Minify CSS/JS files (or provide minified versions)

#### Documentation
- [ ] Create/update README.md with complete information
- [ ] Create CHANGELOG.md with version history
- [ ] Create readme.txt (WordPress.org format, if applicable)
- [ ] Document all hooks and filters provided by plugin
- [ ] Add installation instructions
- [ ] Add troubleshooting section

#### Testing
- [ ] Test with WordPress 6.0, 6.5, 6.7+
- [ ] Test with WooCommerce 6.0, 7.0, 8.0, 8.5+
- [ ] Test with PHP 8.1, 8.2, 8.3
- [ ] Test with popular themes (Storefront, Twenty Twenty-Four, etc.)
- [ ] Test with popular plugins (Yoast, WP Rocket, etc.)
- [ ] Test plugin activation/deactivation
- [ ] Test plugin uninstall
- [ ] Cross-browser testing (Chrome, Firefox, Safari, Edge)
- [ ] Mobile device testing

#### Security
- [ ] Security audit (use tools like WPScan, WordPress Security Checklist)
- [ ] Verify all inputs are sanitized
- [ ] Verify all outputs are escaped
- [ ] Verify all forms have nonce verification
- [ ] Verify all AJAX endpoints check capabilities
- [ ] Check for SQL injection vulnerabilities
- [ ] Check for XSS vulnerabilities

#### Accessibility
- [ ] WCAG 2.1 AA compliance audit
- [ ] Keyboard navigation testing
- [ ] Screen reader compatibility
- [ ] Color contrast verification
- [ ] Alt text for images

#### Marketing Assets
- [ ] Plugin screenshots (1280x720px, at least 1)
- [ ] Plugin icon (256x256px, optional)
- [ ] Banner image (772x250px, for WordPress.org)
- [ ] Description for marketplace listing

#### Support
- [ ] Set up support channel (email/forum)
- [ ] Create support documentation
- [ ] Prepare FAQ document

### Submission Process (WooCommerce.com)

1. **Register for WooCommerce Developer Account**
   - Sign up at WooCommerce.com
   - Complete developer profile

2. **Prepare Submission Package**
   - Clean plugin code
   - All required documentation
   - Marketing assets

3. **Submit for Review**
   - Upload plugin package
   - Provide business information
   - Wait for review (typically 2-4 weeks)

4. **Address Review Feedback**
   - Fix any code issues
   - Update documentation
   - Resubmit if needed

5. **Launch**
   - Final approval
   - Plugin goes live on marketplace
   - Monitor reviews and support requests

---

## Quick Fixes Needed

### 1. Update Plugin Header

Update `dataviz-ai-woocommerce.php`:

```php
/**
 * Plugin Name: Dataviz AI for WooCommerce
 * Plugin URI: https://yourwebsite.com/dataviz-ai-woocommerce  // UPDATE THIS
 * Description: AI-powered analytics and insights plugin for WooCommerce stores.
 * Version: 1.0.0  // UPDATE VERSION
 * Author: Your Name  // UPDATE THIS
 * Author URI: https://yourwebsite.com  // UPDATE THIS
 * Text Domain: dataviz-ai-woocommerce
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1  // UPDATE FROM 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.5
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */
```

### 2. Create CHANGELOG.md

```markdown
# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - YYYY-MM-DD
### Added
- Initial release
- AI-powered analytics dashboard
- FAQ system
- Chat widget
```

### 3. Generate .pot File

Run in WordPress root:
```bash
wp i18n make-pot dataviz-ai-woocommerce-plugin languages/dataviz-ai-woocommerce.pot
```

---

## Resources

- [WooCommerce Marketplace Submission Guide](https://woocommerce.com/document/submitting-your-product-to-the-woo-marketplace/)
- [WooCommerce UX Guidelines](https://woo.zendesk.com/hc/en-us/articles/15664015652244-Woo-Marketplace-User-Experience-Guidelines)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- [WCAG 2.1 Guidelines](https://www.w3.org/WAI/WCAG21/quickref/)

---

## Notes

- The plugin is currently in development (version 0.1.0)
- Update to version 1.0.0 before submission
- Consider beta testing with a small user group before public release
- Monitor WooCommerce and WordPress updates for compatibility

