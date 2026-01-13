# Server Hosting Requirements for Dataviz AI WooCommerce Plugin

This document outlines the minimum and recommended server hosting requirements to successfully run the Dataviz AI WooCommerce plugin in a production environment.

---

## 1. Minimum System Requirements

### Core Software
- **WordPress**: 6.0 or higher
- **WooCommerce**: 6.0 or higher
- **PHP**: 8.3 or higher (Recommended: PHP 8.3+)
- **MySQL/MariaDB**: 5.7+ or MariaDB 10.3+
- **Web Server**: Apache 2.4+ or Nginx 1.18+ (with PHP-FPM)

### PHP Configuration
- **PHP Memory Limit**: 128MB minimum (256MB recommended)
- **PHP Max Execution Time**: 30 seconds minimum (60 seconds recommended)
- **PHP Max Input Time**: 60 seconds
- **PHP Post Max Size**: 8MB minimum (16MB recommended)
- **PHP Upload Max Filesize**: 8MB minimum

### Required PHP Extensions
- ✅ **cURL** (Required for OpenAI API calls and streaming)
- ✅ **JSON** (Required for API communication)
- ✅ **MySQLi** or **PDO_MySQL** (Required for database operations)
- ✅ **OpenSSL** (Required for HTTPS requests)
- ✅ **mbstring** (Required for string handling)
- ✅ **xml** (Required by WordPress/WooCommerce)
- ✅ **zip** (Required for plugin installation/updates)

---

## 2. Network Requirements

### Outbound HTTP/HTTPS Access
The plugin requires outbound HTTPS connections to:
- **OpenAI API**: `api.openai.com` (port 443)
- **Custom Backend** (optional): Your custom API endpoint if configured

### Network Timeouts
- **API Request Timeout**: 30 seconds (configurable in code)
- **Streaming Timeout**: 60+ seconds for long responses

### Firewall Configuration
- **Allow outbound HTTPS**: Ensure the server can make outbound HTTPS requests
- **No proxy required**: Plugin uses direct HTTPS connections
- **DNS Resolution**: Must be able to resolve `api.openai.com`

### SSL/TLS Requirements
- **TLS 1.2+**: Required for secure API communication
- **Valid SSL Certificate**: Required on your site (not required for outbound calls)

---

## 3. Database Requirements

### Storage
- **Base Storage**: Standard WordPress/WooCommerce database
- **Additional Tables**: 
  - Chat history table (grows with usage)
  - Feature requests table (minimal storage)
- **Storage Estimate**: ~1-5MB per 1000 chat messages (depends on message length)

### Performance
- **Indexes**: Plugin creates indexes on timestamp columns for performance
- **Cleanup**: Automatic daily cleanup via WP-Cron (configurable retention period)

### Backup Considerations
- Include custom tables in WordPress database backups
- Chat history can be regenerated, but current conversations will be lost if not backed up

---

## 4. Server Resources

### CPU
- **Minimum**: 1 CPU core
- **Recommended**: 2+ CPU cores for better performance during API calls
- **Peak Usage**: During AI API requests and data processing

### Memory (RAM)
- **Minimum**: 512MB available to PHP
- **Recommended**: 1GB+ available to PHP
- **Peak Usage**: 
  - When processing large WooCommerce datasets (100+ orders)
  - During AI API streaming responses
  - When aggregating sales data

### Disk Space
- **Plugin Files**: ~2-5MB
- **Database Growth**: ~1-5MB per 1000 chat messages
- **Recommended**: 100MB+ free space for plugin + logs + growth

---

## 5. WordPress-Specific Requirements

### WP-Cron Support
- **Required**: WordPress Cron must be enabled and functional
- **Purpose**: Daily cleanup of old chat history
- **Alternative**: Real cron job can replace WP-Cron for better reliability:
  ```bash
  */15 * * * * wget -q -O - https://yoursite.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
  ```

### File Permissions
- **Plugin Directory**: 755 (readable, executable by web server)
- **Config File**: 644 (readable by web server, writable by owner)
- **Note**: `config.php` should be writable by the server only if using admin UI to update settings

### Security
- **HTTPS**: Highly recommended (required for production stores)
- **API Keys**: Stored in `config.php` (should be outside web root or properly secured)
- **Nonces**: All AJAX requests use WordPress nonces
- **Capability Checks**: Admin features require appropriate user capabilities

---

## 6. WooCommerce-Specific Requirements

### Data Access
- **Read Access**: Plugin reads WooCommerce data (orders, products, customers)
- **No Write Access**: Plugin does not modify WooCommerce data
- **Performance Impact**: Minimal; data fetching is optimized with limits

### Large Stores
- **Small Stores** (<1,000 orders): Direct data send (~500KB payloads)
- **Medium Stores** (1,000-100,000 orders): Smart sampling (~50KB payloads)
- **Large Stores** (100,000+ orders): Backend fetch via REST API (~1KB payloads)

---

## 7. External API Requirements

### OpenAI API
- **API Key**: Required (get from https://platform.openai.com/api-keys)
- **Endpoint**: `https://api.openai.com/v1/chat/completions`
- **Model**: `gpt-4o` (default, configurable)
- **Rate Limits**: Subject to your OpenAI account limits
- **Cost**: Pay-per-use (monitor usage in OpenAI dashboard)

### Optional Custom Backend
- **Flexibility**: Can use custom backend instead of OpenAI
- **Requirements**: RESTful API endpoint accepting JSON POST requests
- **Authentication**: Bearer token authentication
- **Response Format**: JSON response compatible with OpenAI format

---

## 8. Recommended Hosting Providers

### Shared Hosting
- ✅ **WP Engine**: Optimized for WordPress, good performance
- ✅ **SiteGround**: Good support, PHP 8.x available
- ✅ **Kinsta**: Managed WordPress hosting, excellent performance
- ⚠️ **Budget Shared Hosting**: May not meet requirements (check PHP version, cURL, memory limits)

### VPS/Cloud Hosting
- ✅ **DigitalOcean**: Full control, scalable
- ✅ **Linode**: Good performance, easy scaling
- ✅ **AWS Lightsail**: Simple, scalable WordPress hosting
- ✅ **Google Cloud Platform**: Flexible, enterprise-grade

### Managed WordPress Hosting (Recommended)
- ✅ **WP Engine**: Excellent for WooCommerce stores
- ✅ **Kinsta**: High-performance, good for AI workloads
- ✅ **Pressable**: WooCommerce-optimized plans
- ✅ **Cloudways**: Multiple cloud providers, WooCommerce-optimized

---

## 9. Hosting Features Checklist

Before deploying, verify your hosting provider offers:

- [ ] PHP 8.3+ (required)
- [ ] cURL extension enabled
- [ ] JSON extension enabled
- [ ] MySQL/MariaDB 5.7+
- [ ] At least 128MB PHP memory limit (256MB+ preferred)
- [ ] Outbound HTTPS access (no firewall blocking)
- [ ] WP-Cron support or ability to set real cron jobs
- [ ] SSL certificate (Let's Encrypt is fine)
- [ ] Regular backups (or ability to implement)
- [ ] SSH access (recommended for troubleshooting)
- [ ] PHP error logging enabled

---

## 10. Performance Considerations

### Optimization Tips
1. **Caching**: Use object caching (Redis/Memcached) for WooCommerce data
2. **Database**: Keep database optimized (regular maintenance)
3. **CDN**: Use CDN for static assets (CSS/JS)
4. **Monitoring**: Monitor API response times and costs
5. **Rate Limiting**: Implement rate limiting for AI requests if needed

### Scaling for High Traffic
- **Caching**: Cache AI responses for common queries
- **Queue System**: Use background job processing for AI requests
- **Database**: Consider read replicas for large stores
- **API Limits**: Monitor and handle OpenAI rate limits gracefully

---

## 11. Security Considerations

### API Key Security
- ✅ Store API keys in `config.php` (outside version control)
- ✅ Use environment variables if available (more secure)
- ✅ Never expose API keys in frontend JavaScript
- ✅ Rotate API keys periodically

### Data Privacy
- **Chat History**: Contains store data; ensure compliance with privacy regulations
- **Data Retention**: Configure automatic cleanup of old chat history
- **Access Control**: Limit plugin access to authorized administrators only

### Network Security
- **HTTPS Only**: Force HTTPS for all plugin requests
- **API Validation**: Validate all API responses before processing
- **Error Handling**: Don't expose sensitive errors to frontend

---

## 12. Troubleshooting Common Hosting Issues

### Issue: "cURL not available"
**Solution**: Contact hosting provider to enable cURL extension

### Issue: "Maximum execution time exceeded"
**Solution**: Increase `max_execution_time` in `php.ini` or use `set_time_limit()`

### Issue: "Outbound HTTPS blocked"
**Solution**: Check firewall settings, allow connections to `api.openai.com`

### Issue: "Memory limit exceeded"
**Solution**: Increase `memory_limit` in `php.ini` or via `.htaccess`

### Issue: "WP-Cron not running"
**Solution**: Set up real cron job or check if WP-Cron is disabled

---

## 13. Cost Considerations

### Hosting Costs
- **Shared Hosting**: $5-20/month (may not meet all requirements)
- **VPS**: $10-40/month (meets all requirements)
- **Managed WordPress**: $30-100+/month (optimal, includes optimizations)

### API Costs (OpenAI)
- **Pay-per-use**: Varies based on usage
- **Estimate**: $0.01-0.10 per chat interaction (depends on model and message length)
- **Monitoring**: Track usage in OpenAI dashboard

### Total Estimated Monthly Cost
- **Small Store**: $30-50/month (hosting + API usage)
- **Medium Store**: $50-150/month
- **Large Store**: $150-500+/month

---

## 14. Migration Checklist

When moving to a new host:

1. ✅ Verify all PHP extensions are available
2. ✅ Test outbound HTTPS connections
3. ✅ Verify PHP memory and execution time limits
4. ✅ Test OpenAI API connectivity
5. ✅ Verify database backups include custom tables
6. ✅ Test WP-Cron functionality
7. ✅ Update API keys in new environment
8. ✅ Test plugin activation and basic functionality
9. ✅ Verify chat history migration (if applicable)
10. ✅ Monitor error logs for issues

---

## 15. Support and Resources

### Plugin Support
- Check plugin README.md for installation instructions
- Review error logs: `wp-content/debug.log`
- Enable WP_DEBUG for troubleshooting

### Hosting Support
- Contact hosting provider for server-specific issues
- Request PHP version/extension changes if needed
- Ask about firewall rules if API calls fail

### API Support
- OpenAI Support: https://help.openai.com/
- Monitor usage: https://platform.openai.com/usage

---

## Summary

**Minimum Requirements:**
- WordPress 6.0+, WooCommerce 6.0+, PHP 8.3+
- cURL extension, 128MB PHP memory
- Outbound HTTPS access
- MySQL/MariaDB database

**Recommended for Production:**
- PHP 8.3+, 256MB+ PHP memory
- 2+ CPU cores, 1GB+ RAM
- Managed WordPress hosting
- Regular backups and monitoring

**Key Dependency:**
- Valid OpenAI API key and account
- Reliable internet connection for API calls

---

**Last Updated**: January 2025
