# Development Mode Toggle - Usage Guide

## Overview

The Hospital Manager plugin now includes a sophisticated development mode toggle system that allows administrators and developers to easily switch between development and production modes without modifying code.

## Features

### 🔧 Development Mode Toggle Component
- **Visibility**: Only visible to administrators and developers
- **Location**: Bottom-right corner of the screen (floating button)
- **Functionality**: Click to toggle between development and production modes
- **Visual Feedback**: 
  - Green = Development mode ON
  - Red = Development mode OFF
- **Tooltip**: Hover to see detailed environment information

### 🏥 Header Status Indicator
- **Location**: Header bar (next to role indicator)
- **Visibility**: Only shown when development mode is active
- **Purpose**: Quick visual confirmation of development mode status

## How to Use

### Method 1: Visual Toggle (Recommended)
1. Log in as an administrator or developer
2. Look for the floating toggle button in the bottom-right corner
3. Click the button to toggle development mode
4. Page will automatically reload to apply changes

### Method 2: URL Parameters (Quick Testing)
```
# Enable development mode
http://your-site.com/?dev_mode=true

# Disable development mode
http://your-site.com/?dev_mode=false
```

### Method 3: Browser Console Commands
Open browser console (F12) and use these commands:

```javascript
// Show all available commands
devMode.help()

// Toggle development mode
devMode.enable()    // Enable
devMode.disable()   // Disable
devMode.toggle()    // Toggle current state

// Check status
devMode.status()    // Check if enabled
devMode.info()      // Show environment details

// Clear overrides
devMode.clear()     // Remove localStorage override
```

### Method 4: Console Debugging
```javascript
// Authentication info
debug.auth()

// User permissions
debug.permissions()

// Environment details
debug.environment()

// Test API connection
debug.api()

// Quick actions
quick.reload()         // Reload page
quick.clearStorage()   // Clear all storage
```

## Development Mode Detection Priority

The system checks for development mode in this order:

1. **URL Parameters** (`?dev_mode=true/false`) - Highest priority
2. **localStorage Override** (set by toggle component)
3. **Environment Variables** (`VITE_APP_MODE=development`)
4. **WordPress Constants** (WP_DEBUG + WP_ENVIRONMENT_TYPE)
5. **Hostname Detection** (localhost, 127.0.0.1, *.local) - Lowest priority

## What Changes in Development Mode

### Authentication
- **Development**: Uses mock admin user, bypasses login
- **Production**: Uses real WordPress authentication

### Permissions
- **Development**: Mock administrator permissions with `schedule_appointments: false`
- **Production**: Real database-driven permissions

### API Calls
- **Development**: May use mock data or bypass certain validations
- **Production**: Full API validation and real data

### Logging
- **Development**: Verbose console logging with 🔧 prefix
- **Production**: Minimal logging

## Access Control

### Who Can See the Toggle?
- ✅ **Administrators**: Full access to toggle and all features
- ✅ **Developers**: Full access to toggle and all features  
- ❌ **Doctors**: No access to development features
- ❌ **Patients**: No access to development features
- ❌ **Other Roles**: No access to development features

### Security Notes
- Toggle only appears for authorized roles
- URL parameters work for quick testing but don't persist
- localStorage overrides are client-side only
- Production mode is the default when no overrides are set

## Environment Variables

Add these to your `.env` file:

```env
# Enable development features
VITE_APP_MODE=development
VITE_ENABLE_DEBUG=true

# Optional: Set specific API URL
VITE_API_URL=http://localhost:10008/wp-json/hospital-manager/v1
```

## WordPress Integration

Add these to your `wp-config.php` for WordPress-level development mode:

```php
// Enable WordPress debug mode
define('WP_DEBUG', true);
define('WP_ENVIRONMENT_TYPE', 'local');

// Hospital Manager specific
define('HOSPITAL_MANAGER_DEV_MODE', true);
```

## Troubleshooting

### Toggle Not Visible
- Ensure you're logged in as an administrator or developer
- Check browser console for errors
- Verify role permissions are properly set

### Mode Not Switching
- Check browser console for error messages
- Clear browser cache and try again
- Use `devMode.clear()` to reset overrides

### Console Commands Not Working
- Ensure the page has fully loaded
- Try refreshing the page
- Check if `window.hospitalManagerDevMode` exists

## Best Practices

1. **Use URL parameters** for quick testing during development
2. **Use the toggle component** for persistent mode switching
3. **Use console commands** for debugging and advanced control
4. **Clear overrides** when switching between environments
5. **Monitor the header indicator** to confirm current mode

## Development Workflow

1. **Development Phase**: Enable development mode for faster iteration
2. **Testing Phase**: Toggle between modes to test both scenarios
3. **Production Deployment**: Ensure development mode is disabled
4. **Debugging**: Use console commands to investigate issues

This system provides maximum flexibility while maintaining security and ease of use.
