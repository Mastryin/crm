# EduCRM - Bolt Deployment Guide

## 🚀 Quick Start

This is an Education CRM built on Laravel with custom cohort management, lead tracking, and payment features.

### First Time Setup

1. **Visit the installer**: Navigate to `/install.php` in your browser
2. **Wait for installation**: The installer will:
   - Run database migrations
   - Seed the database with initial data
   - Create the superadmin user
3. **Login**: Use the credentials displayed after installation

### Default Credentials

```
Email: rohanmishra.design@gmail.com
Password: admin@123
```

**⚠️ IMPORTANT**: Change your password immediately after first login!

### Admin Panel Access

After installation, access the admin panel at:
```
/admin
```

## 📋 Features

### Lead Management
- Multi-channel lead capture (Meta Ads, Deftform, Pabbly, CSV imports)
- Automatic de-duplication by phone number
- Lead qualification engine with configurable rules
- Round-robin assignment to sales advisors
- Kanban view for pipeline management

### Program & Cohort Management
- Define educational programs with pricing
- Manage cohorts with capacity tracking
- Application deadline monitoring
- Enrollment tracking

### Payment Tracking
- Payment plans with installments
- Automated payment reminders
- Transaction history
- Overdue payment alerts

### Communication
- Email integration (SMTP/Encharge)
- WhatsApp integration (Aisensy)
- SMS notifications
- Template-based messaging

### Automation
- Status-based automation triggers
- Webhook integrations (Pabbly)
- Scheduled jobs for reminders and cleanup
- Call scheduling (Trafft integration)

## 🗄️ Database

The application uses **Supabase (PostgreSQL)** as configured in the `.env` file.

All database credentials are pre-configured. No manual setup needed.

## 🔧 Configuration

The `.env` file is pre-configured with:
- Database connection (Supabase)
- Application settings
- Timezone (Asia/Kolkata)
- Currency (INR)

To modify settings, edit the `.env` file in the root directory.

## 📡 API Endpoints

### Lead Capture
- `POST /api/educrm/leads/capture/meta-ads` - Meta Ads webhook
- `POST /api/educrm/leads/capture/deftform` - Deftform submissions
- `POST /api/educrm/leads/capture/pabbly` - Pabbly integration
- `POST /api/educrm/leads/capture/csv` - CSV import

### Webhooks
- `POST /api/educrm/webhooks/pabbly` - Pabbly webhook receiver
- `POST /api/educrm/webhooks/trafft` - Trafft callback handler

## 🏗️ Project Structure

```
packages/Webkul/EduCRM/
├── src/
│   ├── Models/          # Database models
│   ├── Services/        # Business logic
│   ├── Jobs/            # Scheduled jobs
│   ├── Controllers/     # API & Admin controllers
│   ├── Routes/          # Route definitions
│   └── Resources/views/ # Admin UI views
└── composer.json
```

## 🔐 Security

- Row-Level Security (RLS) enabled on all database tables
- Role-based access control (RBAC)
- Password hashing with bcrypt
- CSRF protection enabled
- SQL injection prevention

## 📊 Scheduled Tasks

The following jobs run automatically:
- Payment reminders: Daily at 9:00 AM
- Cohort deadline checks: Daily at 8:00 AM
- Trash cleanup (60-day retention): Daily
- Assignment counter reset: Daily at midnight
- Call reminders: Every 5 minutes

## 🆘 Troubleshooting

### Installation Issues

If installation fails:
1. Check database connection in `.env`
2. Ensure Supabase database is accessible
3. Delete `storage/installed` file and retry

### Cannot Login

1. Verify credentials are correct
2. Clear browser cache
3. Check if user exists in database
4. Run `/install.php` again if needed

### White Screen / Errors

1. Check `storage/logs/laravel.log`
2. Ensure `.env` file exists and is configured
3. Verify database connection
4. Check file permissions on `storage/` directory

## 📞 Support

For issues or questions:
- Check the logs in `storage/logs/`
- Review the `.env` configuration
- Ensure all migrations ran successfully

## 🔄 Updates

To update the application:
1. Pull latest changes
2. Run migrations: Access `/install.php` (after deleting lock file)
3. Clear cache if needed

---

**Built with Laravel, Supabase, and ❤️ for Education Sector**
