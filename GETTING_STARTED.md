# 🎓 EduCRM - Getting Started

## Welcome to EduCRM!

A comprehensive Education CRM for managing cohort-based courses, leads, and payments.

## 🚀 First Time Setup (3 Easy Steps)

### Step 1: Open the Application
Once deployed on Bolt, simply open the application URL.

### Step 2: Run the Installer
The application will automatically redirect you to the installer at `/install.php`

The installer will:
- ✓ Set up all database tables
- ✓ Configure initial data
- ✓ Create your superadmin account

This process takes about 30 seconds.

### Step 3: Login
After installation completes, you'll see your login credentials:

```
Email: rohanmishra.design@gmail.com
Password: admin@123
```

Click "Go to Admin Panel" to access the CRM.

## 📱 What You Can Do

### Lead Management
- Capture leads from multiple sources (Meta Ads, forms, CSV imports)
- Automatic lead qualification and scoring
- Round-robin assignment to sales team
- Kanban-style pipeline views
- Lead de-duplication

### Program & Cohort Management
- Create and manage educational programs
- Set up cohorts with capacity limits
- Track enrollments and deadlines
- Monitor application status

### Payment Tracking
- Create payment plans with installments
- Track payment status
- Automated payment reminders
- View revenue analytics

### Automation & Communication
- Email & WhatsApp notifications
- Status-based automation triggers
- Webhook integrations
- Scheduled reminders

## 🔑 Important Security Notes

**Change Your Password**: After first login, go to your profile and change the default password!

**Database Security**: All tables have Row-Level Security (RLS) enabled with proper access policies.

## 📊 Dashboard Access

After logging in, you'll land on the dashboard with:
- Overview statistics
- Recent leads
- Payment summary
- Upcoming tasks

## 🎯 Quick Actions

From the admin panel, you can:

1. **Add a Program**: Settings → Programs → Create Program
2. **Create a Cohort**: Settings → Cohorts → Create Cohort
3. **Add a Lead**: Leads → Create Lead
4. **Set up Automation**: Settings → Automations → Create Automation
5. **Configure Qualification Rules**: Settings → Qualification Rules

## 🔗 API Integration

EduCRM provides APIs for lead capture:

- Meta Ads: `POST /api/educrm/leads/capture/meta-ads`
- Deftform: `POST /api/educrm/leads/capture/deftform`
- Pabbly: `POST /api/educrm/leads/capture/pabbly`
- CSV Import: `POST /api/educrm/leads/capture/csv`

See the full API documentation in BOLT_DEPLOYMENT.md

## 💡 Tips

1. **Start with Programs**: Define your educational programs first
2. **Create Cohorts**: Set up cohorts with start dates and capacity
3. **Configure Qualification**: Set up rules to automatically qualify/disqualify leads
4. **Enable Notifications**: Configure email and WhatsApp for automated communication
5. **Set up Webhooks**: Integrate with Pabbly for workflow automation

## 🆘 Need Help?

- Check `BOLT_DEPLOYMENT.md` for detailed documentation
- Review logs in the Admin Panel under Settings → Logs
- Database issues? Check your Supabase connection in `.env`

## 🎉 You're All Set!

Start by creating your first program and cohort, then begin capturing leads!

---

**Enjoy using EduCRM! 🚀**
