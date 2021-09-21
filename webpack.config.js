const fs = require("fs");

const HtmlWebpackPlugin = require("html-webpack-plugin");
const MiniCssExtractPlugin = require("mini-css-extract-plugin");

// Create an entry and HtmlWebpackPlugin for each AtoM plugin folder with
// "webpack.entry.js" and "templates/_layout_start_webpack.php" files.
var entry = {};
var htmlPlugins = [];
fs.readdirSync(__dirname + "/plugins")
  .filter(
    (plugin) =>
      fs.existsSync(__dirname + "/plugins/" + plugin + "/webpack.entry.js") &&
      fs.existsSync(
        __dirname +
          "/plugins/" +
          plugin +
          "/templates/_layout_start_webpack.php"
      )
  )
  .forEach((plugin) => {
    entry[plugin] = "./plugins/" + plugin + "/webpack.entry.js";
    htmlPlugins.push(
      new HtmlWebpackPlugin({
        template:
          "./plugins/" + plugin + "/templates/_layout_start_webpack.php",
        filename: "../plugins/" + plugin + "/templates/_layout_start.php",
        publicPath: "/assets",
        chunks: [plugin],
        inject: false,
        minify: false,
      })
    );
  });

module.exports = {
  mode: process.env.NODE_ENV || "production",
  entry: entry,
  output: {
    path: __dirname + "/assets",
    filename: "../plugins/[name]/build/js/bundle.[contenthash].js",
  },
  module: {
    rules: [
      {
        test: /\.(sa|sc|c)ss$/i,
        use: [
          MiniCssExtractPlugin.loader,
          "css-loader",
          "resolve-url-loader",
          { loader: "sass-loader", options: { sourceMap: true } },
        ],
      },
    ],
  },
  plugins: htmlPlugins.concat([
    new MiniCssExtractPlugin({
      filename: "../plugins/[name]/build/css/bundle.[contenthash].css",
    }),
  ]),
};