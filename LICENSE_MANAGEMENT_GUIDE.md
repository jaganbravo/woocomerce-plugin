# License Management & Purchase System Guide

## ✅ What Has Been Implemented

### 1. License Manager Class (`class-dataviz-ai-license-manager.php`)
- ✅ License key validation and activation
- ✅ Plan management (Free, Pro, Agency)
- ✅ Usage tracking (questions per month)
- ✅ Monthly reset functionality
- ✅ Premium feature gating
- ✅ Purchase URL management

### 2. License Settings Page
- ✅ Admin menu: "Dataviz AI → License"
- ✅ License activation/deactivation interface
- ✅ Usage statistics display
- ✅ Upgrade prompts and purchase links
- ✅ Plan comparison (Pro vs Agency)

### 3. Usage Tracking
- ✅ Automatic question counting
- ✅ Free tier: 50 questions/month limit
- ✅ Premium tiers: Unlimited questions
- ✅ Monthly reset on day 30
- ✅ Usage displayed on main admin page

### 4. Premium Feature Gating
- ✅ License checks before processing questions
- ✅ Error messages when limit reached
- ✅ Upgrade prompts with purchase links
- ✅ Usage stats passed to JavaScript

## 📋 How Customers Purchase the Plugin

### Option 1: WooCommerce.com Marketplace (Recommended)

**Steps:**
1. Submit plugin to WooCommerce.com marketplace
2. Set up product listing with pricing:
   - Pro Plan: $15/month or $150/year
   - Agency Plan: $99/month or $990/year
3. Customers purchase through WooCommerce.com checkout
4. They receive license key via email
5. They activate license in WordPress admin: `Dataviz AI → License`

**Purchase URLs (Update in License Manager):**
- Pro: `https://woocommerce.com/products/dataviz-ai-woocommerce`
- Agency: `https://woocommerce.com/products/dataviz-ai-woocommerce-agency`

### Option 2: WordPress.org (Free Version)

**Steps:**
1. Submit free version to WordPress.org
2. Free tier: 50 questions/month
3. Customers install from WordPress admin
4. Upgrade prompts shown when approaching limit
5. Links to WooCommerce.com for premium purchase

### Option 3: Direct Sales (Your Website)

**Steps:**
1. Create WooCommerce store on your website
2. Set up products for Pro/Agency plans
3. Use WooCommerce Subscriptions plugin
4. Generate license keys automatically
5. Update license server URL in `class-dataviz-ai-license-manager.php`

## 🔧 Configuration

### License Server URL
Update the license server URL in `class-dataviz-ai-license-manager.php`:
```php
private $license_server_url = 'https://your-license-server.com/api/validate';
```

### Test License Keys
For testing, use these keys:
- Pro: `TEST-PRO-LICENSE-KEY-12345`
- Agency: `TEST-AGENCY-LICENSE-KEY-12345`

**⚠️ Remove test keys before production!**

### Purchase URLs
Update purchase URLs in `get_purchase_url()` method:
```php
$urls = array(
    'pro'    => 'https://woocommerce.com/products/dataviz-ai-woocommerce',
    'agency' => 'https://woocommerce.com/products/dataviz-ai-woocommerce-agency',
);
```

## 📊 Pricing Plans

### Free Plan
- **Price:** $0/month
- **Features:**
  - 50 questions per month
  - Basic entity types (orders, products, customers)
  - Standard response time
  - Community support
  - Single store

### Pro Plan
- **Price:** $15/month or $150/year (save 17%)
- **Features:**
  - Unlimited questions
  - All entity types
  - Chat history (5 days)
  - Priority support
  - Advanced analytics
  - Single store

### Agency Plan
- **Price:** $99/month or $990/year (save 17%)
- **Features:**
  - Everything in Pro
  - Multiple stores (up to 10)
  - White-label option
  - Priority support
  - API access (future)
  - Custom integrations (future)

## 🚀 Next Steps

### 1. Set Up License Server (For Production)
- Create API endpoint for license validation
- Implement license key generation
- Set up automatic license key delivery
- Handle license renewal/expiration

### 2. Submit to Marketplaces
- **WordPress.org:** Submit free version
- **WooCommerce.com:** Apply for marketplace listing
- Create product pages with screenshots
- Set up payment processing

### 3. Marketing
- Create landing page with pricing
- Add upgrade prompts in plugin UI
- Email campaigns for free users
- Social media promotion

### 4. Support System
- Set up support ticketing
- Create knowledge base
- Email support for premium users
- Community forum for free users

## 🔐 Security Notes

- License keys are stored in WordPress options (encrypted at rest by WordPress)
- License validation should be done server-side
- Never expose license keys in frontend JavaScript
- Use HTTPS for all license server communications
- Implement rate limiting on license validation

## 📝 Files Modified/Created

**New Files:**
- `includes/class-dataviz-ai-license-manager.php` - License management system

**Modified Files:**
- `includes/class-dataviz-ai-admin.php` - Added license page and usage display
- `includes/class-dataviz-ai-ajax-handler.php` - Added license checks and usage tracking
- `includes/class-dataviz-ai-loader.php` - Loaded license manager

## 🎯 Testing Checklist

- [ ] Test license activation with valid key
- [ ] Test license activation with invalid key
- [ ] Test license deactivation
- [ ] Test free tier limit enforcement
- [ ] Test usage counter increment
- [ ] Test monthly reset functionality
- [ ] Test upgrade prompts
- [ ] Test purchase links
- [ ] Test premium feature access

---

**Last Updated:** December 15, 2025
**Status:** ✅ Core functionality complete, ready for license server integration

