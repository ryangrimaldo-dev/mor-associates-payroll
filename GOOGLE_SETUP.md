# Google OAuth Setup Instructions

## Step 1: Create a Google Cloud Project

1. Go to the [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Navigate to "APIs & Services" > "OAuth consent screen"
4. Select "External" user type and click "Create"
5. Fill in the required information (App name, User support email, Developer contact information)
6. Click "Save and Continue"
7. Add the scopes: `.../auth/userinfo.email` and `.../auth/userinfo.profile`
8. Click "Save and Continue", then "Back to Dashboard"

## Step 2: Create OAuth Client ID

1. Navigate to "APIs & Services" > "Credentials"
2. Click "Create Credentials" > "OAuth client ID"
3. Select "Web application" as the Application type
4. Enter a name for your client
5. Add authorized JavaScript origins: `http://localhost` or your domain
6. Add authorized redirect URIs: `http://localhost/Payroll/google-callback.php` (adjust for your domain)
7. Click "Create"
8. Note your Client ID and Client Secret

## Step 3: Configure the Payroll System

1. Open the `google-config.php` file in your Payroll System
2. Replace the placeholder values with your actual credentials:

```php
// Google API configuration
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID'); // Replace with your Client ID
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET'); // Replace with your Client Secret
define('GOOGLE_REDIRECT_URL', 'http://localhost/Payroll/google-callback.php'); // Adjust if needed
```

## Step 4: Install Google API Client Library

Run the following command in your project directory:

```bash
composer update
```

This will install the Google API Client library that was added to your composer.json file.

## Step 5: Test the Integration

1. Go to your login page
2. Click the "Sign in with Google" button
3. Complete the Google authentication flow
4. You should be redirected back to your application and logged in

## Troubleshooting

- If you get redirect errors, make sure your redirect URI exactly matches what you configured in the Google Cloud Console
- Check that your Client ID and Client Secret are correctly entered in the `google-config.php` file
- Ensure that the Google API Client library is properly installed via Composer
- Check PHP error logs for any issues during the authentication process