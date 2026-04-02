# Shift8 Integration for Gravity Forms and SAP Business One

A secure WordPress plugin that integrates Gravity Forms with SAP Business One, automatically creating Business Partner records from form submissions.

**📖 [Read the complete setup guide and technical walkthrough](https://shift8web.ca/how-to-integrate-sap-b1-business-one-into-wordpress-gravity-forms/)**

[![Version](https://img.shields.io/badge/version-1.6.0-blue.svg)](https://github.com/stardothosting/shift8-gravitysap)
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

### Sales Quotation Line Items (Checkbox Field Mapping)

When creating Sales Quotations, you can map checkbox fields to line items. Each checkbox option becomes a separate mappable field:

**Example: Checkbox Field "Select Products"**
- Option 1: "Product A" → Field ID: `15.1`
- Option 2: "Product B" → Field ID: `15.2`
- Option 3: "Product C" → Field ID: `15.3`

**Configuration Steps:**
1. Enable "Automatically create a sales quotation in SAP B1" in form settings
2. Click "Load ItemCodes from SAP B1" to populate available products
3. Map each quotation line slot to a checkbox option:
   - **DocumentLines.1.ItemCode**
     - Form Field: "Select Products → Product A" (15.1)
     - SAP ItemCode: CS-Designer-001
   - **DocumentLines.2.ItemCode**
     - Form Field: "Select Products → Product B" (15.2)
     - SAP ItemCode: CS-Designer-002

**How It Works:**
- ✅ **Checked boxes** create line items in the Sales Quotation
- ❌ **Unchecked boxes** are skipped (no line item created)
- The quotation is automatically linked to the newly created Business Partner
- You can mix checkbox fields with text fields for dynamic product selection

**Perfect For:**
- Product sample request forms
- Multi-product order forms
- Service selection forms
- Configurable quotation requests

### Business Partner Matching (Duplicate Detection)

When enabled, the plugin can check for existing Business Partners before creating new ones:

**Enable in Form Settings:**
1. Go to **Forms > [Your Form] > Settings > SAP Integration**
2. Enable **Check for existing Business Partner**

**How It Works:**

The plugin uses an **OR** strategy -- a match on **either** condition prevents duplicates:

- **Email match** (checked first, most reliable): `EmailAddress` matches an existing BP in SAP
- **Name + Address match** (fallback): `CardName` (case-insensitive) + `Country` + `Postal/ZIP Code` match

If a match is found, the plugin uses the existing Business Partner instead of creating a duplicate. Sales Quotations are created under the existing BP.

The result includes a `match_type` field (`email`, `name_address`, or `name_address_ci`) so logs clearly show how the duplicate was detected.

**WP-CLI Testing:**
```bash
# Search by email only
wp shift8-gravitysap-bp-lookup search --email="info@example.com"

# Search by name + address only
wp shift8-gravitysap-bp-lookup search --name="Test Company" --country="CA" --postal="M5V 1A1"

# Combined (email checked first, then name+address fallback)
wp shift8-gravitysap-bp-lookup search --name="Test Company" --country="CA" --postal="M5V 1A1" --email="info@example.com"

# Run all test scenarios defined in .cursorrules (gitignored)
wp shift8-gravitysap-bp-lookup run_tests
```

### Contact Person Linking

When a Business Partner match is found (or a new one is created), the plugin automatically:

1. **Adds a Contact Person** to the Business Partner using the form's contact data
2. **Links the Contact Person** to the Sales Quotation via SAP's `ContactPersonCode` field

**The Flow:**
```
Form Submission → Check for Existing BP → Found Match?
    ↓                                        ↓
    YES: Use existing BP               NO: Create new BP
    ↓                                        ↓
    Add Contact Person to existing BP  Contact included in new BP
    ↓                                        ↓
    Get Contact's InternalCode         Get Contact's InternalCode
    ↓                                        ↓
    Create Sales Quotation with ContactPersonCode = InternalCode
```

**Important Technical Note:**
SAP B1's `ContactPersonCode` field on documents (Quotations, Orders, Invoices) requires the **numeric InternalCode**, not the contact's text Name. The plugin handles this automatically by fetching the Business Partner after contact creation to retrieve the InternalCode.

**Result in SAP B1:**
- Contact Person appears in the Business Partner's "Contact Persons" tab
- Sales Quotation dropdown shows the correct Contact Person selected
- Contact Person is properly linked for subsequent documents

## WP-CLI Commands (Developers)

### SAP B1 Direct Queries

Query SAP B1 records by their unique identifiers:

```bash
# Look up a Business Partner by CardCode
wp sap-query bp E00115
wp sap-query bp E00115 --contacts --quotations

# Look up a Sales Quotation by DocEntry
wp sap-query quotation 285

# Search Business Partners by name
wp sap-query search "Emilie Cohen"

# View SAP meta stored on a Gravity Forms entry
wp sap-query entry 132 --verify

# View the latest entry for a specific form
wp sap-query entry --form_id=3 --verify
```

### End-to-End Test Submission

Simulate a full form submission with real SAP B1 integration:

```bash
wp shift8-gravitysap test-submission --form_id=3
wp shift8-gravitysap test-submission --form_id=3 --cleanup=false
```

This creates a test GF entry, submits to SAP B1, verifies the data, and optionally cleans up.

### Duplicate Detection Test

Test the Business Partner lookup performance and accuracy:

```bash
# Search by email, name+address, or both
wp shift8-gravitysap-bp-lookup search --email="info@example.com"
wp shift8-gravitysap-bp-lookup search --name="Acme Corp" --country=CA --postal="M5V 1A1" --verbose
wp shift8-gravitysap-bp-lookup search --name="Acme Corp" --country=CA --postal="M5V 1A1" --email="info@acme.com"

# Run all test scenarios from .cursorrules (gitignored, safe for client data)
wp shift8-gravitysap-bp-lookup run_tests

# Benchmark lookup performance
wp shift8-gravitysap-bp-lookup benchmark --iterations=5
```

### Master Data Queries

Look up SAP B1 reference codes for field mapping configuration:

```bash
wp shift8-gravitysap-masterdata groups
wp shift8-gravitysap-masterdata currencies
wp shift8-gravitysap-masterdata pricelists
```

### Manual Test Workflow

1. **Submit**: `wp shift8-gravitysap test_submission --form_id=3 --cleanup=false`
2. **Check entry meta**: `wp sap-query entry --form_id=3 --verify`
3. **Verify BP in SAP**: `wp sap-query bp <CardCode> --contacts --quotations`
4. **Verify Quotation**: `wp sap-query quotation <DocEntry>`
5. **Test duplicate detection**: Re-run step 1, confirm `sap_b1_bp_matched` = `1`
6. **Test email dedup**: `wp shift8-gravitysap-bp-lookup search --email="<email from step 1>"`
7. **Run all dedup scenarios**: `wp shift8-gravitysap-bp-lookup run_tests`
8. **Cleanup**: Delete the test entry via GF admin

### Entry Meta Reference

Each processed GF entry stores these SAP B1 identifiers in meta:

| Meta Key | Description |
|---|---|
| `sap_b1_status` | `processing`, `success`, or `failed` |
| `sap_b1_cardcode` | SAP Business Partner CardCode (e.g., `E00115`) |
| `sap_b1_bp_matched` | `1` if existing BP matched, `0` if new BP created |
| `sap_b1_contact_name` | Contact Person name |
| `sap_b1_contact_internal_code` | Contact Person InternalCode (numeric) |
| `sap_b1_quotation_docentry` | Sales Quotation DocEntry (SAP primary key) |
| `sap_b1_quotation_docnum` | Sales Quotation DocNum (user-facing number) |
| `sap_b1_error` | Error message if submission failed |

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
5. **Duplicate detection** (if enabled): checks SAP for existing BP matching **Email** OR **(Name + Country + Postal Code)**
   - **Existing BP found** (via email or name+address): reuses the BP, checks for existing Contact Person (by Name + Email), adds new contact if needed
   - **No match found**: creates a new Business Partner with Contact Person
6. Sales Quotation created and linked to the BP and Contact Person
7. SAP identifiers (CardCode, DocEntry, InternalCode) stored in GF entry meta
8. Success/error logging, entry notes, and status column updated

## Testing

### Unit Tests
```bash
cd /path/to/shift8-gravitysap
vendor/bin/phpunit tests/unit/
```

### End-to-End Testing
```bash
wp shift8-gravitysap test-submission --form_id=<id>
```

Tests real SAP B1 integration including BP creation, Contact Person handling, Sales Quotation, and data verification.

### Verifying SAP Records
```bash
wp sap-query entry --form_id=<id> --verify
```

Cross-references GF entry meta against live SAP B1 data to confirm records were created correctly.

## Changelog

### 1.6.0
* **NEW**: Email-based duplicate Business Partner detection - matches if email OR (name+country+postal) already exists in SAP
* **NEW**: `wp shift8-gravitysap-bp-lookup run_tests` command reads test scenarios from `.cursorrules` (gitignored) and runs them against live SAP with pass/fail output
* **IMPROVED**: Email check runs first as the most reliable unique identifier before falling back to name+address matching
* **IMPROVED**: WP-CLI `bp-lookup search` command now supports `--email` parameter for email-only or combined lookups
* **IMPROVED**: BP lookup result includes `match_type` field (email, name_address, name_address_ci) for transparency
* **IMPROVED**: Updated documentation for duplicate detection, field mapping, and manual test workflows
* **TESTS**: 5 new email deduplication tests (141 total tests, 316 assertions)

### 1.5.0
* **NEW**: `wp sap-query` WP-CLI command for direct SAP B1 queries by CardCode, DocEntry, or entry ID
* **NEW**: `wp sap-query entry --form_id=<id> --verify` to verify latest entry against SAP B1
* **NEW**: SAP identifiers stored in GF entry meta: `sap_b1_bp_matched`, `sap_b1_contact_name`, `sap_b1_contact_internal_code`
* **NEW**: Remarks/Notes (`FreeText`) field available in Business Partner field mapping
* **IMPROVED**: Entry list SAP Status column now shows BP match type, Quotation DocNum, and Contact name
* **IMPROVED**: Comprehensive WP-CLI documentation for manual testing workflows

### 1.4.8
* **CHANGED**: Switched to synchronous processing (standard GF add-on approach)
* **REMOVED**: Async loopback processing - was unreliable on many hosting environments
* **IMPROVED**: Now works reliably on ALL hosting environments
* **SIMPLIFIED**: Removed unnecessary complexity - follows GF best practices

### 1.4.7
* Synchronous Processing option (superseded by 1.4.8)

### 1.4.6
* **CHANGED**: Debug logging now requires both `WP_DEBUG=true` AND plugin debug setting enabled

### 1.4.5
* **NEW**: Robust debug logging system - requires both `WP_DEBUG` and plugin debug setting
* **NEW**: Dedicated plugin log file at `wp-content/uploads/shift8-gravitysap-debug.log`
* **NEW**: Comprehensive step-by-step logging throughout form submission and async processing
* **IMPROVED**: Full visibility into integration process for troubleshooting
* **IMPROVED**: Fallback to PHP `error_log` if file write fails (permission issues)
* **IMPROVED**: Plugin init now logs diagnostic info (file paths, permissions, PHP version)
* **IMPROVED**: Async loopback request logging for debugging failed callbacks

### 1.4.4
* **NEW**: Duplicate contact detection - checks if contact already exists before adding
* **NEW**: `find_existing_contact()` method for case-insensitive name + email matching
* **IMPROVED**: Reuses existing contacts instead of creating duplicates on repeat submissions
* **TESTING**: Added 10 new tests for contact duplicate detection (edge cases included)
* **TESTING**: Now 138 tests with 306 assertions - All passing

### 1.4.3
* **FIX**: Contact Person now correctly linked to Sales Quotation using SAP's InternalCode (numeric) instead of Name (string)
* **IMPROVED**: After adding Contact Person to existing BP, plugin now fetches BP to retrieve the contact's InternalCode
* **IMPROVED**: Sales Quotation ContactPersonCode field now uses correct integer value for proper SAP B1 linking
* **TESTING**: Added 3 additional Contact Person tests (InternalCode retrieval, existing Name field, first name only)
* **TESTING**: Now 128 tests with 291 assertions - All passing
* **DOCUMENTATION**: Added comprehensive contactPersonLinking section to .cursorrules

### 1.4.2
* **NEW**: Contact Person now added to existing Business Partner when match is found
* **NEW**: Contact Person linked to Sales Quotation via ContactPersonCode field
* **IMPROVED**: SAP Service class now includes `add_contact_to_business_partner()` and `get_business_partner()` methods
* **TESTING**: Added 6 new unit tests for Contact Person functionality
* **TESTING**: Now 125 tests with 283 assertions - All passing

### 1.4.1
* **TESTING**: Added comprehensive test coverage for async processing (9 new tests)
* **TESTING**: Added comprehensive test coverage for Business Partner lookup (10 new tests)
* **TESTING**: Now 119 tests with 272 assertions - All passing
* **DOCUMENTATION**: Updated .cursorrules with async processing, BP lookup, and testing patterns

### 1.4.0
* **NEW**: Async processing for form submissions - SAP integration now runs in a non-blocking background request
* **NEW**: "Check for Existing Business Partner" now integrated into form submission flow
* **NEW**: Test Integration button now supports existing BP lookup when enabled
* **IMPROVED**: Form submissions no longer block on SAP API response time (1-2 seconds faster)
* **IMPROVED**: Centralized Business Partner lookup logic for code reuse across WP-CLI, form processing, and test integration

### 1.3.9
* **NEW**: Added "Check for Existing Business Partner" toggle setting
* **NEW**: Added WP-CLI command `wp shift8-gravitysap-bp-lookup search` to test duplicate detection
* **NEW**: Added WP-CLI command `wp shift8-gravitysap-bp-lookup benchmark` to measure SAP query performance
* **ENHANCEMENT**: Duplicate detection matches on Business Partner Name (case-insensitive), Country, and Postal Code

### 1.3.8
* **FIX**: Test data values now properly save with "Update Settings" button
* **ENHANCEMENT**: Test data fields moved into main settings form for consistent save behavior

### 1.3.7
* **FIX**: Fixed validation error display - fields now properly highlighted when SAP validation fails
* **ENHANCEMENT**: Improved validation error messages with detailed information (submitted value, character count, max allowed, hints)
* **ENHANCEMENT**: Added summary of all SAP validation errors at top of form
* **NEW**: Added WP-CLI commands to query SAP master data (groups, currencies, pricelists)

### 1.3.6
* **FIX**: Removed required validation from CardName field (all fields now optional)

### 1.3.5
* **FIX**: Removed URL format validation from Website field (accepts any text now)

### 1.3.4
* **NEW**: Added Business Partner Group (GroupCode) field mapping
* **NEW**: Added Currency field mapping
* **NEW**: Added Price List (PriceListNum) field mapping from Payment Terms
* **STABILITY**: All 99 tests passing with 231 assertions

### 1.3.3
* **FIX**: Fixed fatal error from undefined methods (removed orphaned debug handlers)
* **FIX**: Removed orphaned debug/test buttons from settings page
* **FIX**: Test Integration now correctly uses Business Partner Type and Code Prefix settings
* **FIX**: Improved entries list column display using field mapping for Name, Email, Country
* **STABILITY**: All 99 tests passing with 231 assertions

### 1.3.2
* **FIX**: Fixed critical error when viewing Gravity Forms entries list (undefined method)
* **FIX**: Updated display_sap_status_column to only handle SAP-specific columns
* **STABILITY**: All 99 tests passing with 231 assertions

### 1.3.1
* **FIX**: Removed temporary debug logging statements
* **CLEANUP**: Removed scenario-specific business logic functions
* **STABILITY**: Code cleanup and optimization for production deployment

### 1.3.0
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

### 1.2.2
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

### 1.2.1
* **SECURITY: Fixed 7 critical security issues** - Enhanced input sanitization, nonce verification, and capability checks
* **FIXED: SSL verification setting** - Now properly saves and persists checkbox state
* **FIXED: Debug logging setting** - Checkbox state now saves correctly
* **IMPROVED: Centralized logging** - All logging now uses `shift8_gravitysap_debug_log()` with proper WP_DEBUG integration
* **IMPROVED: Password security** - SAP password now stored encrypted in memory and decrypted only when needed
* **IMPROVED: Settings save mechanism** - Removed conflicting WordPress Settings API callback
* **REMOVED: Custom log file management** - Now uses WordPress standard `error_log()` for better security
* All tests passing: 85 tests with 205 assertions

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