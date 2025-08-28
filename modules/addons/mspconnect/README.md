# MSPConnect: Multi-Tenant Reseller Module with Stripe Connect

MSPConnect is a comprehensive WHMCS addon module that creates a "reseller" or "multi-tenant" environment within your existing WHMCS installation. It allows your MSP clients to manage their own customers and process payments through Stripe Connect, creating a complete white-label billing solution.

## 🚀 Key Features

### For Platform Owners
- **Multi-tenant MSP Management**: Each MSP client gets their own isolated customer management system
- **Stripe Connect Integration**: Automated payment processing with funds flowing directly to MSP accounts
- **White-label Email System**: Custom SMTP configuration and email templates for each MSP
- **Company Branding**: Logo upload and custom branding for each MSP
- **Comprehensive Admin Panel**: Monitor all MSPs, customers, and transactions from a single interface

### For MSP Clients
- **Customer Management**: Add, edit, and manage their own customer base
- **Service Plans**: Create custom backup plans with their own pricing
- **Invoice Management**: Automated billing with customizable invoices
- **Stripe Integration**: Direct connection to their own Stripe account
- **Activity Logging**: Complete audit trail of all activities
- **Email Templates**: Customizable email templates with merge fields

### For End Customers
- **Dedicated Portal**: Professional customer portal for account management
- **Invoice Viewing**: View and pay invoices through Stripe Checkout
- **Service Management**: View active services and usage information
- **Password Management**: Secure password reset functionality

## 📋 Requirements

- **WHMCS**: Version 7.0 or higher
- **PHP**: Version 7.4 or higher
- **MySQL**: Version 5.7 or higher
- **Stripe Account**: Platform account with Connect enabled
- **SSL Certificate**: Required for Stripe webhook handling

## 🛠 Installation

### 1. Upload Files
Upload the entire `mspconnect` folder to your WHMCS `modules/addons/` directory.

### 2. Activate Module
1. Log into your WHMCS admin area
2. Go to **Setup > Addon Modules**
3. Find "MSPConnect" and click **Activate**
4. Configure the module settings:
   - **Stripe Publishable Key**: Your platform's publishable key
   - **Stripe Secret Key**: Your platform's secret key
   - **Stripe Webhook Secret**: Your webhook endpoint secret
   - **Customer Portal Domain**: Domain for customer portal access
   - **Default From Email**: Fallback email for notifications

### 3. Database Setup
The module will automatically create all necessary database tables during activation:
- `msp_reseller_msp_settings`
- `msp_reseller_company_profile`
- `msp_reseller_smtp_config`
- `msp_reseller_email_templates`
- `msp_reseller_customers`
- `msp_reseller_plans`
- `msp_reseller_services`
- `msp_reseller_invoices`
- `msp_reseller_payment_methods`
- `msp_reseller_activity_log`

### 4. Stripe Setup

#### Platform Configuration
1. Create a Stripe Platform account at https://dashboard.stripe.com
2. Enable Stripe Connect in your dashboard
3. Create a Connect application and note your client ID
4. Configure webhook endpoints for payment notifications

#### Webhook Configuration
Set up webhooks in your Stripe dashboard to point to:
```
https://yourdomain.com/modules/addons/mspconnect/api/webhook.php
```

Required webhook events:
- `payment_intent.succeeded`
- `payment_intent.payment_failed`
- `invoice.payment_succeeded`
- `account.updated`

### 5. Customer Portal Setup
1. Configure a subdomain or directory for the customer portal
2. Update the portal domain in module settings
3. Ensure the portal files are accessible via web

## 🎯 Usage Guide

### For Platform Administrators

#### Managing MSPs
1. Navigate to **Addons > MSPConnect** in WHMCS admin
2. View all MSP accounts, their statistics, and activity
3. Monitor global transactions and revenue

#### Global Settings
- Configure default email templates
- Set up global SMTP settings
- Monitor system activity logs

### For MSP Clients

#### Getting Started
1. Access the module from your WHMCS client area
2. Connect your Stripe account via the Stripe Connect integration
3. Configure your company branding and email settings
4. Create your first service plans

#### Customer Management
1. **Add Customers**: Create customer accounts with automatic email notifications
2. **Manage Services**: Assign service plans to customers
3. **Process Billing**: Automated monthly/annual billing cycles
4. **Handle Support**: View customer activity and manage account status

#### Stripe Integration
1. Click "Connect Stripe Account" in settings
2. Authorize your Stripe account with MSPConnect
3. Configure payout settings in your Stripe dashboard
4. Monitor transactions and revenue

#### Email Customization
1. Configure SMTP settings for branded emails
2. Customize email templates with merge fields
3. Test email delivery with the built-in testing tool

### For End Customers

#### Portal Access
1. Receive welcome email with portal login details
2. Access portal at the configured domain
3. View services, invoices, and account information
4. Update payment methods and billing information

## 🔧 Configuration

### Environment Variables
Create a `.env` file in the module directory (optional):
```env
STRIPE_CLIENT_ID=ca_your_client_id
PORTAL_DOMAIN=portal.yourdomain.com
DEBUG_MODE=false
```

### Email Templates
Available merge fields for email templates:
- `{$msp_company_name}` - MSP company name
- `{$customer_first_name}` - Customer first name
- `{$customer_last_name}` - Customer last name
- `{$invoice_number}` - Invoice number
- `{$invoice_total}` - Invoice total amount
- `{$portal_url}` - Customer portal URL
- `{$password_reset_link}` - Password reset link

### SMTP Configuration
For best email deliverability, configure SMTP settings:
1. Use your domain's SMTP server
2. Configure SPF and DKIM records
3. Test email delivery regularly

## 🔐 Security Considerations

### Data Protection
- Customer passwords are hashed using PHP's `password_hash()`
- Sensitive data (SMTP passwords, tokens) are encrypted
- All database queries use parameterized statements
- Session management with secure cookies

### Access Control
- MSPs can only access their own customers
- API endpoints validate user permissions
- Activity logging for audit trails
- Rate limiting on login attempts

### Stripe Security
- Webhook signature verification
- Secure token storage
- OAuth state parameter validation
- Regular token refresh

## 📊 Database Schema

### Key Tables
- **msp_reseller_msp_settings**: MSP account configuration and Stripe integration
- **msp_reseller_customers**: End-customer accounts managed by MSPs
- **msp_reseller_invoices**: Billing and payment tracking
- **msp_reseller_activity_log**: Comprehensive activity logging

### Relationships
- Each MSP can have multiple customers
- Each customer can have multiple services
- Each service generates invoices based on billing cycles
- All activities are logged with timestamps and metadata

## 🐛 Troubleshooting

### Common Issues

#### Stripe Connection Fails
1. Verify Stripe API keys are correct
2. Check that Connect is enabled in Stripe dashboard
3. Ensure redirect URI is properly configured
4. Review activity logs for detailed error messages

#### Email Delivery Issues
1. Test SMTP configuration using the built-in test tool
2. Verify SPF/DKIM records for your domain
3. Check spam folders for delivered emails
4. Review email activity logs

#### Database Errors
1. Ensure proper MySQL permissions
2. Check table creation during module activation
3. Verify WHMCS database connection
4. Review PHP error logs for detailed information

### Debug Mode
Enable debug mode in module settings to see detailed error information and stack traces.

### Log Files
Check these log files for troubleshooting:
- WHMCS activity log
- PHP error log
- Module-specific activity log in database

## 🔄 Updates and Maintenance

### Backup Before Updates
Always backup your database and files before updating the module.

### Update Process
1. Upload new module files
2. Run any database migrations (if provided)
3. Test functionality in staging environment
4. Deploy to production

### Regular Maintenance
- Monitor activity logs for unusual activity
- Update Stripe webhooks if endpoints change
- Review and update email templates
- Check for module updates regularly

## 📞 Support

### Documentation
- Review this README for common questions
- Check WHMCS documentation for platform-specific issues
- Refer to Stripe Connect documentation for payment issues

### Logs and Debugging
When reporting issues, include:
- Module version
- PHP version
- WHMCS version
- Relevant log entries
- Steps to reproduce the issue

## 📜 License

This module is proprietary software. Unauthorized distribution or modification is prohibited.

## 🏗 Architecture Overview

```
MSPConnect Module
├── Core Module (mspconnect.php)
├── Client Controllers (lib/Client/)
├── Admin Controllers (lib/Admin/)
├── Stripe Integration (lib/Stripe/)
├── Email System (lib/Email/)
├── API Endpoints (api/)
├── Client Templates (templates/clientarea/)
├── Admin Templates (templates/admin/)
├── Customer Portal (portal/)
└── Assets (assets/)
```

### Data Flow
1. **MSP Onboarding**: MSP connects Stripe account via OAuth
2. **Customer Creation**: MSP adds customers, creates Stripe customer objects
3. **Service Management**: MSP creates plans, assigns to customers
4. **Automated Billing**: Cron job generates invoices, processes payments
5. **Payment Processing**: Stripe Connect routes funds to MSP account
6. **Notifications**: White-label emails sent to customers

### Integration Points
- **WHMCS Client Management**: Integrates with existing client accounts
- **Stripe Connect**: Full OAuth and payment processing integration
- **Email System**: Plugs into WHMCS email system with custom SMTP
- **Activity Logging**: Comprehensive audit trail for compliance
- **Webhook Handling**: Real-time payment status updates

This comprehensive module provides everything needed to run a successful multi-tenant MSP billing platform with professional white-label customer management. 