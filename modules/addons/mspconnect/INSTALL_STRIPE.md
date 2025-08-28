# Installing Stripe PHP Library for MSPConnect

The MSPConnect module requires the Stripe PHP library to enable payment processing features. Here are several ways to install it:

## Method 1: Composer (Recommended)

If you have Composer installed on your server:

```bash
cd /var/www/eazybackup.ca/accounts/modules/addons/mspconnect/
composer require stripe/stripe-php
```

## Method 2: Install in WHMCS Root

If you prefer to install Stripe globally for your WHMCS installation:

```bash
cd /var/www/eazybackup.ca/accounts/
composer require stripe/stripe-php
```

## Method 3: Manual Download

1. Download the Stripe PHP library from: https://github.com/stripe/stripe-php/releases
2. Extract it to one of these locations:
   - `/var/www/eazybackup.ca/accounts/modules/addons/mspconnect/vendor/stripe/stripe-php/`
   - `/var/www/eazybackup.ca/accounts/vendor/stripe/stripe-php/`

## Method 4: Create composer.json (If Composer not installed)

Create a `composer.json` file in the MSPConnect directory:

```json
{
    "require": {
        "stripe/stripe-php": "^10.0"
    }
}
```

Then run:
```bash
cd /var/www/eazybackup.ca/accounts/modules/addons/mspconnect/
curl -sS https://getcomposer.org/installer | php
php composer.phar install
```

## Verification

After installation, the module should work without the Stripe-related errors. You can verify by:

1. Accessing the MSPConnect dashboard
2. Going to Stripe Connect settings
3. The error message should be gone

## Module Functionality Without Stripe

If you don't install Stripe, the module will still work but with limited functionality:

- ✅ Customer management
- ✅ Service plans
- ✅ Invoice generation
- ✅ Email templates
- ❌ Payment processing
- ❌ Stripe Connect integration

## Troubleshooting

If you still get errors after installation:

1. Check file permissions: `chmod -R 755 vendor/`
2. Verify the path exists: `ls -la vendor/stripe/stripe-php/`
3. Check PHP include_path settings
4. Restart your web server

## Alternative Payment Gateways

If you prefer not to use Stripe, you can modify the module to integrate with other payment processors by updating the `StripeManager.php` class. 