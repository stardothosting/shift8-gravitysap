# WordPress.org Plugin Assets

This directory contains the assets for the **Shift8 Integration for Gravity Forms and SAP Business One** plugin directory listing on WordPress.org.

## 📁 **Asset Files**

- **`icon-128x128.png`** - Plugin icon (128x128px) for the plugin directory
- **`icon-256x256.png`** - Plugin icon (256x256px) for high-resolution displays
- **`banner-772x250.png`** - Plugin banner (772x250px) for the plugin directory header
- **`banner-1554x500.png`** - Plugin banner (1554x500px) for high-resolution displays
- **`screenshot-1.jpg`** - Screenshot of the plugin settings page

## 🚀 **How Assets Are Deployed**

When using the `push.sh` script, these assets are automatically synced to the WordPress.org SVN repository:

```bash
# The push script automatically handles assets
./push.sh "Version 1.0.7 release" "1.0.7"
```

**What happens:**
1. Main plugin files go to `svn/trunk/` and `svn/tags/1.0.7/`
2. Assets from this directory go to `svn/assets/`
3. WordPress.org automatically uses assets from the `assets` folder

## 📋 **WordPress.org Asset Guidelines**

### **Icons**
- **128x128px** - Required minimum size
- **256x256px** - Recommended for high-resolution displays
- **Format:** PNG with transparent background
- **File names:** `icon-128x128.png`, `icon-256x256.png`

### **Banners**
- **772x250px** - Standard banner size
- **1544x500px** - High-resolution banner (optional)
- **Format:** JPG or PNG
- **File names:** `banner-772x250.png`, `banner-1544x500.png`

### **Screenshots**
- **Format:** JPG or PNG  
- **File names:** `screenshot-1.jpg`, `screenshot-2.jpg`, etc.
- **Recommended size:** 1200x900px or similar aspect ratio
- **Purpose:** Show plugin functionality and interface

## ✅ **Benefits of Separate Asset Directory**

1. **Clean Plugin Code** - No assets mixed with functional code
2. **WordPress.org Compliance** - Meets submission requirements
3. **Version Control** - Assets tracked separately from code changes
4. **Easy Updates** - Update assets without changing plugin version
5. **Automated Deployment** - Assets sync automatically during releases

## 🔄 **Updating Assets**

To update plugin assets:

1. **Replace files** in this directory with new versions
2. **Keep same filenames** for WordPress.org recognition
3. **Run deployment script** to sync to WordPress.org
4. **No plugin version bump needed** for asset-only changes

## 📝 **Asset Creation Tips**

- **Use high contrast** for better visibility
- **Keep text minimal** in banners (WordPress.org adds plugin name)
- **Test on different screen sizes** for responsiveness
- **Follow WordPress.org branding guidelines**
- **Use consistent color scheme** across all assets

---

*These assets are deployed to WordPress.org automatically and are separate from the plugin installation package.* 
