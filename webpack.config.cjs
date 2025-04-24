const path = require('path');

// Absolute path to entry file for clarity
const entryPath = path.resolve(__dirname, 'assets/js/src/index.js');
console.log('Entry path:', entryPath);

module.exports = {
  mode: 'production',
  entry: entryPath,
  output: {
    filename: 'bundle.js',
    path: path.resolve(__dirname, 'assets/js/dist'),
    publicPath: '/wp-content/plugins/hospital-manager/assets/js/dist/'
  },
  module: {
    rules: [
      {
        test: /\.(js|jsx)$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: {
            sourceType: 'module',
            presets: [
              ['@babel/preset-env', {
                targets: {
                  browsers: ['last 2 versions', 'not dead']
                },
                useBuiltIns: 'usage',
                corejs: 3,
                modules: false
              }],
              ['@babel/preset-react', {
                runtime: 'automatic'
              }]
            ]
          }
        }
      },
      {
        test: /\.css$/,
        use: ['style-loader', 'css-loader']
      }
    ]
  },
  resolve: {
    extensions: ['.js', '.jsx']
  }
};
