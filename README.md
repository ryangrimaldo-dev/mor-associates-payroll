# EarnMOR Payroll System

**"It Pays to EarnMOR."**

A comprehensive web-based payroll management system built with PHP, MySQL, and Bootstrap. This system provides complete payroll processing capabilities with robust security features and modern user interface.

## 🚀 Features

### Core Payroll Features

#### Employee Management
- **Employee Registration**: Add new employees with complete profile information
- **Employee Database**: Manage employee records with personal and employment details
- **Employee Status Tracking**: Active/Inactive employee status management
- **Department Organization**: Organize employees by departments
- **Position Management**: Track employee positions and roles
- **Rate Management**: Support for both daily and monthly rate structures

#### Payroll Processing
- **Automated Payroll Calculation**: Calculate gross pay, deductions, and net pay
- **Pay Period Management**: Create and manage different pay periods
- **Multiple Rate Types**: Support for daily and monthly salary structures
- **Automatic Deductions**: 
  - SSS (Social Security System) contributions
  - PhilHealth contributions
  - Pag-IBIG contributions
  - Withholding tax calculations
- **Custom Deductions**: Add custom deductions as needed
- **Overtime Calculations**: Handle overtime pay calculations
- **Payroll Approval Workflow**: Multi-step approval process for payroll
- **Bulk Payroll Processing**: Process payroll for multiple employees simultaneously

#### Payslip Management
- **Digital Payslips**: Generate professional PDF payslips
- **Payslip Download**: Employees can download their payslips
- **Email Distribution**: Automatically email payslips to employees
- **Payslip History**: Access historical payslip records
- **Custom Payslip Numbers**: Unique identification for each payslip

#### Reporting & Analytics
- **Monthly Reports**: Generate comprehensive monthly payroll reports
- **Annual Reports**: Year-end payroll summaries and analytics
- **Department Reports**: Payroll analysis by department
- **Employee Reports**: Individual employee payroll history
- **Export Functionality**: Export reports to PDF and CSV formats
- **Real-time Analytics**: Dashboard with payroll insights

#### User Management & Access Control
- **Role-Based Access**: Admin and Employee role distinctions
- **Admin Dashboard**: Complete system overview for administrators
- **Employee Portal**: Self-service portal for employees
- **User Profile Management**: Update personal information and preferences

### Authentication & Login Options

#### Standard Authentication
- **Username/Password Login**: Traditional login system
- **Secure Password Hashing**: bcrypt with cost factor 12
- **Session Management**: Secure session handling with timeout

#### Google OAuth Integration
- **Google Sign-In**: Login using Google accounts
- **Profile Integration**: Automatic profile picture and information sync
- **Seamless Authentication**: Single sign-on capability

## 🔒 Security Features

### Authentication & Authorization
- **Secure Password Hashing**: bcrypt algorithm with cost factor 12
- **Session Security**: 
  - Session timeout and automatic regeneration
  - Secure session cookie settings
  - Session hijacking protection
- **Rate Limiting**: Protection against brute force login attempts
- **CSRF Protection**: Cross-Site Request Forgery protection on all forms
- **Role-Based Access Control**: Granular permissions based on user roles

### Input Validation & Data Protection
- **Input Sanitization**: All user inputs are sanitized and validated
- **Output Escaping**: XSS protection through proper output escaping
- **Prepared Statements**: SQL injection prevention using prepared statements
- **Data Type Validation**: Strict validation for different data types
- **File Upload Security**: Secure handling of file uploads

### Web Security Headers
- **Content Security Policy (CSP)**: Prevents XSS and code injection
- **X-XSS-Protection**: Browser-level XSS protection
- **X-Content-Type-Options**: MIME type sniffing protection
- **X-Frame-Options**: Clickjacking protection
- **Referrer-Policy**: Control referrer information
- **HSTS**: HTTP Strict Transport Security (for production)

### Environment & Configuration Security
- **Environment Variables**: Sensitive data stored in `.env` file
- **Database Credentials Protection**: Secure credential management
- **API Key Security**: Protected API keys and secrets
- **Configuration Isolation**: Separate development and production configs

### Server Security
- **Directory Listing Disabled**: Prevents directory browsing
- **Sensitive File Protection**: `.htaccess` rules for file protection
- **PHP Security Settings**: Optimized PHP configuration
- **Error Handling**: Secure error reporting without information disclosure

### Logging & Monitoring
- **Security Event Logging**: Track security-related events
- **Login Attempt Tracking**: Monitor and log login attempts
- **Error Logging**: Comprehensive error logging system
- **Audit Trail**: Track important system changes

## 🛠️ Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Composer (for dependency management)

### Setup Instructions

1. **Clone the Repository**
   ```bash
   git clone <repository-url>
   cd Payroll
   ```

2. **Install Dependencies**
   ```bash
   composer install
   ```

3. **Environment Configuration**
   ```bash
   cp .env.example .env
   ```
   Edit `.env` file with your database credentials and configuration:
   ```
   DB_HOST=localhost
   DB_NAME=payroll_db
   DB_USER=your_username
   DB_PASS=your_password
   APP_ENV=development
   APP_DEBUG=true
   SESSION_LIFETIME=1800
   ```

4. **Database Setup**
   - Create a MySQL database
   - Import the database schema (SQL file should be provided)
   - Update database credentials in `.env`

5. **Google OAuth Setup (Optional)**
   - Create a Google Cloud Project
   - Enable Google+ API
   - Create OAuth 2.0 credentials
   - Update `google-config.php` with your credentials

6. **File Permissions**
   ```bash
   chmod 755 payslips/
   chmod 755 reports/
   chmod 755 logs/
   ```

## 🚀 Usage

### For Administrators
1. **Login** with admin credentials
2. **Manage Employees**: Add, edit, and organize employee records
3. **Process Payroll**: Create pay periods and calculate payroll
4. **Generate Reports**: Access comprehensive payroll analytics
5. **System Management**: Manage users and system settings

### For Employees
1. **Login** with employee credentials or Google account
2. **View Payslips**: Access current and historical payslips
3. **Download Payslips**: Download PDF copies of payslips
4. **Update Profile**: Manage personal information

## 📁 Project Structure

```
Payroll/
├── assets/                 # Static assets (CSS, JS, images)
│   ├── css/
│   ├── js/
│   └── images/
├── config/                 # Configuration files
│   ├── config.php
│   └── database.php
├── includes/               # Shared PHP includes
│   ├── auth.php
│   ├── header.php
│   └── security.php
├── payslips/              # Generated payslip PDFs
├── reports/               # Generated report files
├── logs/                  # System logs
├── vendor/                # Composer dependencies
├── .env                   # Environment variables
├── .htaccess             # Apache configuration
├── composer.json         # PHP dependencies
└── *.php                 # Application files
```

## 🔧 Configuration

### Environment Variables
Key environment variables in `.env`:
- `DB_*`: Database connection settings
- `APP_ENV`: Application environment (development/production)
- `APP_DEBUG`: Debug mode toggle
- `SESSION_LIFETIME`: Session timeout in seconds

### Security Configuration
- **Production Setup**: Set `APP_ENV=production` and `APP_DEBUG=false`
- **HTTPS**: Enable HTTPS in production environments
- **File Permissions**: Ensure proper file and directory permissions
- **Database Security**: Use strong database credentials

## 📊 System Requirements

### Minimum Requirements
- **PHP**: 7.4+
- **MySQL**: 5.7+
- **Memory**: 512MB RAM
- **Storage**: 1GB available space

### Recommended Requirements
- **PHP**: 8.0+
- **MySQL**: 8.0+
- **Memory**: 1GB RAM
- **Storage**: 2GB available space

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📝 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🆘 Support

For support and questions:
- Create an issue in the repository
- Contact the system administrator
- Check the documentation

## 🔄 Updates & Maintenance

### Regular Maintenance
- Keep dependencies updated with `composer update`
- Monitor system logs regularly
- Backup database regularly
- Review security settings periodically

### Security Best Practices
1. Never commit `.env` file to version control
2. Use HTTPS in production environments
3. Regularly update PHP and dependencies
4. Monitor for security vulnerabilities
5. Implement proper backup strategies
6. Review access logs for suspicious activity

---

**EarnMOR Payroll System** - Making payroll management efficient, secure, and user-friendly.