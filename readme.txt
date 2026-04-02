=== Shift8 Integration for Gravity Forms and SAP Business One ===
* Contributors: shift8
* Donate link: https://shift8web.ca
* Tags: gravity forms, sap, business one, integration, crm
* Requires at least: 5.0
* Tested up to: 6.8
* Stable tag: 1.6.0
* Requires PHP: 7.4
* License: GPLv3
* License URI: http://www.gnu.org/licenses/gpl-3.0.html

Integrates Gravity Forms with SAP Business One to automatically create Business Partner records from form submissions.

== Description ==

A secure WordPress plugin that integrates Gravity Forms with SAP Business One, automatically creating Business Partner records from form submissions.

For a complete setup guide and technical walkthrough, see our blog post: [How to integrate SAP B1 (Business One) into WordPress Gravity Forms](https://shift8web.ca/how-to-integrate-sap-b1-business-one-into-wordpress-gravity-forms/)

= Features =

* **Seamless Integration**: Direct integration with SAP Business One Service Layer API
* **Field Mapping**: Flexible mapping between Gravity Forms fields and SAP Business Partner fields
* **Contact Person Support**: Map form fields to Contact Persons tab in SAP B1
* **Sales Quotation Creation**: Automatically create Sales Quotations with checkbox-based line item mapping
* **Duplicate Detection**: Prevent duplicate Business Partners using email OR name+address matching
* **Automatic Form Validation**: Real-time validation against SAP field limits before submission
* **Security First**: Password encryption, input validation, and secure API communication
* **Real-time Testing**: Built-in connection and integration testing tools
* **WP-CLI Testing**: End-to-end testing and SAP B1 query commands for developers
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

= How do I use checkbox fields for Sales Quotation line items? =

Checkbox fields are perfect for product selection forms. Each checkbox option becomes a separate mappable field:

**Example:** If you have a checkbox field "Select Products" with options "Product A", "Product B", "Product C", they will appear in the field mapping as:
* "Select Products → Product A" (Field ID: 15.1)
* "Select Products → Product B" (Field ID: 15.2)
* "Select Products → Product C" (Field ID: 15.3)

**To configure:**
1. Enable "Automatically create a sales quotation in SAP B1" in form settings
2. Click "Load ItemCodes from SAP B1" to load available products
3. Map each DocumentLines slot to a specific checkbox option
4. Select the corresponding SAP ItemCode for each slot

**How it works:**
* Checked boxes → Line items are created in the Sales Quotation
* Unchecked boxes → Skipped (no line item)
* The quotation is automatically linked to the Business Partner

This is ideal for sample request forms, multi-product orders, and service selection forms.

= How do I prevent duplicate Business Partners? =

Enable **Check for existing Business Partner** in your form's SAP Integration settings. The plugin uses an OR strategy -- a match on either condition prevents duplicates:

* **Email match** (checked first): EmailAddress matches an existing BP in SAP
* **Name + Address match** (fallback): Business Partner Name (case-insensitive) + Country + Postal/ZIP Code

If a match is found, the plugin uses the existing Business Partner instead of creating a duplicate. You can test this with WP-CLI:

`wp shift8-gravitysap-bp-lookup search --email="info@example.com"`
`wp shift8-gravitysap-bp-lookup search --name="Test Company" --country="CA" --postal="M5V 1A1"`
`wp shift8-gravitysap-bp-lookup run_tests`

= How are Contact Persons linked to Sales Quotations? =

When a Business Partner match is found (or a new one is created), the plugin automatically:

1. Adds a Contact Person to the Business Partner using the form's contact data
2. Retrieves the Contact Person's InternalCode from SAP
3. Links the Contact Person to the Sales Quotation via SAP's ContactPersonCode field

**Result in SAP B1:**
* Contact Person appears in the Business Partner's "Contact Persons" tab
* Sales Quotation dropdown shows the correct Contact Person selected
* Contact Person is properly linked for subsequent documents

**Technical Note:** SAP B1's ContactPersonCode field requires the numeric InternalCode, not the text Name. The plugin handles this automatically.

== Screenshots ==

1. Main settings page with SAP connection configuration
2. Gravity Forms integration settings with field mapping
3. Test connection and integration tools
4. Debug logging interface

== Changelog ==

= 1.6.0 =
* **NEW**: Email-based duplicate Business Partner detection - matches if email OR (name+country+postal) already exists in SAP
* **NEW**: Automated test suite for duplicate detection (`wp shift8-gravitysap-bp-lookup run_tests`) reads scenarios from gitignored config
* **IMPROVED**: Email check runs first as most reliable unique identifier before name+address fallback
* **IMPROVED**: WP-CLI `bp-lookup search` now supports `--email` parameter for email-only or combined lookups
* **IMPROVED**: BP lookup result includes `match_type` field for match transparency
* **IMPROVED**: Updated documentation for duplicate detection, field mapping, and manual test workflows

= 1.5.0 =
* **NEW**: WP-CLI `sap-query` command for direct SAP B1 record queries (bp, quotation, entry, search)
* **NEW**: SAP identifiers stored in GF entry meta for cross-referencing (bp_matched, contact_name, contact_internal_code)
* **NEW**: Remarks/Notes (FreeText) field available in Business Partner field mapping
* **IMPROVED**: Entry list SAP Status column shows BP match type, Quotation DocNum, and Contact name
* **IMPROVED**: Comprehensive WP-CLI documentation for manual testing and verification workflows

= 1.4.8 =
* **CHANGED**: Switched to synchronous processing (standard GF add-on approach)
* **REMOVED**: Async loopback processing - was unreliable on many hosting environments
* **IMPROVED**: Now works reliably on ALL hosting environments
* **SIMPLIFIED**: Removed unnecessary complexity - follows GF best practices

= 1.4.7 =
* Synchronous Processing option (superseded by 1.4.8)

= 1.4.6 =
* **CHANGED**: Debug logging now requires both WP_DEBUG=true AND plugin debug setting enabled

= 1.4.5 =
* **NEW**: Robust debug logging system - requires both WP_DEBUG and plugin debug setting
* **NEW**: Dedicated log file at `wp-content/uploads/shift8-gravitysap-debug.log`
* **NEW**: Comprehensive logging throughout form submission and async processing
* **IMPROVED**: Logs include step-by-step visibility for troubleshooting
* **IMPROVED**: Fallback to PHP error_log if file write fails
* **IMPROVED**: Plugin init logs diagnostic info (file paths, permissions, versions)

= 1.4.4 =
* **NEW**: Duplicate contact detection - checks if contact already exists before adding
* **NEW**: `find_existing_contact()` method for case-insensitive name + email matching
* **IMPROVED**: Reuses existing contacts instead of creating duplicates on repeat submissions
* **TESTING**: Added 10 new tests for contact duplicate detection (edge cases included)
* **TESTING**: Now 138 tests with 306 assertions - All passing

= 1.4.3 =
* **FIX**: Contact Person now correctly linked to Sales Quotation using SAP's InternalCode (numeric) instead of Name (string)
* **IMPROVED**: After adding Contact Person to existing BP, plugin now fetches BP to retrieve the contact's InternalCode
* **IMPROVED**: Sales Quotation ContactPersonCode field now uses correct integer value for proper SAP B1 linking
* **TESTING**: Added 3 additional Contact Person tests (InternalCode retrieval, existing Name field, first name only)
* **TESTING**: Now 128 tests with 291 assertions - All passing
* **DOCUMENTATION**: Added comprehensive contactPersonLinking section to .cursorrules

= 1.4.2 =
* **NEW**: Contact Person now added to existing Business Partner when match is found
* **NEW**: Contact Person linked to Sales Quotation via ContactPersonCode field
* **IMPROVED**: SAP Service class now includes `add_contact_to_business_partner()` and `get_business_partner()` methods
* **TESTING**: Added 6 new unit tests for Contact Person functionality
* **TESTING**: Now 125 tests with 283 assertions - All passing

= 1.4.1 =
* **TESTING**: Added comprehensive test coverage for async processing (9 new tests)
* **TESTING**: Added comprehensive test coverage for Business Partner lookup (10 new tests)
* **TESTING**: Now 119 tests with 272 assertions - All passing
* **DOCUMENTATION**: Updated .cursorrules with async processing, BP lookup, and testing patterns

= 1.4.0 =
* **NEW**: Async processing for form submissions - SAP integration now runs in a non-blocking background request
* **NEW**: "Check for Existing Business Partner" now integrated into form submission flow
* **NEW**: Test Integration button now supports existing BP lookup when enabled
* **IMPROVED**: Form submissions no longer block on SAP API response time (1-2 seconds faster)
* **IMPROVED**: Centralized Business Partner lookup logic for code reuse across WP-CLI, form processing, and test integration

= 1.3.9 =
* **NEW**: Added "Check for Existing Business Partner" toggle setting
* **NEW**: Added WP-CLI command `wp shift8-gravitysap-bp-lookup search` to test duplicate detection
* **NEW**: Added WP-CLI command `wp shift8-gravitysap-bp-lookup benchmark` to measure SAP query performance
* **ENHANCEMENT**: Duplicate detection matches on Business Partner Name (case-insensitive), Country, and Postal Code

= 1.3.8 =
* **FIX**: Test data values now properly save with "Update Settings" button
* **ENHANCEMENT**: Test data fields moved into main settings form for consistent save behavior

= 1.3.7 =
* **FIX**: Fixed validation error display - fields now properly highlighted when SAP validation fails
* **ENHANCEMENT**: Improved validation error messages with detailed information (submitted value, character count, max allowed, hints)
* **ENHANCEMENT**: Added summary of all SAP validation errors at top of form
* **NEW**: Added WP-CLI commands to query SAP master data (groups, currencies, pricelists)

= 1.3.6 =
* **FIX**: Removed required validation from CardName field (all fields now optional)

= 1.3.5 =
* **FIX**: Removed URL format validation from Website field (accepts any text now)

= 1.3.4 =
* **NEW**: Added Business Partner Group (GroupCode) field mapping
* **NEW**: Added Currency field mapping
* **NEW**: Added Price List (PriceListNum) field mapping from Payment Terms
* **STABILITY**: All 99 tests passing with 231 assertions

= 1.3.3 =
* **FIX**: Fixed fatal error from undefined methods (removed orphaned debug handlers)
* **FIX**: Removed orphaned debug/test buttons from settings page
* **FIX**: Test Integration now correctly uses Business Partner Type and Code Prefix settings
* **FIX**: Improved entries list column display using field mapping for Name, Email, Country
* **STABILITY**: All 99 tests passing with 231 assertions

= 1.3.2 =
* **FIX**: Fixed critical error when viewing Gravity Forms entries list (undefined method)
* **FIX**: Updated display_sap_status_column to only handle SAP-specific columns
* **STABILITY**: All 99 tests passing with 231 assertions

= 1.3.1 =
* **FIX**: Removed temporary debug logging statements
* **CLEANUP**: Removed scenario-specific business logic functions
* **STABILITY**: Code cleanup and optimization for production deployment

= 1.3.0 =
* **NEW FEATURE**: Sales Quotation creation with checkbox field mapping support
* **ENHANCEMENT**: Dynamic line item mapping for Sales Quotations
* **ENHANCEMENT**: Improved SAP B1 API integration and data handling
* **ENHANCEMENT**: Enhanced user interface and progress indicators
* **FIX**: Fixed checkbox sub-field value detection for Sales Quotations
* **FIX**: Fixed field mapping persistence for sub-field IDs (e.g., 15.1, 15.2)
* **FIX**: Various bug fixes and performance improvements
* **TESTING**: Added 6 new tests for checkbox field mapping functionality
* **TESTING**: Expanded test coverage with additional unit tests
* **STABILITY**: Code optimization and cleanup for production deployment

= 1.2.5 =
* **NEW FEATURE**: Implemented full pagination support for SAP B1 Items API
* **FIX**: Resolved issue where only 20 ItemCodes were loading (now loads all 1,995+ items)
* **ENHANCEMENT**: Added pagination loop with $skip parameter to fetch all items across multiple pages
* **ENHANCEMENT**: Added spinning wheel animation and progress indicators for long-running operations
* **ENHANCEMENT**: Extended AJAX timeout to 60 seconds and PHP execution time to 120 seconds
* **ENHANCEMENT**: Added comprehensive debug logging for pagination progress
* **ENHANCEMENT**: Implemented caching to avoid repeated pagination on subsequent loads
* **FIX**: Removed duplicate create_sales_quotation() method in SAP service class
* **TESTING**: Added 8 new pagination tests - all 93 tests passing (213 assertions)
* **DOCUMENTATION**: Updated .cursorrules with apiPagination section documenting SAP B1 pagination requirements
* **PERFORMANCE**: Load time 30-60 seconds for first load, instant for cached loads
* **STABILITY**: Safety limit of 3,000 items to prevent infinite loops

= 1.2.4 =
* **TESTING**: All 85 unit tests pass successfully - comprehensive test coverage verified
* **CLEANUP**: Removed all temporary debug files from plugin and project directories
* **MAINTENANCE**: Code cleanup and optimization for production deployment
* **VERIFICATION**: End-to-end functionality testing completed successfully
* **STABILITY**: Plugin ready for production use with full feature set

= 1.2.3 =
* **NEW FEATURE**: Added custom pricing for Sales Quotation line items
* **NEW**: Price input fields for each ItemCode mapping with optional override functionality
* **NEW**: Flexible pricing system - use SAP item master pricing or set custom prices per line item
* **ENHANCEMENT**: Enhanced WP-CLI test command to display price configuration (SAP Auto vs Custom)
* **ENHANCEMENT**: Added detailed logging for line item creation with price source tracking
* **ENHANCEMENT**: Improved settings UI with clear price configuration options
* **FIX**: Resolved line item visibility issues in SAP B1 UI (items were being created correctly, user was checking wrong quotation numbers)
* **CLEANUP**: Removed temporary debug files and improved development workflow
* **TESTING**: Verified end-to-end quotation creation with both auto-pricing and custom pricing scenarios

= 1.2.2 =
* **MAJOR FEATURE**: Added Sales Quotation creation functionality
* **NEW**: Create sales quotations in SAP B1 automatically after Business Partner creation
* **NEW**: Dynamic line item mapping for quotation products with checkbox support
* **NEW**: On-demand ItemCode loading system with master sync button
* **NEW**: Dual mapping system - form fields trigger line items, SAP ItemCodes define products
* **ENHANCEMENT**: Added active ItemCode filtering to prevent inactive item errors
* **ENHANCEMENT**: Added proper UoM (Unit of Measure) handling for quotation line items
* **ENHANCEMENT**: Enhanced WP-CLI test command with full quotation testing and verification
* **ENHANCEMENT**: Added ItemCode management tools and debugging commands
* **FIX**: Resolved "Item is inactive" errors by filtering only active ItemCodes
* **FIX**: Resolved "Cannot add or update document; specify a UoM code" by using numeric UoM entries
* **FIX**: Improved error handling and user feedback for quotation creation
* **TESTING**: Added comprehensive end-to-end testing for Business Partner + Sales Quotation workflow
* **TESTING**: All 85 unit tests pass with enhanced coverage
* **PERFORMANCE**: Optimized ItemCode loading with intelligent caching and refresh options

= 1.2.1 =
* **SECURITY: Fixed 7 critical security issues** - Enhanced input sanitization, nonce verification, and capability checks
* **FIXED: SSL verification setting** - Now properly saves and persists checkbox state
* **FIXED: Debug logging setting** - Checkbox state now saves correctly
* **IMPROVED: Centralized logging** - All logging now uses `shift8_gravitysap_debug_log()` with proper WP_DEBUG integration
* **IMPROVED: Password security** - SAP password now stored encrypted in memory and decrypted only when needed
* **IMPROVED: Settings save mechanism** - Removed conflicting WordPress Settings API callback
* **REMOVED: Custom log file management** - Now uses WordPress standard `error_log()` for better security
* All tests passing: 85 tests with 205 assertions

= 1.2.0 =
* **NEW: Contact Person Support** - Map form fields to Contact Persons tab in SAP B1
* Added 5 new Contact Person fields: FirstName, LastName, Phone, Email, Address
* **NEW: WP-CLI Testing Command** - End-to-end testing with `wp shift8-gravitysap test_submission`
* Enhanced field mapping with clear labels for BPAddresses vs ContactEmployees
* Improved SAP B1 data structure handling for addresses and contacts
* Comprehensive test suite: 90 unit tests + E2E integration testing
* Updated documentation with SAP B1 data structure best practices
* All tests passing with 214 assertions

= 1.1.1 =
* Enhanced field mapping system - validation is now completely dynamic based on user's field mapping configuration
* Improved validation efficiency - only validates fields that are actually mapped to SAP
* Verified WordPress.org plugin directory compliance - all automated checks now pass
* Code cleanup and optimization for better performance

= 1.1.0 =
* Expanded field mapping options - added Phone2, Mobile Phone, Fax, Website, and Country fields
* Better alignment with SAP Business One field structure (Telephone 1, Telephone 2, etc.)
* Enhanced test data with examples for all new fields
* Improved field mapping interface for comprehensive Business Partner data
* Fixed SAP field length compliance - test data now uses proper state codes (CA vs Test State)
* Added comprehensive field length documentation and troubleshooting guide
* **NEW: Automatic form validation** - validates all mapped fields against SAP limits before submission
* Enhanced field mapping interface now shows SAP field limits and validation hints
* Prevents "Value too long" SAP errors by catching field length issues in the form

= 1.0.9 =
* Fixed critical field mapping bug - all mapped fields now properly sent to SAP Business One
* Email, phone, and address fields will now be correctly populated in SAP
* Improved debug logging for Business Partner creation process
* All field mapping functionality now works as designed

= 1.0.8 =
* Fixed all WordPress.org plugin checker compliance issues
* Updated text domain to 'shift8-gravity-forms-sap-b1-integration'
* Removed all development functions (error_log) for production compliance
* Comprehensive testing framework with 65 automated tests
* Removed WordPress.org directory assets from plugin code
* Ready for WordPress.org plugin directory submission

= 1.0.7 =
* Updated plugin name to comply with WordPress.org trademark guidelines
* Changed display name to 'Shift8 Integration for Gravity Forms and SAP Business One'
* Removed discouraged load_plugin_textdomain() function call
* All functionality remains unchanged

= 1.0.0 =
* Initial release
* SAP Business One Service Layer integration
* Gravity Forms field mapping
* Password encryption and security features
* Debug logging with sensitive data protection
* Connection and integration testing tools

== Upgrade Notice ==

= 1.6.0 =
Email-based duplicate detection: Business Partners now matched by email OR name+address. Includes automated test suite for duplicate detection scenarios.

= 1.5.0 =
New WP-CLI `sap-query` command for direct SAP B1 queries. SAP identifiers now stored in GF entry meta. Remarks/Notes field available for mapping.

= 1.4.8 =
Switched to synchronous processing for reliable operation on all hosting environments.

= 1.2.0 =
Major update with Contact Person support! You can now map form fields to the Contact Persons tab in SAP B1. Includes new WP-CLI testing command for developers.

= 1.0.0 =
Initial release of the Shift8 Integration for Gravity Forms and SAP Business One plugin.

---

# Shift8 Integration for Gravity Forms and SAP Business One

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
| `FreeText` | Remarks/Notes | No | ~254 chars |

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
5. Duplicate detection (if enabled): checks SAP for existing BP matching Email OR (Name + Country + Postal)
   - Existing BP found: reuses the BP, checks for existing Contact Person, adds new contact if needed
   - No match found: creates a new Business Partner with Contact Person
6. Sales Quotation created and linked to the BP and Contact Person
7. SAP identifiers (CardCode, DocEntry, InternalCode) stored in GF entry meta
8. Success/error logging, entry notes, and status column updated

## Support

For support and documentation:
- Review debug logs for error details
- Use built-in connection testing tools
- Check SAP Service Layer documentation
- Contact: https://www.shift8web.ca

## License

GNU General Public License v3.0 or later 
