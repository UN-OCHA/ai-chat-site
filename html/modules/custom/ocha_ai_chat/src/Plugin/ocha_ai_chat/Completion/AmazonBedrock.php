<?php

namespace Drupal\ocha_ai_chat\Plugin\ocha_ai_chat\Completion;

use Drupal\ocha_ai_chat\Plugin\CompletionPluginBase;
use GuzzleHttp\Exception\BadResponseException;

/**
 * Amazon Bedrock completion generator.
 *
 * @OchaAiChatCompletion(
 *   id = "amazon_bedrock",
 *   label = @Translation("Amazon Bedrock"),
 *   description = @Translation("Use Amazon Bedrock as completion generator."),
 * )
 */
class AmazonBedrock extends CompletionPluginBase {

  /**
   * Completion model.
   *
   * @var string
   */
  protected string $model;

  /**
   * Completion URL.
   *
   * @var string
   */
  protected string $url;

  /**
   * {@inheritdoc}
   */
  public function answer(string $question, string $context): string {
    if (empty($question) || empty($context)) {
      return '';
    }

    $messages = [
      [
        'role' => 'system',
        'content' => $context,
      ],
      [
        'role' => 'user',
        'content' => $question,
      ],
    ];

    $payload = [
      'model' => $this->getModel(),
      'messages' => $messages,
      'temperature' => 0,
      // Retrieve that from the config.
      'max_tokens' => 512,
    ];

    try {
      /** @var \Psr\Http\Message\ResponseInterface $response */
      $response = $this->httpClient->request('POST', $this->getUrl(), [
        'json' => $payload,
        'timeout' => 600,
      ]);
    }
    catch (BadResponseException $exception) {
      $response = $exception->getResponse();
      $this->logger->error(strtr('Completion request failed with @status error: @error.', [
        '@status' => $response->getStatusCode(),
        '@error' => $exception->getMessage(),
      ]));
      return '';
    }

    try {
      $data = json_decode($response->getBody()->getContents(), TRUE);
    }
    catch (\Exception $exception) {
      $this->logger->error('Unable to decode completion response.');
      return '';
    }

    return trim($data['choices'][0]['message']['content'] ?? '');
  }

  /**
   * Get the completion URL.
   *
   * @return string
   *   Completion URL.
   */
  protected function getUrl():string {
    if (!isset($this->url)) {
      // @todo throw an error if this is not defined.
      $this->url = trim($this->config->get('amazon_bedrock_completion_url'));
    }
    return $this->url;
  }

  /**
   * Get the completion model.
   *
   * @return string
   *   Model name.
   */
  protected function getModel(): string {
    if (!isset($this->model)) {
      // @todo throw an error if this is not defined.
      $this->model = trim($this->config->get('amazon_bedrock_completion_model'));
    }
    return $this->model;
  }

}
