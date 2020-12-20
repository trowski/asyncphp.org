'use strict';

const path = require('path');
const config = require('./app/webpack/config');
const HtmlWebpackPlugin = require('html-webpack-plugin');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const VueLoaderPlugin = require('vue-loader/lib/plugin');

function resolve(dir) {
    return path.join(__dirname, dir);
}

process.traceDeprecation = true;

// noinspection WebpackConfigHighlighting
module.exports = {
    context: resolve('.'),
    entry: {
        app: './app/index.js'
    },
    output: {
        path: config.outputPath,
        publicPath: config.publicPath
    },
    resolve: {
        extensions: ['.ts', '.js'],
        alias: {
            'vue$': 'vue/dist/vue.esm.js',
            '@': path.resolve(__dirname, 'app')
        }
    },
    plugins: [
        new CleanWebpackPlugin(),
        new HtmlWebpackPlugin({
            title: 'asyncphp.org',
            template: resolve('app/index.ejs')
        }),
        new VueLoaderPlugin()
    ],
    module: {
        rules: [
            {
                test: /\.vue$/,
                loader: 'vue-loader',
            },
            {
                test: /\.js$/,
                loader: 'babel-loader',
                include: [resolve('app')]
            },
            {
                test: /\.(png|jpe?g|gif|svg)(\?.*)?$/,
                loader: 'file-loader',
            },
            {
                test: /\.scss$/,
                use: [
                    {
                        loader: 'style-loader'
                    },
                    {
                        loader: 'css-loader'
                    },
                    {
                        loader: 'postcss-loader',
                        options: {
                            postcssOptions: {
                                plugins: function() {
                                    return [
                                        require('autoprefixer')
                                    ]
                                }
                            }
                        }
                    },
                    {
                        loader: 'sass-loader'
                    }
                ]
            }
        ]
    }
}