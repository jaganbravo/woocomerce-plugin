# Secure Payment Integration Guide

## ✅ What Has Been Implemented

### 1. Payment Handler Class (`class-dataviz-ai-payment-handler.php`)
- ✅ Stripe integration (Payment Intents API)
- ✅ PayPal integration (Orders API)
- ✅ Secure payment processing
- ✅ License key generation after successful payment
- ✅ Email delivery of license keys
- ✅ Support for test/live modes

### 2. Secure Checkout Page
- ✅ Professional checkout interface
- ✅ Stripe Elements for card input (PCI compliant)
- ✅ PayPal Smart Buttons
- ✅ Order summary display
- ✅ Feature list sidebar
- ✅ Security messaging

### 3. Payment Processing Flow
1. User clicks "Buy Plan" button → Redirects to secure checkout
2. User selects payment method (Stripe or PayPal)
3. Payment processed securely (no card data touches your server)
4. License key generated automatically
5. License activated automatically
6. License key sent via email
7. User redirected to license page with success message

### 4. AJAX Handlers
- ✅ `dataviz_ai_create_payment_intent` - Creates Stripe payment intent
- ✅ `dataviz_ai_process_payment` - Processes payment and generates license

## 🔧 Configuration

### Stripe Setup

1. **Get Stripe API Keys:**
   - Sign up at https://stripe.com
   - Go to Developers → API keys
   - Copy your Publishable key and Secret key

2. **Configure in `config.php`:**
   ```php
   define( 'DATAVIZ_AI_STRIPE_SECRET_KEY', 'sk_test_...' );
   define( 'DATAVIZ_AI_STRIPE_PUBLISHABLE_KEY', 'pk_test_...' );
   ```

3. **Or use Environment Variables:**
   ```bash
   export STRIPE_SECRET_KEY="sk_test_..."
   export STRIPE_PUBLISHABLE_KEY="pk_test_..."
   ```

### PayPal Setup

1. **Get PayPal Credentials:**
   - Sign up at https://developer.paypal.com
   - Create a new app
   - Copy Client ID and Secret

2. **Configure in `config.php`:**
   ```php
   define( 'DATAVIZ_AI_PAYPAL_CLIENT_ID', 'your_client_id' );
   define( 'DATAVIZ_AI_PAYPAL_CLIENT_SECRET', 'your_client_secret' );
   ```

3. **Or use Environment Variables:**
   ```bash
   export PAYPAL_CLIENT_ID="your_client_id"
   export PAYPAL_CLIENT_SECRET="your_client_secret"
   ```

### Payment Mode

Set test or live mode:
```php
define( 'DATAVIZ_AI_PAYMENT_MODE', 'test' ); // or 'live'
```

Or via environment:
```bash
export PAYMENT_MODE="test"
```

## 🔒 Security Features

### PCI Compliance
- ✅ **Stripe Elements**: Card data never touches your server
- ✅ **Tokenization**: Payment data is tokenized by Stripe/PayPal
- ✅ **HTTPS Required**: All payment pages use HTTPS
- ✅ **Nonce Verification**: All AJAX requests verified with nonces
- ✅ **Capability Checks**: Only authorized users can process payments

### Data Protection
- ✅ No card numbers stored
- ✅ No CVV codes stored
- ✅ Payment IDs only stored for reference
- ✅ License keys encrypted in database

## 📋 Payment Flow Details

### Stripe Flow
1. User enters card details in Stripe Elements (secure iframe)
2. JavaScript creates payment intent via AJAX
3. Stripe returns client secret
4. Card payment confirmed client-side
5. Payment ID sent to server
6. Server generates license key
7. License activated automatically

### PayPal Flow
1. User clicks PayPal button
2. PayPal SDK creates order
3. User approves payment in PayPal popup
4. Order captured
5. Payment ID sent to server
6. Server generates license key
7. License activated automatically

## 🎯 Testing

### Test Cards (Stripe)
- **Success**: `4242 4242 4242 4242`
- **Decline**: `4000 0000 0000 0002`
- **3D Secure**: `4000 0025 0000 3155`
- Use any future expiry date and any 3-digit CVC

### Test PayPal
- Use PayPal Sandbox accounts
- Create test buyer/seller accounts
- Test with sandbox credentials

## 📧 Email Notifications

After successful payment, users receive:
- License key
- Activation instructions
- Support contact information

Email template can be customized in `send_license_key_email()` method.

## 🔄 Webhook Support (Future Enhancement)

For production, consider adding webhooks:
- Stripe webhooks for payment status updates
- PayPal webhooks for order status
- Handle subscription renewals
- Handle payment failures

## 📊 Files Created/Modified

**New Files:**
- `includes/class-dataviz-ai-payment-handler.php` - Payment processing
- `admin/js/checkout.js` - Frontend payment handling

**Modified Files:**
- `includes/class-dataviz-ai-admin.php` - Added checkout page
- `includes/class-dataviz-ai-ajax-handler.php` - Added payment AJAX handlers
- `includes/class-dataviz-ai-loader.php` - Registered payment handlers

## 🚀 Next Steps

1. **Configure Payment Credentials**
   - Add Stripe keys to `config.php` or environment
   - Add PayPal credentials if using PayPal

2. **Test Payment Flow**
   - Test with Stripe test cards
   - Test with PayPal sandbox
   - Verify license activation

3. **Switch to Live Mode**
   - Update to live Stripe keys
   - Update to live PayPal credentials
   - Set `PAYMENT_MODE` to `'live'`

4. **Set Up Webhooks** (Optional)
   - Configure Stripe webhooks
   - Configure PayPal webhooks
   - Handle subscription events

5. **Customize Email Templates**
   - Update license key email
   - Add branding
   - Include support links

## ⚠️ Important Notes

- **Never commit API keys** to version control
- **Use test mode** during development
- **Test thoroughly** before going live
- **Monitor payments** in Stripe/PayPal dashboards
- **Handle errors gracefully** for better UX
- **Keep payment handler updated** with latest security practices

## 📞 Support

For payment issues:
- Check Stripe/PayPal dashboards
- Review server logs
- Test with test credentials first
- Contact payment provider support if needed

---

**Last Updated:** December 17, 2025
**Status:** ✅ Payment integration complete and ready for testing

