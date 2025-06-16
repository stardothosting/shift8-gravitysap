# Shift8 Gravity Forms SAP B1 Integration

A WordPress plugin that integrates Gravity Forms with SAP Business One using the Service Layer API to create Business Partner records.

## Description

This plugin allows you to create Business Partners in SAP Business One directly from Gravity Forms submissions. It provides a seamless integration between your WordPress website and SAP Business One, enabling you to:

- Create Customers, Vendors, and Leads in SAP B1
- Map Gravity Forms fields to SAP Business Partner fields
- Test the SAP connection before going live
- View detailed logs of form submissions and SAP operations

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Gravity Forms 2.5 or higher
- SAP Business One Service Layer API access

## Installation

1. Upload the `shift8-gravitysap` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Shift8 > Gravity SAP to configure your SAP connection settings
4. Test the connection to ensure it works
5. Go to a Gravity Form and add a "SAP B1 Integration" feed
6. Map form fields to SAP Business Partner fields
7. Test form submission to create Business Partners in SAP

## Configuration

### SAP Connection Settings

1. **SAP Service Layer Endpoint URL**: The URL of your SAP Service Layer API (e.g., https://sap.example.com:50000/b1s/v1/)
2. **SAP Company Database**: Your SAP Company Database identifier
3. **SAP Username**: Your SAP Service Layer username
4. **SAP Password**: Your SAP Service Layer password

### Feed Settings

1. **Feed Name**: A unique name to identify this feed
2. **Business Partner Type**: Select the type of Business Partner to create (Customer, Vendor, or Lead)
3. **Field Mappings**: Map Gravity Forms fields to SAP Business Partner fields

## Field Mappings

The following SAP Business Partner fields can be mapped:

- **Card Code**: Unique identifier for the Business Partner
- **Card Name**: Name of the Business Partner
- **Card Type**: Type of Business Partner
- **Email**: Email address
- **Phone**: Phone number
- **Address**: Complete address information

## Usage

1. Create a Gravity Form with the fields you want to map to SAP
2. Add a "SAP B1 Integration" feed to the form
3. Configure the feed settings and field mappings
4. Test the form submission
5. Check the SAP Business One system to verify the Business Partner was created

## Troubleshooting

If you encounter any issues:

1. Check the SAP connection settings
2. Verify the field mappings are correct
3. Check the WordPress debug log for any error messages
4. Ensure your SAP Service Layer API is accessible
5. Verify the user has sufficient permissions in SAP

## Support

For support, please visit [Shift8 Web](https://www.shift8web.ca) or email support@shift8web.ca.

## License

This plugin is licensed under the GPL v3 or later.

## Credits

Developed by [Shift8 Web](https://www.shift8web.ca)

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