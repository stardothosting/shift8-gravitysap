# WordPress Plugin Testing Best Practices

## ❌ **Problems with Current Mock Approach**

Our current `bootstrap-simple.php` has **major issues**:

1. **Function Signature Drift**: WordPress updates functions, our mocks become outdated
2. **Incomplete Behavior**: Mocks don't replicate full WordPress function behavior  
3. **Maintenance Burden**: 50+ function mocks to manually maintain
4. **False Confidence**: Tests pass but might fail in real WordPress
5. **Missing Dependencies**: WordPress functions depend on globals/other functions

## ✅ **Industry Standard: WordPress Test Suite Integration**

### **Setup WordPress Official Testing Environment**

```bash
# 1. Install WordPress test suite
./bin/install-wp-tests.sh wordpress_test root '' localhost latest

# 2. Set environment variables
export WP_PHPUNIT__DIR=/tmp/wordpress-tests-lib
export WP_TESTS_DIR=/tmp/wordpress-tests-lib
export WP_CORE_DIR=/tmp/wordpress/

# 3. Update composer.json
composer require --dev johnpbloch/wordpress-core
```

### **Proper Bootstrap File**

```php
<?php
// tests/bootstrap.php - PROPER WordPress integration

// Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Give access to tests_add_filter() function
require_once getenv('WP_PHPUNIT__DIR') . '/includes/functions.php';

/**
 * Manually load the plugin being tested
 */
function _manually_load_plugin() {
    require dirname(__DIR__) . '/shift8-gravitysap.php';
}

// Load plugin before WordPress initializes
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment
require getenv('WP_PHPUNIT__DIR') . '/includes/bootstrap.php';
```

## 🚀 **Modern Approaches**

### **1. Brain/Monkey (Recommended for Unit Tests)**

**Best for:** Pure unit testing without WordPress overhead

```bash
composer require --dev brain/monkey
```

```php
<?php
// tests/bootstrap-monkey.php
require_once __DIR__ . '/../vendor/autoload.php';

use Brain\Monkey;

// Initialize Brain\Monkey
Monkey\setUp();

// Your plugin tests here - Brain\Monkey handles all WP function mocking
```

**Benefits:**
- ✅ **Automatic function mocking** (no manual mock creation)
- ✅ **Function signature validation** (fails if WP changes functions)  
- ✅ **Behavior verification** (test if functions called correctly)
- ✅ **Fast execution** (no WordPress loading)

### **2. WP Mock (Alternative)**

```bash
composer require --dev 10up/wp_mock
```

### **3. WordPress VIP's Testing Framework**

Used by WordPress.com, enterprise-grade:

```bash
composer require --dev automattic/vipwpcli
```

## 📊 **Comparison Matrix**

| Approach | Speed | Accuracy | Maintenance | WordPress Integration |
|----------|-------|----------|-------------|---------------------|
| **Manual Mocks (Current)** | ⚡ Fast | ❌ Low | 🔴 High | ❌ Poor |
| **Brain/Monkey** | ⚡ Fast | ✅ High | ✅ Low | 🟡 Partial |
| **WP Test Suite** | 🐌 Slow | ✅ Perfect | ✅ Low | ✅ Complete |
| **Hybrid Approach** | 🟡 Medium | ✅ High | ✅ Low | ✅ Good |

## 🎯 **Recommended Solution: Hybrid Approach**

### **Structure**

```
tests/
├── bootstrap.php              # Full WordPress environment
├── bootstrap-monkey.php       # Brain/Monkey for unit tests  
├── unit/                      # Fast Brain/Monkey tests
│   ├── HelperFunctionsTest.php
│   └── DataValidationTest.php
├── integration/               # WordPress test suite
│   ├── AdminInterfaceTest.php
│   └── GravityFormsTest.php
└── acceptance/                # E2E tests
    └── FullWorkflowTest.php
```

### **Updated Composer Scripts**

```json
{
  "scripts": {
    "test:unit": "phpunit --configuration phpunit-unit.xml",
    "test:integration": "phpunit --configuration phpunit-integration.xml", 
    "test:all": "composer test:unit && composer test:integration",
    "test:coverage": "phpunit --configuration phpunit-unit.xml --coverage-text"
  }
}
```

## 🔧 **Implementation Plan**

### **Phase 1: Migrate to Brain/Monkey (Immediate)**

1. **Install Brain/Monkey**
   ```bash
   composer require --dev brain/monkey
   ```

2. **Create Unit Test Bootstrap**
   ```php
   // tests/bootstrap-unit.php
   require_once __DIR__ . '/../vendor/autoload.php';
   Brain\Monkey\setUp();
   ```

3. **Update Unit Tests**
   ```php
   // tests/unit/HelperFunctionsTest.php
   use Brain\Monkey\Functions;
   
   public function test_debug_logging() {
       Functions\expect('get_option')
           ->once()
           ->with('shift8_gravitysap_settings', [])
           ->andReturn(['sap_debug' => '1']);
           
       // Test passes and verifies get_option was called correctly
   }
   ```

### **Phase 2: Add WordPress Integration Tests (Later)**

1. **Setup WordPress Test Environment**
2. **Create Integration Test Suite**
3. **Test Real WordPress Interactions**

## 🏆 **Benefits of Proper Approach**

### **Brain/Monkey Benefits**
- ✅ **Automatic Updates**: No manual function mocking
- ✅ **Signature Validation**: Fails if WordPress changes functions
- ✅ **Behavior Testing**: Verify functions called with correct parameters
- ✅ **IDE Support**: Full autocomplete and error checking

### **WordPress Test Suite Benefits**  
- ✅ **Real Environment**: Tests run in actual WordPress
- ✅ **Database Testing**: Real database operations
- ✅ **Plugin Interactions**: Test with other plugins
- ✅ **WordPress.org Standard**: Official recommendation

## 🚨 **Action Required**

Your current approach will **definitely break** when WordPress updates core functions. The proper solution is:

1. **Immediate**: Migrate to Brain/Monkey for unit tests
2. **Soon**: Add WordPress test suite for integration tests  
3. **Optional**: Keep simplified mocks only for CI/CD speed tests

This gives you **future-proof, maintainable, industry-standard** WordPress plugin testing. 