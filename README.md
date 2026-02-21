## DialClient

HTTP client library built on top of Guzzle with sync/async requests and stream support.

API specification: https://dialx.ai/dial_api

### Install

```bash
composer require distvan/dialclient
```

### Basic usage (sync)

`DialChatCompletions::create()` returns a `Message` (role + content).

```php
<?php

use DialClient\Dial\DialChatCompletions;
use DialClient\Http\GuzzleClient;

$client = new GuzzleClient(
    baseUri: 'https://your-dial-host.example',
    apiKey: null, // (optional) sets Authorization: Bearer ...
);

$chat = new DialChatCompletions(
    client: $client,
    deploymentName: 'your-deployment',
    apiKeyHeaderValue: null, // (optional) sets `api-key: ...`
);

// Alternatively, omit deploymentName and pass it in the payload as `deployment_name` or `deployment`.

$message = $chat->create([
    'messages' => [
        ['role' => 'user', 'content' => 'Hello!'],
    ],
]);

echo $message->content;
```

### Local env vars (scripts)

If you want to run the included [app.php](app.php) locally, you can set the required environment variables using the scripts in `./scripts`.

The CLI expects:

- `DIAL_BASE_URI` (required)
- `DIAL_DEPLOYMENT` (required)
- `DIAL_API_KEY` (optional; sent as `api-key` header)
- `DIAL_CA_BUNDLE` (optional; used as Guzzle `verify` path)

`DIAL_API_KEY` (if set) is sent as an `api-key` header by [app.php](app.php) via `DialChatCompletions`.

Note: [app.php](app.php) currently runs the CLI in streaming mode (`$app->run(true)`), but it prints the final assembled answer (not token-by-token).

**Bash / Git Bash**

```bash
source ./scripts/dial-env.sh
php ./app.php
```

**PowerShell**

```powershell
. .\scripts\dial-env.ps1
php .\app.php
```

### Run the CLI chat example

This repo includes a tiny interactive CLI chat in [app.php](app.php) (backed by `DialClient\Application`).

- Pass an optional `system_prompt` as the first argument.
- Type `exit` to quit.

**PowerShell**

```powershell
composer install
. .\scripts\dial-env.ps1
php .\app.php

# With an explicit system prompt:
php .\app.php "You are a helpful assistant."

# Help:
php .\app.php --help
```

**Bash / Git Bash**

```bash
composer install
source ./scripts/dial-env.sh
php ./app.php

# With an explicit system prompt:
php ./app.php "You are a helpful assistant."

# Help:
php ./app.php --help
```

### SSL / cURL error 60 (Windows)

If you see `cURL error 60: SSL certificate problem: unable to get local issuer certificate`, PHP/cURL can't validate the certificate chain.

- Preferred fix: set `DIAL_CA_BUNDLE` to the path of a PEM CA bundle (or your corporate root/intermediate CA in PEM format). Example:

```powershell
$env:DIAL_CA_BUNDLE = (Resolve-Path .\scripts\cacert.pem).Path
```

### Async

`createAsync()` returns a Promise that resolves to the decoded JSON array returned by the server.

```php
$promise = $chat->createAsync(['model' => 'your-model', 'messages' => []]);
$promise->then(function (array $result): void {
    // handle result
});
```

### Streaming

The API streams SSE events (`data: ...`) where each JSON chunk contains `choices[0].delta` (partial output).
The stream ends with `data: [DONE]`.

In many deployments, early chunks may contain only role/metadata and no `delta.content`, and `finish_reason` stays `null` until the final chunk.

To print tokens/fragments as they arrive:

```php
foreach ($chat->streamText(['messages' => []]) as $delta) {
    echo $delta;
}
```

If you need access to the full decoded chunks:

```php
foreach ($chat->streamChunks(['messages' => []]) as $chunk) {
    // $chunk is the decoded JSON array for one SSE event
}
```

If you just want the final assembled message content:

```php
$message = $chat->stream(['messages' => []]);
echo $message->content;
```

### Xdebug step-debug noise (CLI)

If you see `Xdebug: [Step Debug] Time-out connecting to debugging client ...` when running `composer test` / `composer phpstan`, disable step-debug for the Composer process:

```powershell
$env:XDEBUG_MODE = 'off'
composer test
```
