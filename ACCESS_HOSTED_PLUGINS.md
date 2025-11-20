# How to Access Old/Unused Plugins from Hosted WooCommerce Site

## Overview

If you have plugins installed on a hosted WordPress/WooCommerce site that you want to access locally, here are several methods to retrieve them.

---

## Method 1: Via FTP/SFTP (Most Common)

### Step 1: Get FTP Credentials
- Check your hosting control panel (cPanel, Plesk, etc.)
- Look for FTP/SFTP account information
- Or check your hosting provider's documentation

### Step 2: Connect via FTP Client
```bash
# Using command line (sftp)
sftp username@your-domain.com
# Enter password when prompted
cd public_html/wp-content/plugins
ls -la  # List all plugins
```

### Step 3: Download Plugins
```bash
# Download specific plugin
get -r plugin-name/

# Or download all plugins
get -r .
```

### Using FTP Client Software
- **FileZilla** (Free, cross-platform)
- **Cyberduck** (Free, macOS/Windows)
- **WinSCP** (Windows)

**Connection details:**
- **Host:** your-domain.com or FTP server IP
- **Username:** Your FTP username
- **Password:** Your FTP password
- **Port:** 21 (FTP) or 22 (SFTP)
- **Path:** `/public_html/wp-content/plugins` (or similar)

---

## Method 2: Via WP-CLI (If SSH Access Available)

### Step 1: SSH into Your Server
```bash
ssh username@your-domain.com
```

### Step 2: Navigate to WordPress Directory
```bash
cd /path/to/wordpress/wp-content/plugins
ls -la  # List all plugins
```

### Step 3: Download Plugin
```bash
# Create a zip of the plugin
zip -r plugin-name.zip plugin-name/

# Or use WP-CLI to export
wp plugin get plugin-name --format=json
```

### Step 4: Transfer to Local Machine
```bash
# Using SCP from your local machine
scp username@your-domain.com:/path/to/wordpress/wp-content/plugins/plugin-name.zip ./
```

---

## Method 3: Via WordPress Admin (Download Plugin Files)

### Step 1: Access WordPress Admin
- Login to your hosted WordPress admin panel
- Go to **Plugins → Installed Plugins**

### Step 2: Use File Manager in Hosting Control Panel
- Access your hosting control panel (cPanel, etc.)
- Open **File Manager**
- Navigate to: `public_html/wp-content/plugins/`
- Select plugin folder → **Compress** → Download ZIP

---

## Method 4: Via WP-CLI Export (Recommended)

If you have SSH access, you can export plugins directly:

```bash
# SSH into your server
ssh username@your-domain.com

# List all plugins
wp plugin list --allow-root

# Export specific plugin
wp plugin get plugin-name --format=json > plugin-name.json

# Or download plugin folder
cd wp-content/plugins
tar -czf plugin-name.tar.gz plugin-name/
```

Then download the file to your local machine.

---

## Method 5: Via Database Backup (If Plugin Stores Code in DB)

Some plugins store code in the database. You can export:

```bash
# Export WordPress database
wp db export backup.sql --allow-root

# Or via phpMyAdmin
# Export wp_options table where option_name like '%plugin%'
```

---

## Method 6: Clone from Git Repository (If Available)

If your plugins are in a Git repository:

```bash
# Clone the repository
git clone https://github.com/your-username/your-plugin-repo.git

# Or if it's a private repo
git clone https://your-token@github.com/your-username/your-plugin-repo.git
```

---

## Method 7: Using WordPress Plugin Installer API

If the plugin is available on WordPress.org:

```bash
# Download from WordPress.org
wp plugin install plugin-slug --force --allow-root

# Or download ZIP
curl -L https://downloads.wordpress.org/plugin/plugin-slug.zip -o plugin.zip
```

---

## Step-by-Step: Download Plugin to Local Docker Setup

Once you have the plugin files, add them to your local setup:

### Option A: Copy to Docker WordPress
```bash
# Copy plugin to Docker WordPress
cp -R downloaded-plugin /Users/jaganbravo/woocomerce-plugin/docker/wordpress/wp-content/plugins/

# Activate via WP-CLI
cd /Users/jaganbravo/woocomerce-plugin/docker
docker compose exec wpcli wp plugin activate plugin-name --allow-root
```

### Option B: Add to Your Plugin Development
```bash
# Copy to your plugin directory
cp -R downloaded-plugin /Users/jaganbravo/woocomerce-plugin/
```

---

## Finding Plugin Locations

### Standard WordPress Plugin Paths
- **Standard:** `/wp-content/plugins/`
- **Multisite:** `/wp-content/plugins/` (network-wide)
- **Custom:** Check `WP_PLUGIN_DIR` constant in `wp-config.php`

### Check Active Plugins
```bash
# Via WP-CLI
wp plugin list --status=active --allow-root

# Via database
SELECT option_value FROM wp_options WHERE option_name = 'active_plugins';
```

---

## Important Notes

1. **Backup First:** Always backup before downloading
2. **Permissions:** Ensure you have read access to plugin files
3. **Dependencies:** Some plugins may have dependencies that need to be included
4. **Database Data:** Plugin settings are stored in `wp_options` table
5. **Custom Tables:** Some plugins create custom tables - export those too if needed

---

## Quick Checklist

- [ ] Get FTP/SFTP credentials from hosting provider
- [ ] Connect to server via FTP client or SSH
- [ ] Navigate to `/wp-content/plugins/`
- [ ] Download plugin folder(s)
- [ ] Copy to local Docker setup
- [ ] Activate and test locally

---

## Troubleshooting

### Can't Access FTP
- Check firewall settings
- Verify credentials
- Try SFTP instead of FTP
- Contact hosting support

### Plugin Not Working After Download
- Check for missing dependencies
- Verify database options are exported
- Check for custom database tables
- Review plugin requirements (PHP version, WordPress version)

### Permission Issues
```bash
# Fix permissions after download
chmod -R 755 plugin-name/
chown -R www-data:www-data plugin-name/
```

---

**Need Help?** If you can share:
- Your hosting provider
- Access method available (FTP, SSH, cPanel)
- Plugin names you're looking for

I can provide more specific instructions!

