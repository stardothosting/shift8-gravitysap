# Shift8 Integration for Gravity Forms and SAP Business One

A secure WordPress plugin that integrates Gravity Forms with SAP Business One, automatically creating Business Partner records from form submissions.

[![WordPress Plugin Version](https://img.shields.io/badge/WordPress-5.0%2B-blue)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-purple)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPLv3-green)](http://www.gnu.org/licenses/gpl-3.0.html)

## Features

* **Seamless Integration**: Direct integration with SAP Business One Service Layer API
* **Field Mapping**: Flexible mapping between Gravity Forms fields and SAP Business Partner fields
* **Contact Person Support**: Map form fields to Contact Persons tab in SAP B1
* **Automatic Form Validation**: Real-time validation against SAP field limits before submission
* **Security First**: Password encryption, input validation, and secure API communication
* **Real-time Testing**: Built-in connection and integration testing tools
* **WP-CLI Testing**: End-to-end testing command for developers
* **Comprehensive Logging**: Detailed debug logging with sensitive data protection
* **User-Friendly Interface**: Intuitive settings and configuration interface
* **Error Handling**: Robust error handling with detailed feedback

## Requirements

### WordPress Environment
* **WordPress**: 5.0 or higher
* **PHP**: 7.4 or higher
* **Gravity Forms**: Latest version required

### SAP Business One Environment
* **SAP Business One**: Version 9.3 or higher
* **Service Layer**: Properly configured and accessible
* **User Permissions**: SAP user with Business Partner creation rights
* **Numbering Series**: Configured for Business Partners in SAP B1

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
6. Select **Business Partner Code Prefix** (e.g., "E - EndUser")
7. Map form fields to SAP Business Partner fields
8. Click **Update Settings**

### Step 3: Test Integration
1. Click **Test Numbering Series** to verify SAP configuration
2. Use WP-CLI command to test end-to-end: `wp shift8-gravitysap test_submission --form_id=<id>`
3. Verify Business Partner creation in SAP Business One

## Field Mapping

### Main Business Partner Fields
| SAP Field | Description | Required | Max Length |
|-----------|-------------|----------|------------|
| `CardName` | Business Partner Name | Yes | ~100 chars |
| `EmailAddress` | Email Address | No | Email format |
| `Phone1` | Telephone 1 | No | ~20 chars |
| `Phone2` | Telephone 2 | No | ~20 chars |
| `Cellular` | Mobile Phone | No | ~20 chars |
| `Fax` | Fax Number | No | ~20 chars |
| `Website` | Website URL | No | URL format |

### Address Fields (BPAddresses - appears in General tab)
| SAP Field | Description | Required | Max Length |
|-----------|-------------|----------|------------|
| `BPAddresses.Street` | Street Address | No | ~100 chars |
| `BPAddresses.City` | City | No | ~25 chars |
| `BPAddresses.State` | State/Province | No | **3-4 chars** (codes only!) |
| `BPAddresses.ZipCode` | Zip/Postal Code | No | ~20 chars |
| `BPAddresses.Country` | Country | No | 2-letter code |

### Contact Person Fields (ContactEmployees - appears in Contact Persons tab)
| SAP Field | Description | Required | Max Length |
|-----------|-------------|----------|------------|
| `ContactEmployees.FirstName` | Contact First Name | No | ~50 chars |
| `ContactEmployees.LastName` | Contact Last Name | No | ~50 chars |
| `ContactEmployees.Phone1` | Contact Phone | No | ~20 chars |
| `ContactEmployees.E_Mail` | Contact Email | No | Email format |
| `ContactEmployees.Address` | Contact Address | No | ~254 chars |

**Important**: 
- SAP has strict field length limits. Use state codes ("CA" not "California") and country codes ("US" not "United States")
- BPAddresses data appears in the **General tab** of the Business Partner in SAP B1
- ContactEmployees data appears in the **Contact Persons tab** of the Business Partner in SAP B1
- You can map address data to either BPAddresses, ContactEmployees, or both depending on your needs

## WP-CLI Testing (Developers)

Test the complete form submission workflow with real SAP B1 integration:

```bash
wp shift8-gravitysap test_submission --form_id=3
```

This command will:
1. Create a test entry in Gravity Forms
2. Submit to SAP B1
3. Verify data in SAP B1 (field-by-field comparison)
4. Clean up test entry

Perfect for regression testing and debugging!

## Troubleshooting

### Field Length Errors
If you get "Value too long in property" errors:
1. Check that State field uses codes ("CA", "NY", "TX") not full names
2. Verify Country field uses 2-letter codes ("US", "CA", "GB")
3. Keep phone numbers under 20 characters
4. Keep street addresses under 100 characters
5. Keep city names under 25 characters

### Connection Issues
1. Verify SAP Service Layer is running
2. Check endpoint URL format (should end with `/b1s/v1/`)
3. Test credentials in SAP Business One
4. Review debug logs for detailed error information

### Numbering Series Issues
1. Configure numbering series in SAP B1 Administration
2. Go to **Administration > System Initialization > Document Numbering**
3. Set up series for Business Partners
4. Ensure default series are configured
5. Use the **Test Numbering Series** button to verify

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

## Testing

### Unit Tests
```bash
cd /path/to/shift8-gravitysap
vendor/bin/phpunit tests/unit/
```

**Test Coverage**: 90 tests, 214 assertions - All passing ✅

### End-to-End Testing
```bash
wp shift8-gravitysap test_submission --form_id=<id>
```

Tests real SAP B1 integration with data verification.

## Changelog

### 1.2.0
* **NEW: Contact Person Support** - Map form fields to Contact Persons tab in SAP B1
* Added 5 new Contact Person fields: FirstName, LastName, Phone, Email, Address
* **NEW: WP-CLI Testing Command** - End-to-end testing with `wp shift8-gravitysap test_submission`
* Enhanced field mapping with clear labels for BPAddresses vs ContactEmployees
* Improved SAP B1 data structure handling for addresses and contacts
* Comprehensive test suite: 90 unit tests + E2E integration testing
* Updated documentation with SAP B1 data structure best practices
* All tests passing with 214 assertions

### 1.1.1
* Enhanced field mapping system - validation is now completely dynamic based on user's field mapping configuration
* Improved validation efficiency - only validates fields that are actually mapped to SAP
* Verified WordPress.org plugin directory compliance - all automated checks now pass
* Code cleanup and optimization for better performance

### 1.1.0
* Expanded field mapping options - added Phone2, Mobile Phone, Fax, Website, and Country fields
* Better alignment with SAP Business One field structure (Telephone 1, Telephone 2, etc.)
* Enhanced test data with examples for all new fields
* Improved field mapping interface for comprehensive Business Partner data
* Fixed SAP field length compliance - test data now uses proper state codes (CA vs Test State)
* Added comprehensive field length documentation and troubleshooting guide
* **NEW: Automatic form validation** - validates all mapped fields against SAP limits before submission
* Enhanced field mapping interface now shows SAP field limits and validation hints
* Prevents "Value too long" SAP errors by catching field length issues in the form

### 1.0.9
* Fixed critical field mapping bug - all mapped fields now properly sent to SAP Business One
* Email, phone, and address fields will now be correctly populated in SAP
* Improved debug logging for Business Partner creation process
* All field mapping functionality now works as designed

### 1.0.8
* Fixed all WordPress.org plugin checker compliance issues
* Updated text domain to 'shift8-gravity-forms-sap-b1-integration'
* Removed all development functions (error_log) for production compliance
* Comprehensive testing framework with 65 automated tests
* Removed WordPress.org directory assets from plugin code
* Ready for WordPress.org plugin directory submission

### 1.0.7
* Updated plugin name to comply with WordPress.org trademark guidelines
* Changed display name to 'Shift8 Integration for Gravity Forms and SAP Business One'
* Removed discouraged load_plugin_textdomain() function call
* All functionality remains unchanged

### 1.0.0
* Initial release
* SAP Business One Service Layer integration
* Gravity Forms field mapping
* Password encryption and security features
* Debug logging with sensitive data protection
* Connection and integration testing tools

## Support

For support and documentation:
- Review debug logs for error details
- Use built-in connection testing tools
- Check SAP Service Layer documentation
- Contact: https://www.shift8web.ca

## License

GNU General Public License v3.0 or later - see [LICENSE](http://www.gnu.org/licenses/gpl-3.0.html)

## Contributing

This plugin is developed and maintained by [Shift8 Web](https://shift8web.ca).

---

**Note**: This plugin requires both Gravity Forms and SAP Business One with Service Layer configured. It is designed for WordPress sites that need to integrate form submissions directly into SAP B1 as Business Partners.