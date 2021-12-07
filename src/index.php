<?php
// usage: php -S 127.0.0.1:9999 src/index.php

namespace Rsky\PhpProxy;

require __DIR__.'/../vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\ResponseInterface;

const HOST = '127.0.0.1';
const PORT = 9999;
const USERNAME = 'pxuser';
const PASSWORD = 'pxpass';

function main(): void
{
    $request = Request::createFromGlobals();

    //debug_print_request($request);

    if ($request->getPathInfo() === '/proxy.pac') {
        output_example_pac();
    } elseif (!check_proxy_auth($request)) {
        require_proxy_auth();
    } else {
        $response = perform_request($request);
        output_response($response);
    }
}

function debug_print_request(Request $request): void
{
    $stderr = fopen('php://stderr', 'w');
    fprintf($stderr, print_r($request->headers, true));
    fflush($stderr);
}

function check_proxy_auth(Request $request): bool
{
    $basic = sprintf('Basic %s', base64_encode(USERNAME . ':' . PASSWORD));

    return $request->headers->contains('proxy-authorization', $basic);
}

function require_proxy_auth(): void
{
    header('Proxy-Authenticate: Basic');
    header('HTTP/1.1 407 Proxy Authentication Required', true, 407);
}

function output_example_pac(): void
{
    header('Content-Type: application/x-ns-proxy-autoconfig');
    $format = <<<'PAC'
function FindProxyForURL(url, host) {
    if (host === 'example.com') {
        return 'PROXY %1$s:%2$d';
    }
    return 'DIRECT';
}
PAC;
    printf($format, HOST, PORT);
}

function perform_request(Request $request): ResponseInterface
{
    $method = $request->getMethod();
    $uri = $request->getUri();
    $headers = [];
    foreach ($request->headers->all() as $key => $values) {
        if (str_starts_with($key, 'proxy-') || $key === 'upgrade-insecure-requests') {
            continue;
        } elseif (!is_null($values[0])) {
            $headers[convert_header_case($key)] = $values[0];
        }
    }

    $options = ['headers' => $headers];
    if (in_array($method, ['POST', 'PUT'])) {
        $options['body'] = $request->getContent();
    }

    return HttpClient::create()->request($method, $uri, $options);
}

function output_response(ResponseInterface $response): void
{
    foreach ($response->getHeaders(false) as $key => $values) {
        $header = convert_header_case($key);
        foreach ($values as $value) {
            header(sprintf('%s: %s', $header, $value));
        }
    }

    echo $response->getContent(false);
}

function convert_header_case(string $key): string
{
    return implode('-', array_map(function (string $s): string {
        return ucfirst(strtolower($s));
    }, explode('-', $key)));
}

main();
