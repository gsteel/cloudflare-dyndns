<?php

declare(strict_types=1);

use Http\Client\Curl\Client;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\StreamFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Webmozart\Assert\Assert;

require __DIR__ . '/vendor/autoload.php';

$config = require __DIR__ . '/config.php';
$token = $config['token'] ?? null;
Assert::string($token, 'CloudFlare API Token not set');
$zones = $config['zones'] ?? [];
Assert::allString(array_keys($zones));

$httpClient = new Client();
$targetIp = fetchExternalIp4Address($httpClient);
printf('Your current external IP address is %s' . PHP_EOL, $targetIp);

/**
 * @psalm-var array<string, list<string>> $zones
 */
foreach ($zones as $domain => $records) {
    $zoneId = getZoneId($domain, $token, $httpClient);
    printf('Zone: %s' . PHP_EOL, $domain);
    Assert::allString($records);
    foreach ($records as $subdomain) {
        if ($subdomain === '@') {
            $subdomain = $domain;
        } else {
            $subdomain = sprintf('%s.%s', $subdomain, $domain);
        }

        $record = getFirstARecord($subdomain, $zoneId, $token, $httpClient);
        $recordId = $record['id'] ?? null;
        Assert::string($recordId);
        $currentIp = $record['content'] ?? null;
        Assert::string($currentIp);
        Assert::ipv4($currentIp);
        printf('The current IP address for "%s" is "%s"' . PHP_EOL, $subdomain, $currentIp);
        if ($currentIp === $targetIp) {
            printf('The IP address for "%s" is already correct' . PHP_EOL, $subdomain);
            continue;
        }

        setARecord($targetIp, $recordId, $zoneId, $token, $httpClient);
        printf('The A record has been updated for "%s"' . PHP_EOL, $subdomain);
    }
}

function fetchExternalIp4Address(ClientInterface $client): string
{
    $request = (new RequestFactory())->createRequest('GET', 'https://api.ipify.org?format=json');
    $body = responseJsonToArray($request, $client);
    Assert::keyExists($body, 'ip');
    Assert::string($body['ip']);
    Assert::ipv4($body['ip']);

    return $body['ip'];
}

function debugDump(RequestInterface $request, ResponseInterface $response): string
{
    return <<<EOF
        Request URI: {$request->getUri()}
        Response Code: {$response->getStatusCode()}
        Reason Phrase: {$response->getReasonPhrase()}
        Response Body:
        {$response->getBody()}
        EOF;
}

/** @return array<array-key, mixed> */
function responseJsonToArray(RequestInterface $request, ClientInterface $client): array
{
    $response = $client->sendRequest($request);
    Assert::eq(200, $response->getStatusCode(), sprintf(
        'Expected a 200 response code. Received %d:%s%s',
        $response->getStatusCode(),
        PHP_EOL,
        debugDump($request, $response)
    ));

    $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    Assert::isArray($body);

    return $body;
}

function cloudflareRequestFactory(string $method, string $path, string $apiToken): RequestInterface
{
    $url = sprintf('https://api.cloudflare.com/client/v4%s', $path);
    $request = (new RequestFactory())->createRequest($method, $url);

    return $request->withHeader('Content-Type', 'application/json')
        ->withHeader('Authorization', sprintf('Bearer %s', $apiToken));
}

/** @return array<array-key, mixed> */
function firstCloudflareResult(RequestInterface $request, ClientInterface $client): array
{
    $body = responseJsonToArray($request, $client);
    Assert::keyExists($body, 'result', 'Unexpected response body format');
    Assert::isArray($body['result']);
    Assert::greaterThanEq(count($body['result']), 1, 'Expected at least one result for the query but none received');
    $result = reset($body['result']);
    Assert::isArray($result);

    return $result;
}

function getZoneId(string $name, string $apiToken, ClientInterface $client): string
{
    $path = sprintf('/zones?name=%s', $name);
    $request = cloudflareRequestFactory('GET', $path, $apiToken);
    $zoneData = firstCloudflareResult($request, $client);
    Assert::keyExists($zoneData, 'id');
    Assert::string($zoneData['id']);

    return $zoneData['id'];
}

/** @return array<array-key, mixed> */
function getFirstARecord(string $name, string $zoneId, string $apiToken, ClientInterface $client): array
{
    $path = sprintf('/zones/%s/dns_records?type=A&name=%s', $zoneId, urlencode($name));
    $request = cloudflareRequestFactory('GET', $path, $apiToken);

    return firstCloudflareResult($request, $client);
}

function setARecord(string $targetIp, string $recordId, string $zoneId, string $apiToken, ClientInterface $client): void
{
    $path = sprintf('/zones/%s/dns_records/%s', $zoneId, $recordId);
    $request = cloudflareRequestFactory('PATCH', $path, $apiToken);
    $body = (new StreamFactory())->createStream(json_encode([
        'content' => $targetIp,
    ]));
    $request = $request->withBody($body);
    responseJsonToArray($request, $client);
}
