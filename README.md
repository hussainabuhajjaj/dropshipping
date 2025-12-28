# Laravel Dropshipping Application

A full-featured dropshipping e-commerce platform built with Laravel 12.x, Vue.js 3, and Filament Admin Panel.

## 🚀 Quick Links

- **🎯 [Deployment Quick Start](DEPLOYMENT_QUICK_START.md)** - Get started in 5 minutes
- **📖 [Complete Deployment Guide](CPANEL_DEPLOYMENT_GUIDE.md)** - Full cPanel deployment instructions
- **✅ [Deployment Checklist](DEPLOYMENT_CHECKLIST_CPANEL.md)** - Track your deployment progress
- **🎉 [Deployment Ready Summary](DEPLOYMENT_READY.md)** - Comprehensive readiness status
- **⚡ [Production Optimization](PRODUCTION_OPTIMIZATION.md)** - Performance tuning guide

## 📋 Features

### E-commerce Features
- 🛍️ Product catalog with categories
- 🛒 Shopping cart with session persistence
- 💳 Secure checkout process (guest & registered)
- 📦 Order tracking and management
- ⭐ Product reviews and ratings
- ❤️ Wishlist functionality
- 🎁 Coupon and gift card system
- 🔄 Return request management

### CJ Dropshipping Integration
- 🔗 Product synchronization
- 📊 Inventory management
- 🚚 Automated order fulfillment
- 📡 Webhook handling for order updates
- 🏭 Warehouse management

### Payment Integration
- 💰 Paystack payment gateway
- 🔔 Webhook support
- 💵 Refund handling
- 🧾 Payment history

### Admin Panel (Filament)
- 📊 Comprehensive dashboard
- 🛠️ Product management
- 📋 Order management
- 👥 Customer management
- ⚙️ Site settings
- 📈 Analytics and reports
- 🔐 Role-based access control

### Additional Features
- 🌐 Multi-language support
- 📱 Mobile app API
- 🔍 SEO optimization
- 📧 Email notifications
- 🎨 Customizable storefront
- 🔐 Google OAuth integration
- 🤖 AI-powered features (DeepSeek integration)

## 🛠️ Tech Stack

- **Backend:** Laravel 12.x
- **Frontend:** Vue.js 3 + Inertia.js
- **Admin Panel:** Filament 4.3
- **Styling:** Tailwind CSS 3.4
- **Database:** MySQL (Production) / SQLite (Development)
- **Authentication:** Laravel Sanctum
- **Payment:** Paystack
- **Dropshipping:** CJ Dropshipping API
- **Build Tool:** Vite 7

## ⚙️ Requirements

- PHP 8.2 or higher
- Composer 2.x
- Node.js 18+ and npm
- MySQL 5.7+ or MariaDB 10.3+
- Apache/Nginx with mod_rewrite enabled
- SSL certificate (recommended)

### Required PHP Extensions
- OpenSSL
- PDO
- Mbstring
- Tokenizer
- XML
- Ctype
- JSON
- BCMath
- Fileinfo
- GD or Imagick

## 🚀 Quick Start (Development)

### 1. Clone and Install Dependencies
```bash
# Clone the repository
git clone <your-repo-url>
cd dropshipping

# Install PHP dependencies
composer install

# Install Node dependencies
npm install
```

### 2. Configure Environment
```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure your database in .env
# For development, SQLite is already configured
```

### 3. Set Up Database
```bash
# Run migrations
php artisan migrate

# Seed the database (optional)
php artisan db:seed --class=SettingsSeeder

# Create storage link
php artisan storage:link
```

### 4. Build Assets and Start Development
```bash
# Build frontend assets
npm run build

# Start development servers
composer dev
# This runs: Laravel server, queue worker, logs, and Vite

# Or run individually:
php artisan serve      # Laravel server
npm run dev           # Vite dev server
php artisan queue:work # Queue worker
```

### 5. Access the Application
- **Storefront:** http://localhost:8000
- **Admin Panel:** http://localhost:8000/admin

## 📦 Deployment to cPanel

### Ready to Deploy? 🎯

This project is **fully prepared** for cPanel deployment. All necessary files and documentation have been created.

### Start Here:
1. **📖 Read:** [DEPLOYMENT_READY.md](DEPLOYMENT_READY.md) - Complete readiness summary
2. **🚀 Quick Start:** [DEPLOYMENT_QUICK_START.md](DEPLOYMENT_QUICK_START.md) - 5-minute overview
3. **📚 Full Guide:** [CPANEL_DEPLOYMENT_GUIDE.md](CPANEL_DEPLOYMENT_GUIDE.md) - Step-by-step instructions
4. **✅ Track:** [DEPLOYMENT_CHECKLIST_CPANEL.md](DEPLOYMENT_CHECKLIST_CPANEL.md) - Deployment checklist

### Pre-Deployment Scripts

**Windows:**
```bash
deploy-prepare.bat
```

**Linux/Mac:**
```bash
chmod +x deploy-prepare.sh
./deploy-prepare.sh
```

These scripts will:
- Install all dependencies
- Build production assets
- Optimize the application
- Create a deployment package

## 🔧 Configuration

### Environment Variables

Key environment variables to configure:

```env
# Application
APP_NAME="Your Store Name"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Database
DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Payment
PAYSTACK_SECRET_KEY=your_secret_key
PAYSTACK_PUBLIC_KEY=your_public_key

# CJ Dropshipping
CJ_API_KEY=your_api_key
CJ_API_SECRET=your_api_secret

# Mail
MAIL_MAILER=smtp
MAIL_HOST=your_mail_host
MAIL_USERNAME=your_email
MAIL_PASSWORD=your_password
```

See `.env.production` for a complete production-ready template.

## 📚 Documentation

### Deployment Documentation
- [Deployment Readiness Summary](DEPLOYMENT_READY.md)
- [Quick Start Guide](DEPLOYMENT_QUICK_START.md)
- [Complete Deployment Guide](CPANEL_DEPLOYMENT_GUIDE.md)
- [Deployment Checklist](DEPLOYMENT_CHECKLIST_CPANEL.md)
- [Production Optimization](PRODUCTION_OPTIMIZATION.md)

### API Documentation
- [API Documentation Index](API_DOCS_INDEX.md)
- [API Quick Start](API_QUICK_START.md)
- [API Implementation Summary](API_IMPLEMENTATION_SUMMARY.md)

### Project Documentation
- [Project Completion Report](PROJECT_COMPLETE.md)
- [System Status Report](SYSTEM_STATUS_REPORT.md)
- [Audit Full Report](AUDIT_FULL_REPORT.md)

## 🧪 Testing

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature

# Run with coverage
php artisan test --coverage
```

## 🔐 Security

### Production Security Checklist
- ✅ `APP_DEBUG=false`
- ✅ Strong `APP_KEY` generated
- ✅ HTTPS enforced
- ✅ CORS properly configured
- ✅ Rate limiting active
- ✅ CSRF protection enabled
- ✅ SQL injection protection (Eloquent ORM)
- ✅ XSS protection headers
- ✅ Secure session configuration

### Reporting Vulnerabilities
If you discover a security vulnerability, please email [your-email@example.com].

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## 🙏 Acknowledgments

- [Laravel Framework](https://laravel.com)
- [Filament Admin Panel](https://filamentphp.com)
- [Vue.js](https://vuejs.org)
- [Tailwind CSS](https://tailwindcss.com)
- [Inertia.js](https://inertiajs.com)
- [CJ Dropshipping](https://www.cjdropshipping.com)
- [Paystack](https://paystack.com)

## 📞 Support

For deployment support:
1. Check the [Deployment Guide](CPANEL_DEPLOYMENT_GUIDE.md)
2. Review the [Troubleshooting Section](CPANEL_DEPLOYMENT_GUIDE.md#-troubleshooting)
3. Check Laravel logs: `storage/logs/laravel.log`
4. Contact your hosting provider for server-specific issues

## 🎯 Project Status

**Status:** ✅ Production Ready  
**Version:** 1.0.0  
**Last Updated:** December 29, 2025

---

**Made with ❤️ using Laravel**
In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
## Pricing & Inventory

### Product vs. Variant Pricing

- **Product-level prices**: `selling_price` and `cost_price` serve as defaults for all variants.
- **Variant-level prices**: When set, the variant `price` overrides the product's `selling_price`.
- **Cost fallback**: Variant `cost_price` uses the product's `cost_price` if not explicitly set.
- **Margin validation**: Selling price must meet minimum margin requirements based on cost price and configured margin policy.

### CJ Dropshipping Integration

- **Ship-to filtering**: Use the optional `CJ_SHIP_TO_DEFAULT` environment variable to filter CJ products by destination country (e.g., `US`, `GB`). The system infers warehouse countries from product payloads and skips import if no warehouse serves the target country.
- **Sync configuration**: CJ product imports and syncs inherit the ship-to country from configuration and pass it through all import operations.
- **Pre-import filtering**: In the CJ Catalog and My Products pages, use the "Ship-to Filter" action to dynamically filter displayed items before import.