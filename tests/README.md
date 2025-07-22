# Shift8 Integration for Gravity Forms and SAP Business One - Testing Suite

This comprehensive testing suite provides robust validation for the Shift8 Integration plugin using PHPUnit with Brain/Monkey for WordPress function mocking.

## 📊 Current Test Coverage

- **Tests**: 25 tests with 63 assertions
- **Coverage**: 15.78% lines covered (131/830)
- **Strategy**: Brain/Monkey for automatic WordPress function mocking

## 🧪 Test Structure

### Unit Tests (`tests/unit/`)

#### `HelperFunctionsTest.php`
Tests global helper functions:
- Password encryption/decryption with various inputs
- Debug logging with different settings
- Log data sanitization for security

#### `MainPluginTest.php`  
Tests the main `Shift8_GravitySAP` class:
- Singleton pattern implementation
- Plugin activation/deactivation
- Gravity Forms integration setup
- Form settings management
- Entry processing workflows

### Test Configuration

#### `tests/bootstrap.php`
Brain/Monkey bootstrap that automatically mocks WordPress functions and provides a clean testing environment.

#### `phpunit.xml`
PHPUnit configuration for running tests with coverage reporting.

#### `patchwork.json`
Configuration for Brain/Monkey to mock internal PHP functions when needed.

## 🚀 Running Tests

### Quick Commands
```bash
# Run all tests
composer test

# Run unit tests only  
composer test:unit

# Generate coverage report
composer test:coverage

# Generate HTML coverage report
composer test:coverage-html
```

### Manual PHPUnit Commands
```bash
# Run all unit tests
./vendor/bin/phpunit

# Run specific test class
./vendor/bin/phpunit tests/unit/HelperFunctionsTest.php

# Run with coverage
./vendor/bin/phpunit --coverage-text

# Generate HTML coverage
./vendor/bin/phpunit --coverage-html tests/coverage/html
```

## 🎯 Test Strategy

### Brain/Monkey Benefits
- **Automatic Mocking**: WordPress functions are mocked automatically
- **Function Validation**: Verifies correct function usage and parameters
- **Future Proof**: Adapts to WordPress core changes automatically
- **Professional Grade**: Industry standard for WordPress plugin testing

### Mocked Dependencies
- **WordPress Core Functions**: Automatically handled by Brain/Monkey
- **SAP HTTP Responses**: Simulated success/error responses 
- **Gravity Forms Functions**: `rgar`, `rgpost`, form handling
- **File System Operations**: Upload directory, file writing

### Real Components Tested
- Business logic and data processing
- Form field mapping and validation  
- Error handling and edge cases
- Security features (sanitization, nonces)

## 📈 Coverage Breakdown

### Excellent Coverage (>80%)
- Password encryption/decryption functions
- Log data sanitization utilities
- Form settings validation

### Good Coverage (50-80%)
- Plugin initialization and setup
- Gravity Forms integration hooks
- Entry to business partner mapping

### Needs Improvement (<50%)
- SAP service communication layer
- Admin interface components
- Error recovery mechanisms

## 🔧 Adding New Tests

### 1. Create Test File
```php
<?php
namespace Shift8\GravitySAP\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class YourNewTest extends TestCase {
    
    public function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    public function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }
    
    public function test_your_functionality() {
        // Mock WordPress functions as needed
        Functions\when('get_option')->justReturn('test_value');
        
        // Your test code
        $this->assertTrue(true);
    }
}
```

### 2. Mock WordPress Functions
```php
// Simple mocking
Functions\when('wp_function')->justReturn('value');

// Expectation with validation
Functions\expect('wp_function')
    ->once()
    ->with('expected_param')
    ->andReturn('value');

// Alias to existing function
Functions\when('sanitize_text_field')->alias('strip_tags');
```

### 3. Run Your Test
```bash
composer test -- --filter=YourNewTest
```

## 🛠️ Debugging Tests

### View Detailed Output
```bash
composer test -- --debug --verbose
```

### Run Single Test Method
```bash
./vendor/bin/phpunit --filter=test_specific_method tests/unit/YourTest.php
```

### Coverage for Specific File
```bash
./vendor/bin/phpunit --coverage-filter src/specific-file.php
```

## 📋 Test Checklist

Before submitting code, ensure:
- [ ] All existing tests pass
- [ ] New functionality has corresponding tests
- [ ] Test coverage doesn't decrease significantly
- [ ] Edge cases and error conditions are tested
- [ ] WordPress functions are properly mocked with Brain/Monkey

## 🎨 Best Practices

1. **Test Isolation**: Each test should be independent with proper setUp/tearDown
2. **Clear Assertions**: Use descriptive assertion messages
3. **Mock External Dependencies**: Don't rely on real SAP connections
4. **Test Edge Cases**: Invalid inputs, missing data, etc.
5. **Use Brain/Monkey Properly**: Mock functions appropriately for each test
6. **Maintain Coverage**: Aim for >70% line coverage on new code

## 🌟 Brain/Monkey Advantages

- **Zero Maintenance**: No manual function mocking required
- **WordPress Updates**: Automatically adapts to WordPress core changes
- **Function Validation**: Ensures correct function usage and parameters
- **IDE Support**: Full autocomplete and error checking
- **Professional Standard**: Used by major WordPress plugins and themes

## 🎯 Next Testing Priorities

### High Priority
- **SAP Service Tests**: Add comprehensive SAP communication layer tests
- **Admin Interface Tests**: Test settings page and AJAX handlers
- **Security Tests**: Input sanitization and output escaping validation

### Medium Priority
- **Integration Tests**: Test component interactions
- **Error Handling**: Comprehensive failure scenario testing
- **Performance Tests**: Ensure efficient execution

### Lower Priority
- **End-to-End Tests**: Complete workflow validation
- **Accessibility Tests**: Ensure admin interface compliance
- **Browser Compatibility**: Cross-browser testing

## Continuous Integration Ready

The test suite is designed for CI/CD:
- ✅ **GitHub Actions** compatible
- ✅ **No external dependencies** (Brain/Monkey mocking)
- ✅ **Fast execution** (< 2 seconds)
- ✅ **Professional grade** testing framework
- ✅ **WordPress.org compliant** testing

This testing suite ensures the plugin remains reliable and maintainable as it evolves! 