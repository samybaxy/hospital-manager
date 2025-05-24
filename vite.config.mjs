import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';
import fs from 'fs';
import path from 'path';

// Custom plugin to move CSS files after build
const moveCssPlugin = () => {
  return {
    name: 'move-css-plugin',
    closeBundle: () => {
      // Create the destination directory if it doesn't exist
      const destDir = path.resolve(__dirname, 'assets/css/dist');
      if (!fs.existsSync(destDir)) {
        fs.mkdirSync(destDir, { recursive: true });
      }

      // Check for CSS files in the JS dist directory
      const jsDistDir = path.resolve(__dirname, 'assets/js/dist');
      const cssFiles = fs.readdirSync(jsDistDir).filter(file => file.endsWith('.css'));

      // Move any CSS files to the CSS dist directory
      cssFiles.forEach(cssFile => {
        const source = path.join(jsDistDir, cssFile);
        const destination = path.join(destDir, 'frontend.css');
        
        try {
          fs.copyFileSync(source, destination);
          fs.unlinkSync(source); // Remove the original file
          console.log(`Moved CSS file from ${source} to ${destination}`);
        } catch (err) {
          console.error(`Error moving CSS file: ${err}`);
        }
      });
    }
  };
};

export default defineConfig(({ mode }) => {
  // Read from .env files
  const isDev = mode === 'development' || process.env.NODE_ENV === 'development';
  
  // Log environment for debugging during build
  console.log(`Building in ${isDev ? 'development' : 'production'} mode`);
  
  return {
    plugins: [
      react(),
      moveCssPlugin()
    ],
    base: '',
    build: {
        outDir: 'assets/js/dist',
        emptyOutDir: false, // Don't empty out dir as we only want to update JS files here
        sourcemap: isDev ? true : false, // Enable sourcemaps in development mode,
        sourcemapExcludeSources: true, // Don't include source code in maps
        minify: isDev ? false : true, // Don't minify in development mode
        rollupOptions: {
            input: {
                bundle: resolve(__dirname, 'assets/js/src/index.jsx'),
            },
            output: {
                entryFileNames: 'bundle.js',
                chunkFileNames: '[name]-[hash].js',
                assetFileNames: (assetInfo) => {
                    // Place files directly in js/dist without creating an additional assets directory
                    return '[name]-[hash][extname]';
                }
            },
        }
    },
    define: {
      // This ensures React runs in development mode when we use --mode development
      'process.env.NODE_ENV': JSON.stringify(isDev ? 'development' : 'production')
    },
    resolve: {
      alias: {
        '@': resolve(__dirname, 'assets/js/src')
      }
    }
  };
});
