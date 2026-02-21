<?php

declare(strict_types=1);

namespace DialClient;

use DialClient\Util\Question;
use DialClient\Exception\ConfigurationException;
use DialClient\Http\GuzzleClient;
use DialClient\Dial\DialChatCompletions;
use GuzzleHttp\Exception\RequestException;

class Application
{
    private Question $question;
    private string $systemPrompt;
    private DialChatCompletions $dial;

    /**
     * @param array<int, string> $cliParams
     */
    public function __construct(array $cliParams)
    {
        $this->question = new Question();
        $defaultSystemPrompt = "You are an assistant who answers concisely and informatively.";
        $systemPromptArg = $cliParams[1] ?? null;

        if ($systemPromptArg === '-h' || $systemPromptArg === '--help') {
            fwrite(STDERR, "Usage: php ./app.php [system_prompt]\n");
            fwrite(STDERR, "If omitted, you'll be prompted to enter it (or press Enter to use the default).\n");
            exit(0);
        }

        $systemPrompt = is_string($systemPromptArg) ? trim($systemPromptArg) : '';
        $this->systemPrompt = $systemPrompt !== ''
            ? $systemPrompt
            : $this->question->ask('System prompt', $defaultSystemPrompt);

        $baseUri = (string) (getenv('DIAL_BASE_URI') ?: '');
        $apiKey = getenv('DIAL_API_KEY');
        $deploymentName = getenv('DIAL_DEPLOYMENT');

        $caBundle = getenv('DIAL_CA_BUNDLE');
        if ($caBundle === false || $caBundle === '') {
            $caBundle = getenv('CURL_CA_BUNDLE');
        }
        if ($caBundle === false || $caBundle === '') {
            $caBundle = getenv('SSL_CERT_FILE');
        }

        $guzzleOptions = [];
        if ($caBundle !== false && $caBundle !== '') {
            $guzzleOptions['verify'] = (string) $caBundle;
        }

        if ($baseUri === '' || $deploymentName === false || $deploymentName === '') {
            throw new ConfigurationException('Set env vars: DIAL_BASE_URI, DIAL_DEPLOYMENT (optional: DIAL_API_KEY)');
        }

        $apiKeyValue = $apiKey === false ? null : trim((string) $apiKey);

        $client = new GuzzleClient(
            baseUri: $baseUri,
            apiKey: null,
            guzzleOptions: $guzzleOptions,
        );
        $this->dial = new DialChatCompletions(
            client: $client,
            deploymentName: (string) $deploymentName,
            apiKeyHeaderValue: $apiKeyValue,
        );
    }

    public function run(bool $stream=false): void
    {
        try {
            $messages = [
                ['role' => 'system', 'content' => $this->systemPrompt],
            ];
            fwrite(STDOUT, "Type your question and press Enter. Type 'exit' to quit.\n\n");
            while (true) {
                $userQuestion = $this->question->ask('You');
                if ($userQuestion === '') {
                    continue;
                }
                if (strtolower($userQuestion) === 'exit') {
                    break;
                }
                $messages[] = ['role' => 'user', 'content' => $userQuestion];
                if ($stream) {
                    $result = $this->dial->stream([
                        'stream' => true,
                        'messages' => $messages,
                    ]);
                } else {
                    $result = $this->dial->create([
                        'messages' => $messages,
                    ]);
                }
                $messages[] = ['role' => 'assistant', 'content' => $result->content];
                fwrite(STDOUT, "Assistant: {$result->content}\n\n");
            }
        } catch (RequestException $e) {
            echo "HTTP request failed:" . $e->getMessage() . "\n";
        }
    }
}
