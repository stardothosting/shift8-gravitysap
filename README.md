# Shift8 Gravity Forms SAP B1 Integration

A WordPress plugin that integrates Gravity Forms with SAP Business One using the Gravity Forms Add-On Framework to automatically create Business Partner records from form submissions.

## Description

This plugin allows you to seamlessly connect your WordPress Gravity Forms with SAP Business One, automatically creating Business Partner records when forms are submitted. It uses the SAP Service Layer API for secure and reliable integration.

## Features

- **Gravity Forms Add-On Framework**: Full integration with Gravity Forms using the official add-on framework
- **SAP Service Layer Integration**: Connects directly to SAP Business One via the Service Layer API
- **Field Mapping**: Map Gravity Form fields to SAP Business Partner fields including:
  - CardName (Business Partner Name)
  - EmailAddress
  - Phone1
  - CardType (Customer, Vendor, Lead)
  - BlockSendingMarketingContent (Marketing opt-out)
  - Address fields (Street, City, Country, State, Zip)
- **Secure Configuration**: Encrypted password storage and secure API communication
- **Comprehensive Logging**: Detailed logging with file rotation and management
- **Shift8 Menu Integration**: Appears under the existing Shift8 admin menu
- **Connection Testing**: Built-in SAP connection testing tool
- **Error Handling**: Robust error handling with detailed logging and user feedback

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Gravity Forms 2.4 or higher
- SAP Business One with Service Layer enabled
- cURL extension enabled
- JSON extension enabled

## Installation

### Method 1: WordPress Admin (Recommended)

1. Download the plugin zip file
2. In your WordPress admin, go to **Plugins > Add New**
3. Click **Upload Plugin** and select the zip file
4. Click **Install Now** and then **Activate**

### Method 2: Manual Installation

1. Upload the `shift8-gravitysap` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the **Plugins** menu in WordPress

### Method 3: Development Installation

1. Clone this repository into your plugins directory:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/your-repo/shift8-gravitysap.git
   ```
2. Install dependencies:
   ```bash
   cd shift8-gravitysap
   composer install
   ```
3. Activate the plugin in WordPress admin

## Configuration

### 1. SAP Service Layer Setup

Before configuring the plugin, ensure your SAP Business One Service Layer is properly set up:

1. **Enable Service Layer** in SAP Business One
2. **Configure HTTPS** (recommended for production)
3. **Create a dedicated SAP user** with appropriate permissions for Business Partner creation
4. **Note your Service Layer endpoint** (e.g., `https://your-sap-server:50000/b1s/v1/`)

### 2. Plugin Configuration

1. Navigate to **Shift8 > SAP Integration** in your WordPress admin
2. Fill in the required SAP connection details:
   - **SAP Service Layer Endpoint URL**: Your SAP Service Layer URL
   - **SAP Company Database**: Your company database identifier
   - **SAP Username**: SAP user with Business Partner creation permissions
   - **SAP Password**: Password for the SAP user (encrypted and stored securely)
   - **Enable Logging**: Toggle logging for debugging and monitoring

3. Click **Test SAP Connection** to verify your settings
4. Save the configuration

### 3. Gravity Forms Feed Setup

1. Edit any Gravity Form where you want SAP integration
2. Go to **Settings > SAP B1 Integration**
3. Click **Add New** to create a new feed
4. Configure the feed:
   - **Feed Name**: A descriptive name for this integration
   - **Business Partner Type**: Choose Customer, Vendor, or Lead
   - **Field Mapping**: Map form fields to SAP Business Partner fields
   - **Conditions**: Set conditions for when the integration should run

5. Save the feed

## Field Mapping

The plugin supports mapping the following SAP Business Partner fields:

| SAP Field | Description | Required |
|-----------|-------------|----------|
| CardName | Business Partner Name | Yes |
| EmailAddress | Email Address | No |
| Phone1 | Phone Number | No |
| BlockSendingMarketingContent | Marketing Opt-out (Yes/No) | No |
| BPAddresses.Street | Street Address | No |
| BPAddresses.City | City | No |
| BPAddresses.Country | Country | No |
| BPAddresses.State | State/Province | No |
| BPAddresses.ZipCode | Zip/Postal Code | No |

## Usage

### Basic Workflow

1. **User submits form**: A user fills out and submits a Gravity Form
2. **Feed evaluation**: The plugin checks if any SAP feeds are configured for the form
3. **Condition checking**: If conditions are met, the integration proceeds
4. **Field mapping**: Form data is mapped to SAP Business Partner structure
5. **SAP authentication**: Plugin authenticates with SAP Service Layer
6. **Business Partner creation**: New Business Partner record is created in SAP
7. **Logging**: Success or failure is logged and noted on the form entry

### Example Form Setup

For a contact form that creates SAP customers:

1. **Form Fields**:
   - Name (Text)
   - Email (Email)
   - Phone (Phone)
   - Company (Text)
   - Address (Address)
   - Marketing Emails (Checkbox)

2. **Field Mapping**:
   - Name → CardName
   - Email → EmailAddress
   - Phone → Phone1
   - Address (Street) → BPAddresses.Street
   - Address (City) → BPAddresses.City
   - Marketing Emails → BlockSendingMarketingContent

## Logging

The plugin includes comprehensive logging functionality:

### Log Features
- **Automatic rotation**: Logs are rotated when they exceed 5MB
- **Multiple levels**: ERROR, WARNING, INFO, DEBUG
- **WordPress integration**: Errors also logged to WordPress error log when WP_DEBUG is enabled
- **Admin interface**: View and clear logs from the admin interface

### Log File Location
Logs are stored in: `wp-content/plugins/shift8-gravitysap/shift8-gravitysap.log`

### Log Management
- Access logs via **Shift8 > SAP Integration > Log Information**
- View recent entries in the admin interface
- Clear logs when needed
- Automatic cleanup of old backup files

## Security Considerations

### Password Security
- SAP passwords are encrypted before storage
- Passwords are never logged or displayed in plain text
- Connection testing uses temporary decryption

### API Security
- Uses HTTPS for SAP connections (recommended)
- Session management with automatic logout
- Proper error handling to prevent information leakage

### WordPress Security
- Nonce verification for admin forms
- Capability checks for admin access
- Sanitization of all user inputs
- Escaping of all outputs

## Troubleshooting

### Common Issues

#### Connection Failed
- **Check SAP Service Layer URL**: Ensure the endpoint is correct and accessible
- **Verify credentials**: Test SAP username and password directly in SAP
- **Network connectivity**: Ensure WordPress server can reach SAP server
- **SSL certificates**: Check if SSL verification is causing issues

#### Business Partner Not Created
- **Check logs**: Review the plugin logs for detailed error messages
- **Field validation**: Ensure required fields (CardName) are mapped
- **SAP permissions**: Verify the SAP user has Business Partner creation rights
- **Field data**: Check that form submissions contain valid data

#### Permission Errors
- **SAP user rights**: Ensure the SAP user has appropriate permissions
- **Company database**: Verify the correct company database is specified
- **Service Layer access**: Check if the user can access the Service Layer

### Debug Mode

Enable debug logging by setting `WP_DEBUG` to `true` in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

This will enable detailed debug logging and WordPress error logging.

## Support

### Documentation
- Check this README for comprehensive setup instructions
- Review the plugin settings tooltips for field-specific help
- Examine log files for detailed error information

### Requirements Check
Ensure your environment meets all requirements:
- WordPress version compatibility
- PHP version and extensions
- Gravity Forms installation and version
- SAP Business One Service Layer configuration

## Development

### Project Structure
```
shift8-gravitysap/
├── admin/                          # Admin interface classes
│   └── class-shift8-gravitysap-admin.php
├── assets/                         # CSS, JS, and image assets
│   └── js/
│       └── admin.js
├── includes/                       # Core plugin classes
│   ├── class-gf-shift8-gravitysap-addon.php
│   ├── class-shift8-gravitysap-logger.php
│   └── class-shift8-gravitysap-sap-service.php
├── languages/                      # Translation files
├── vendor/                         # Composer dependencies
├── composer.json                   # Composer configuration
├── shift8-gravitysap.php          # Main plugin file
└── README.md                       # This file
```

### Contributing
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

### Coding Standards
- Follow WordPress Coding Standards
- Use PSR-4 autoloading for new classes
- Include proper PHPDoc comments
- Sanitize inputs and escape outputs
- Use WordPress APIs and functions where available

## Changelog

### Version 1.0.0
- Initial release
- Gravity Forms Add-On Framework integration
- SAP Service Layer API integration
- Business Partner creation functionality
- Field mapping interface
- Comprehensive logging system
- Shift8 menu integration
- Security and error handling implementation

## License

This plugin is licensed under the GPL v3 or later.

```
Copyright 2025 Shift8 Web

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
```

## Credits

**Author**: Shift8 Web  
**Website**: https://www.shift8web.ca  
**Email**: info@shift8web.ca

Built with the Gravity Forms Add-On Framework and following WordPress plugin development best practices. 