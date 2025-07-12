# Hospital Manager WordPress Plugin

A WordPress plugin for hospital management with a modern React frontend built with Vite and styled with Tailwind CSS.

## Development Setup

### Prerequisites

- Node.js (v18+)
- Yarn package manager
- WordPress development environment

### Installation

1. Clone this repository into your WordPress plugins directory
2. Navigate to the plugin directory
```bash
cd /path/to/wp-content/plugins/hospital-manager
```
3. Install dependencies using Yarn
```bash
yarn install
```

### Development Workflow

#### Development Mode Toggle

The plugin includes a sophisticated development mode toggle system for administrators and developers:

- **Visual Toggle**: Click the floating button (🔧) in the bottom-right corner
- **Console Commands**: Use browser console commands like `devMode.enable()`, `devMode.disable()`
- **URL Parameters**: Add `?dev_mode=true` or `?dev_mode=false` to any URL
- **Environment Detection**: Automatically detects localhost, development environments

**For detailed usage instructions, see [DEVELOPMENT_MODE_GUIDE.md](./DEVELOPMENT_MODE_GUIDE.md)**

#### Build Commands

You can use either Yarn directly or the provided development script:

#### Using Yarn

To start development with hot reloading:
```bash
yarn dev
```

To build assets for production:
```bash
yarn build
```

To watch for changes and rebuild automatically:
```bash
yarn watch
```

#### Using the Development Script

We've included a convenient script to manage common development tasks:

```bash
# Install dependencies
./dev.sh install

# Start development server
./dev.sh dev

# Build for production
./dev.sh build

# Watch for changes
./dev.sh watch

# Show help
./dev.sh help
```

### WordPress Integration

The plugin uses a shortcode `[hospital_manager]` to render the React application on any WordPress page or post.

## Project Structure

```
hospital-manager/
├── app/                     # PHP application files
│   ├── Controllers/         # MVC controllers
│   ├── Models/              # MVC models
│   └── Views/               # MVC views
├── assets/                  # Frontend assets
│   ├── css/
│   │   ├── dist/            # Compiled CSS
│   │   └── src/             # Source CSS (with Tailwind)
│   └── js/
│       ├── dist/            # Compiled JS bundle
│       └── src/             # React source files
├── node_modules/            # Node dependencies (not versioned)
├── package.json             # Node dependencies and scripts
├── tailwind.config.js       # Tailwind configuration
├── postcss.config.js        # PostCSS configuration
└── vite.config.js           # Vite bundler configuration
```

## Tailwind CSS

This project uses Tailwind CSS for styling. You can customize the theme in `tailwind.config.js`.

Custom utility classes are defined in `assets/css/src/frontend.css` using the `@layer components` directive.
