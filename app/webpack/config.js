'use strict';

const path = require('path');

function resolve(dir) {
    return path.join(__dirname, '..', '..', dir);
}

module.exports = {
    outputPath: resolve('public/dist'),
    publicPath: '/dist'
};