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
- **Automated Payroll Calculation**: Calculate gross pay, deductions, and net pay with precision
- **Pay Period Management**: Supports 15th and 30th/31st period combinations for accurate monthly calculations
- **Multiple Rate Types**: Support for daily and monthly salary structures
- **Automatic Deductions**: 
  - **SSS Contributions**: Automatic calculation with MPF-ER and MPF-EE components
  - **PhilHealth Contributions**: Accurate premium calculations based on salary brackets
  - **Pag-IBIG Contributions**: Member savings and loan deductions
  - **Withholding Tax**: Automated tax calculations based on BIR tax tables
- **Loan Management**:
  - MPII Savings (HDMF Loan) tracking
  - Calamity Loan processing
  - Multi-Purpose Loan management
- **Overtime System**:
  - Multiple overtime types (ordinary day, rest day, holiday, etc.)
  - Accurate rate calculations
  - Detailed overtime entry and tracking
- **Payroll Period Combination**: Automatic combination of 15th and 30th period basic pay for accurate monthly deductions
- **Bulk Payroll Processing**: Process payroll for multiple employees simultaneously

#### Payslip Management
- **Digital Payslips**: Generate professional PDF payslips
- **Payslip Download**: Employees can download their payslips
- **Email Distribution**: Automatically email payslips to employees
- **Payslip History**: Access historical payslip records
- **Custom Payslip Numbers**: Unique identification for each payslip

#### Reporting & Analytics
- **Compliance Reports**:
  - **SSS Report**: Detailed SSS contributions with MPF-ER and MPF-EE calculations
  - **Pag-IBIG Report**: Complete Pag-IBIG contributions and loan deductions
  - **PhilHealth Report**: Premium contributions and matching
  - **BIR Tax Report**: Tax withholding and remittance details
- **Monthly Reports**: Generate comprehensive monthly payroll reports
- **Annual Reports**: Year-end payroll summaries and analytics (BIR Form 2316)
- **Department Reports**: Payroll analysis by department with cost center breakdown
- **Employee Reports**: Individual payroll history and earnings statements
- **Export Functionality**: Export reports to PDF and CSV formats
- **Real-time Analytics**: Interactive dashboard with payroll metrics and trends

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
- **Content Security Policy (CSP)**: Strict policy preventing XSS and code injection
- **X-XSS-Protection**: Browser-level XSS protection with block mode
- **X-Content-Type-Options**: MIME type sniffing protection (nosniff)
- **X-Frame-Options**: SAMEORIGIN to prevent clickjacking
- **Referrer-Policy**: Strict origin-when-cross-origin
- **HSTS**: HTTP Strict Transport Security with preload and includeSubDomains
- **Feature-Policy**: Controls browser features and APIs
- **Expect-CT**: Certificate Transparency enforcement

### Environment & Configuration Security
- **Environment Variables**: Sensitive data stored in `.env` file
- **Database Credentials Protection**: Secure credential management
- **API Key Security**: Protected API keys and secrets
- **Configuration Isolation**: Separate development and production configs

### Server Security
- **Directory Listing Disabled**: Prevents directory browsing
- **Error Handling**: Custom error pages with secure logging
- **File Permissions**: Strict file and directory permissions (750 for directories, 640 for files)
- **SQL Injection Protection**: Prepared statements and parameterized queries
- **CSRF Protection**: Token-based protection on all forms
- **Input Validation**: Strict validation of all user inputs
- **Output Encoding**: Context-aware output encoding to prevent XSS
- **Secure Session Handling**:
  - HTTP-only and Secure flags on cookies
  - Session timeout and regeneration
  - Session fixation protection

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