=== Shift8 Gravity Forms SAP B1 Integration ===
* Contributors: shift8
* Donate link: https://shift8web.ca
* Tags: gravity forms, sap, business one, integration, crm
* Requires at least: 5.0
* Tested up to: 6.8
* Stable tag: 1.0.4
* Requires PHP: 7.4
* License: GPLv3
* License URI: http://www.gnu.org/licenses/gpl-3.0.html

Integrates Gravity Forms with SAP Business One to automatically create Business Partner records from form submissions.

== Description ==

A secure WordPress plugin that integrates Gravity Forms with SAP Business One, automatically creating Business Partner records from form submissions.

= Features =

* **Seamless Integration**: Direct integration with SAP Business One Service Layer API
* **Field Mapping**: Flexible mapping between Gravity Forms fields and SAP Business Partner fields
* **Security First**: Password encryption, input validation, and secure API communication
* **Real-time Testing**: Built-in connection and integration testing tools
* **Comprehensive Logging**: Detailed debug logging with sensitive data protection
* **User-Friendly Interface**: Intuitive settings and configuration interface
* **Error Handling**: Robust error handling with detailed feedback

== Installation ==

1. Download the plugin ZIP file
2. Go to **WordPress Admin > Plugins > Add New**
3. Click **Upload Plugin** and select the ZIP file
4. Click **Install Now** and then **Activate**

== Frequently Asked Questions ==

= What are the requirements? =

**WordPress Environment:**
* WordPress 5.0 or higher
* PHP 7.4 or higher
* Gravity Forms plugin (latest version)

**SAP Business One Environment:**
* SAP Business One version 9.3 or higher
* Service Layer properly configured and accessible
* SAP user with Business Partner creation rights
* Numbering Series configured for Business Partners in SAP B1

= How do I configure the plugin? =

1. Navigate to **WordPress Admin > Shift8 > Gravity SAP**
2. Enter your SAP connection details
3. Test the connection
4. Go to your Gravity Form settings and enable SAP integration
5. Map your form fields to SAP Business Partner fields

= Why am I getting connection errors? =

Common solutions:
1. Verify SAP Service Layer is running
2. Check endpoint URL format (should end with /b1s/v1/)
3. Test credentials in SAP Business One directly
4. Review debug logs for detailed error information

= How do I set up numbering series in SAP? =

1. Go to SAP B1 Administration > System Initialization > Document Numbering
2. Set up series for Business Partners
3. Ensure default series are configured
4. Test using the built-in numbering series test tool

== Screenshots ==

1. Main settings page with SAP connection configuration
2. Gravity Forms integration settings with field mapping
3. Test connection and integration tools
4. Debug logging interface

== Changelog ==

= 1.0.0 =
* Initial release
* SAP Business One Service Layer integration
* Gravity Forms field mapping
* Password encryption and security features
* Debug logging with sensitive data protection
* Connection and integration testing tools

== Upgrade Notice ==

= 1.0.0 =
Initial release of the Shift8 Gravity Forms SAP B1 Integration plugin.

---

# Shift8 Gravity Forms SAP B1 Integration

A secure WordPress plugin that integrates Gravity Forms with SAP Business One, automatically creating Business Partner records from form submissions.

## Features

- **Seamless Integration**: Direct integration with SAP Business One Service Layer API
- **Field Mapping**: Flexible mapping between Gravity Forms fields and SAP Business Partner fields
- **Security First**: Password encryption, input validation, and secure API communication
- **Real-time Testing**: Built-in connection and integration testing tools
- **Comprehensive Logging**: Detailed debug logging with sensitive data protection
- **User-Friendly Interface**: Intuitive settings and configuration interface
- **Error Handling**: Robust error handling with detailed feedback

## Requirements

### WordPress Environment
- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **Gravity Forms**: Latest version required

### SAP Business One Environment
- **SAP Business One**: Version 9.3 or higher
- **Service Layer**: Properly configured and accessible
- **User Permissions**: SAP user with Business Partner creation rights
- **Numbering Series**: Configured for Business Partners in SAP B1

## Installation

1. Download the plugin ZIP file
2. Go to **WordPress Admin > Plugins > Add New**
3. Click **Upload Plugin** and select the ZIP file
4. Click **Install Now** and then **Activate**

## Configuration

### Step 1: Configure SAP Connection
1. Navigate to **WordPress Admin > Shift8 > Gravity SAP**
2. Enter your SAP connection details:
   - **SAP Service Layer Endpoint**: `https://your-sap-server:50000/b1s/v1/`
   - **Company Database**: Your SAP company database name
   - **Username**: SAP user with Business Partner permissions
   - **Password**: SAP user password (automatically encrypted)
3. Enable **Debug Logging** if needed for troubleshooting
4. Click **Save Settings**
5. Click **Test SAP Connection** to verify connectivity

### Step 2: Configure Gravity Forms Integration
1. Go to **WordPress Admin > Forms** and select a form
2. Click **Settings > SAP Integration**
3. Check **Enable SAP Integration**
4. Enter a **Feed Name** for identification
5. Select **Business Partner Type** (Customer, Vendor, or Lead)
6. Map form fields to SAP Business Partner fields
7. Click **Update Settings**

### Step 3: Test Integration
1. Click **Test Numbering Series** to verify SAP configuration
2. Enter test data and click **Test Integration**
3. Verify Business Partner creation in SAP Business One

## Field Mapping

| SAP Field | Description | Required |
|-----------|-------------|----------|
| `CardName` | Business Partner Name | Yes |
| `EmailAddress` | Email Address | No |
| `Phone1` | Primary Phone Number | No |
| `BPAddresses.Street` | Street Address | No |
| `BPAddresses.City` | City | No |
| `BPAddresses.State` | State/Province | No |
| `BPAddresses.ZipCode` | Zip/Postal Code | No |

## Troubleshooting

### Connection Issues
1. Verify SAP Service Layer is running
2. Check endpoint URL format
3. Test credentials in SAP Business One
4. Review debug logs for detailed error information

### Numbering Series Issues
1. Configure numbering series in SAP B1 Administration
2. Go to **Administration > System Initialization > Document Numbering**
3. Set up series for Business Partners
4. Ensure default series are configured

## Security Features

- **Password Encryption**: All passwords encrypted using WordPress salts
- **Input Validation**: All data sanitized and validated
- **Secure Communication**: HTTPS API communication
- **Access Control**: Admin-only access with capability checks
- **Debug Protection**: Sensitive data automatically redacted from logs

## How It Works

1. User submits Gravity Form with mapped fields
2. Plugin validates form has SAP integration enabled
3. Data mapping occurs between form fields and SAP fields
4. SAP authentication using encrypted credentials
5. Business Partner creation via SAP Service Layer API
6. Success/error logging and entry notes

## Support

For support and documentation:
- Review debug logs for error details
- Use built-in connection testing tools
- Check SAP Service Layer documentation
- Contact: https://www.shift8web.ca

## License

GNU General Public License v3.0 or later 