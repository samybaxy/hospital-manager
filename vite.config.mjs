import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';
import fs from 'fs';
import path from 'path';

// Remove leftovers of earlier builds (old chunks, source maps). The folder is
// shared with nothing else, but we don't empty it wholesale in case a deploy
// adds files there; only build output we recognise is removed.
const cleanStaleOutputPlugin = (outDir) => ({
  name: 'clean-stale-output',
  buildStart: () => {
    const dir = path.resolve(__dirname, outDir);
    if (!fs.existsSync(dir)) return;
    fs.readdirSync(dir)
      .filter((file) => /\.(js|map|css)$/.test(file))
      .forEach((file) => fs.unlinkSync(path.join(dir, file)));
  },
});

// Custom plugin to move CSS files after build
const moveCssPlugin = (outDir) => {
  return {
    name: 'move-css-plugin',
    closeBundle: () => {
      // Create the destination directory if it doesn't exist
      const destDir = path.resolve(__dirname, 'assets/css/dist');
      if (!fs.existsSync(destDir)) {
        fs.mkdirSync(destDir, { recursive: true });
      }

      // Check for CSS files in the JS dist directory
      const jsDistDir = path.resolve(__dirname, outDir);
      if (!fs.existsSync(jsDistDir)) return;
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
  // Production unless run with --mode development (yarn build:dev / watch).
  const isDev = mode === 'development';
  const outDir = process.env.HM_OUT_DIR || 'assets/js/dist';

  console.log(`Building in ${isDev ? 'development' : 'production'} mode`);

  return {
    plugins: [
      react(),
      cleanStaleOutputPlugin(outDir),
      moveCssPlugin(outDir)
    ],
    // Strip console.* and debugger from production bundles (some logged patient data).
    esbuild: isDev ? {} : { drop: ['console', 'debugger'] },
    base: '',
    build: {
        outDir,
        emptyOutDir: false, // stale files are removed by cleanStaleOutputPlugin
        sourcemap: isDev, // never ship source maps in production
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
