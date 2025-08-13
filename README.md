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
- **Error Handling**: Custom error pages and logging
- **File Permissions**: Proper file and directory permissions

## 📋 Installation & Setup

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Composer
- Web server (Apache/Nginx)

### Installation Steps
1. Clone the repository
2. Run `composer install` to install dependencies
3. Copy `.env.example` to `.env` and configure your environment variables
4. Import the database schema from `database/schema.sql`
5. Configure your web server to point to the project directory
6. Access the system through your web browser

### Google OAuth Setup (Optional)
1. Create a project in Google Developer Console
2. Configure OAuth consent screen
3. Create OAuth client ID credentials
4. Add the credentials to your `.env` file

## 🛠️ Technologies Used

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5
- **PDF Generation**: mPDF
- **Email**: PHPMailer
- **Environment Variables**: phpdotenv
- **Authentication**: Custom + Google OAuth

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 📞 Support

For support, please contact the system administrator or open an issue in the repository.