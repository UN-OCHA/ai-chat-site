<?php

declare(strict_types=1);

namespace Drupal\ocha_ai_chat\Plugin\ocha_ai_chat\Completion;

use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Sts\StsClient;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ocha_ai_chat\Plugin\CompletionPluginBase;

/**
 * AWS Bedrock completion generator.
 *
 * @OchaAiChatCompletion(
 *   id = "aws_bedrock",
 *   label = @Translation("AWS Bedrock"),
 *   description = @Translation("Use AWS Bedrock as completion generator."),
 * )
 */
class AwsBedrock extends CompletionPluginBase {

  /**
   * AWS Bedrock API client.
   *
   * @var \Aws\BedrockRuntime\BedrockRuntimeClient
   */
  protected BedrockRuntimeClient $apiClient;

  /**
   * {@inheritdoc}
   */
  public function answer(string $question, string $context): string {
    if (empty($question) || empty($context)) {
      return '';
    }

    // @todo review what is a good template for AWS titan model.
    $prompt = strtr($this->getPluginSetting('prompt_template'), [
      '{{ context }}' => $context,
      '{{ question }}' => $question,
    ]);

    $payload = [
      'accept' => 'application/json',
      'body' => json_encode([
        'inputText' => $prompt,
        'textGenerationConfig' => [
          'maxTokenCount' => $this->getPluginSetting('max_tokens', 512),
          // @todo adjust based on the prompt.
          'stopSequences' => [],
          'temperature' => 0.0,
          'topP' => 0.9,
        ],
      ]),
      'contentType' => 'application/json',
      'modelId' => $this->getPluginSetting('model'),
    ];

    try {
      /** @var \Aws\Result $response */
      $response = $this->getApiClient()->invokeModel($payload);
    }
    catch (\Exception $exception) {
      $this->getLogger()->error(strtr('Completion request failed with error: @error.', [
        '@error' => $exception->getMessage(),
      ]));
      return '';
    }

    try {
      $data = json_decode($response->get('body')->getContents(), TRUE);
    }
    catch (\Exception $exception) {
      $this->getLogger()->error('Unable to decode completion response.');
      return '';
    }

    return trim($data['results'][0]['outputText'] ?? '');
  }

  /**
   * Get the Bedrock API Client.
   *
   * @return \Aws\BedrockRuntime\BedrockRuntimeClient
   *   API Client.
   */
  protected function getApiClient(): BedrockRuntimeClient {
    if (!isset($this->apiClient)) {
      $region = $this->getPluginSetting('region');
      $role_arn = $this->getPluginSetting('role_arn', NULL, FALSE);

      if (!empty($role_arn)) {
        $stsClient = new StsClient([
          'region' => $region,
          'version' => 'latest',
        ]);

        $result = $stsClient->AssumeRole([
          'RoleArn' => $role_arn,
          'RoleSessionName' => 'aws-bedrock-ocha-ai-chat',
        ]);

        $credentials = [
          'key'    => $result['Credentials']['AccessKeyId'],
          'secret' => $result['Credentials']['SecretAccessKey'],
          'token'  => $result['Credentials']['SessionToken'],
        ];
      }
      else {
        $credentials = [
          'key' => $this->getPluginSetting('api_key'),
          'secret' => $this->getPluginSetting('api_secret'),
        ];
      }

      $options = [
        'credentials' => $credentials,
        'region'  => $region,
      ];

      $endpoint = $this->getPluginSetting('endpoint', NULL, FALSE);
      if (!empty($endpoint)) {
        $options['endpoint'] = $endpoint;
      }

      $this->apiClient = new BedrockRuntimeClient($options);
    }
    return $this->apiClient;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $plugin_type = $this->getPluginType();
    $plugin_id = $this->getPluginId();
    $config = $this->getConfiguration() + $this->defaultConfiguration();

    // Empty endpoint is allowed in which case the SDK will generate it.
    $form['plugins'][$plugin_type][$plugin_id]['endpoint']['#required'] = FALSE;
    $form['plugins'][$plugin_type][$plugin_id]['endpoint']['#description'] = $this->t('Endpoint of the API. Leave empty to use the official one.');

    // Remove the requirement for the API key as it's possible to acces the API
    // via the Role ARN.
    $form['plugins'][$plugin_type][$plugin_id]['api_key']['#required'] = FALSE;

    // API secret. Not mandatory for the same reason as the API key.
    $form['plugins'][$plugin_type][$plugin_id]['api_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API secret'),
      '#description' => $this->t('Optional secret to access the API.'),
      '#default_value' => $config['api_secret'] ?? NULL,
    ];

    // Role ARN to access the API as an alternative to the API key.
    $form['plugins'][$plugin_type][$plugin_id]['role_arn'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Role ARN'),
      '#description' => $this->t('Role ARN to access the API.'),
      '#default_value' => $config['role_arn'] ?? NULL,
    ];

    return $form;
  }

}
