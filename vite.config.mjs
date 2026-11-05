import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';
import fs from 'fs';
import path from 'path';

// Remove leftovers of earlier builds (old chunks, source maps). The folder is
// shared with nothing else, but we don't empty it wholesale in case a deploy
// adds files there; only build output we recognise is removed.
const DEFAULT_JS_DIST = path.resolve(__dirname, 'assets/js/dist');
const CSS_DIST = path.resolve(__dirname, 'assets/css/dist');

// Both plugins act on the output folder Vite actually uses (build.outDir, which
// `--outDir` on the command line overrides). They used to read a fixed path, so a
// scratch build with --outDir deleted the live bundle in assets/js/dist.
const cleanStaleOutputPlugin = () => {
  let outDir;
  return {
    name: 'clean-stale-output',
    configResolved: (config) => {
      outDir = path.resolve(config.root, config.build.outDir);
    },
    buildStart: () => {
      if (!outDir || !fs.existsSync(outDir)) return;
      fs.readdirSync(outDir)
        .filter((file) => /\.(js|map|css)$/.test(file))
        .forEach((file) => fs.unlinkSync(path.join(outDir, file)));
    },
  };
};

// Move the built CSS to assets/css/dist/frontend.css (what FrontendController
// enqueues). For a build into any other folder, keep it there as frontend.css.
const moveCssPlugin = () => {
  let outDir;
  return {
    name: 'move-css-plugin',
    configResolved: (config) => {
      outDir = path.resolve(config.root, config.build.outDir);
    },
    closeBundle: () => {
      if (!outDir || !fs.existsSync(outDir)) return;
      const destDir = outDir === DEFAULT_JS_DIST ? CSS_DIST : outDir;
      fs.mkdirSync(destDir, { recursive: true });
      fs.readdirSync(outDir)
        .filter((file) => file.endsWith('.css') && file !== 'frontend.css')
        .forEach((cssFile) => {
          const source = path.join(outDir, cssFile);
          const destination = path.join(destDir, 'frontend.css');
          try {
            fs.copyFileSync(source, destination);
            fs.unlinkSync(source);
            console.log(`Moved CSS file from ${source} to ${destination}`);
          } catch (err) {
            console.error(`Error moving CSS file: ${err}`);
          }
        });
    },
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
      cleanStaleOutputPlugin(),
      moveCssPlugin()
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
