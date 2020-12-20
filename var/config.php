<?php

namespace AsyncPHP;

const USER_ID = 501;

const ORIGIN = 'localhost:8443';
const ORIGIN_SCHEME = 'https';
const ORIGIN_HOST = 'localhost';
const ORIGIN_PORT = 8443;
const ORIGIN_URI = ORIGIN_SCHEME . '://' . ORIGIN_HOST . ':' . ORIGIN_PORT;
const ORIGIN_REGEXP = '/^localhost:8443$/';

const ORIGIN_URIS = [
    '0.0.0.0:8443',
    '[::]:8443',
];

const REDIRECT_ORIGIN = 'localhost:8080';
const REDIRECT_ORIGIN_SCHEME = 'http';
const REDIRECT_ORIGIN_HOST = 'localhost';
const REDIRECT_ORIGIN_PORT = 8080;
const REDIRECT_ORIGIN_URI = REDIRECT_ORIGIN_SCHEME . '://' . REDIRECT_ORIGIN_HOST . ':' . REDIRECT_ORIGIN_PORT;
const REDIRECT_ORIGIN_REGEXP = '/^localhost:8080$/';

const REDIRECT_URIS = [
    '0.0.0.0:8080',
    '[::]:8080',
];
