const webpack = require('webpack');
const ExtractTextPlugin = require('extract-text-webpack-plugin');

const isProd = process.argv.indexOf('-p') !== -1; //are we in production?
const BundleAnalyzerPlugin = require('webpack-bundle-analyzer').BundleAnalyzerPlugin;


// Conditionally return a list of plugins to use based on the current environment.
// Repeat this pattern for any other config key (ie: loaders, etc).
function getPlugins() {
    const plugins = [];

    // Conditionally add plugins for Production builds.
    if (isProd) {
        plugins.push(new webpack.DefinePlugin({
            'process.env': {
                NODE_ENV: JSON.stringify('production')
            }
        }));
        plugins.push(new webpack.optimize.AggressiveMergingPlugin());
        plugins.push(new BundleAnalyzerPlugin());
    }

    plugins.push( new ExtractTextPlugin({ filename: 'data-admin.css', disable: false, allChunks: true }));

    return plugins;
}

let config = {
    entry: [
        './app.js',
        './sass/data-admin.scss' //sass entry point
    ],
    output: {
        path: __dirname + "/dist",
        filename: 'data-admin.js'
    },
    devtool: 'source-map',
    module: {
        rules: [{
            test: /\.js$/, // files ending with .js
            exclude: /node_modules/, // exclude the node_modules directory
            loader: 'babel-loader', // use this (babel-core) loader
            query: {
                presets: ['es2015', 'react'],
                plugins: ['transform-object-rest-spread']
            }
        },
            {
                test: /\.css$/,
                use: ['style-loader', 'css-loader']
            },
            {
                test: /\.(png|jpg|gif)$/,
                use: [
                    {
                        loader: 'file-loader',
                        options: {}
                    }
                ]
            },
            //load sass and compile (live reload)
            {
                test: /\.scss$/,
                loader: ExtractTextPlugin.extract({ fallback: 'style-loader', use: 'css-loader!sass-loader' })
            }
        ]
    },
    devServer: {
        inline: true,
        port: 4321//server port, change at your pleasure
    },
    resolve: {
        modules: ['app', 'node_modules']//where the import looks for module (avoid ../../../)
    },
    plugins: getPlugins()
};


module.exports = config;
