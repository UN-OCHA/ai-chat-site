<?php

namespace Drupal\ocha_ai_chat\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\ocha_ai_chat\Services\OchaAiChat;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Chat form for the Ocha AI Chat module.
 */
class OchaAiChatChatForm extends FormBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * The OCHA AI chat service.
   *
   * @var Drupal\ocha_ai_chat\Services\OchaAiChat
   */
  protected OchaAiChat $ochaAiChat;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\ocha_ai_chat\Services\OchaAiChat $ocha_ai_chat
   *   The OCHA AI chat service.
   */
  public function __construct(
    AccountProxyInterface $current_user,
    StateInterface $state,
    OchaAiChat $ocha_ai_chat
  ) {
    $this->currentUser = $current_user;
    $this->state = $state;
    $this->ochaAiChat = $ocha_ai_chat;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('state'),
      $container->get('ocha_ai_chat.chat')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $defaults = $this->ochaAiChat->getSettings();

    $source_url = $form_state->getValue(['source', 'url']) ?? $defaults['plugins']['source']['url'] ?? NULL;
    $source_limit = $form_state->getValue(['source', 'limit']) ?? $defaults['plugins']['source']['limit'] ?? 5;

    // Advanced options for test purposes.
    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced options'),
      '#open' => FALSE,
      '#access' => $this->currentUser->hasPermission('access ocha ai chat advanced features'),
    ];

    // Completion plugin.
    $completion_options = array_map(function ($plugin) {
      return $plugin->getPluginLabel();
    }, $this->ochaAiChat->getCompletionPluginManager()->getAvailablePlugins());

    $completion_default = $form_state->getValue('completion_plugin_id') ??
      $defaults['plugins']['completion']['plugin_id'] ??
      key($completion_options);

    $form['advanced']['completion_plugin_id'] = [
      '#type' => 'select',
      '#title' => $this->t('AI service'),
      '#description' => $this->t('Select the AI service to use to generate the answer.'),
      '#options' => $completion_options,
      '#default_value' => $completion_default,
      '#required' => TRUE,
    ];

    // Source of documents.
    $form['source'] = [
      '#type' => 'details',
      '#title' => $this->t('Source documents'),
      '#tree' => TRUE,
      '#open' => empty($source_url),
    ];

    $form['source']['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ReliefWeb river URL'),
      '#description' => $this->t('Filtered list of ReliefWeb content from https://reliefweb.int/updates to chat against.'),
      '#default_value' => $source_url,
      '#required' => TRUE,
      '#maxlength' => 2048,
    ];

    $form['source']['limit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Document limit'),
      '#description' => $this->t('Maximum number of documents to chat against.'),
      '#default_value' => $source_limit,
      '#required' => TRUE,
    ];

    $history = $form_state->getValue('history', '');
    $form['chat'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Chat'),
      '#access' => !empty($history),
      '#tree' => TRUE,
    ];
    $form['history'] = [
      '#type' => 'hidden',
      '#value' => $history,
    ];

    foreach (json_decode($history, TRUE) ?? [] as $index => $record) {
      $form['chat'][$index] = [
        '#type' => 'inline_template',
        '#template' => '<dl><dt>Question</dt><dd>{{ question }}</dd><dt>Answer</dt><dd>{{ answer }}</dd>{% if references %}<dt>References</dt><dd>{{ references }}</dd>{% endif %}</dl>',
        '#context' => [
          'question' => $record['question'],
          'answer' => $record['answer'],
          'references' => $this->formatReferences($record['references']),
        ],
      ];
    }

    $form['question'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Question'),
      '#default_value' => $form_state->getValue('question') ?? NULL,
      '#rows' => 2,
    ];

    // @todo add field with the result of the question.
    // @todo replace with a more interesting UI.
    // @todo add ajax submission.
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Ask'),
      '#description' => $this->t('It may take several minutes to get the answer.'),
      '#button_type' => 'primary',
    ];

    $form['#attached']['library'][] = 'ocha_ai_chat/ocha_ai_chat_chat_form';

    // @todo check if we need a theme.
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $source_url = $form_state->getValue(['source', 'url']);
    $source_limit = $form_state->getValue(['source', 'limit']);
    $question = $form_state->getValue('question');

    $completion_plugin_id = $form_state->getValue('completion_plugin_id');
    if (isset($completion_plugin_id)) {
      $completion_plugin = $this->ochaAiChat
        ->getCompletionPluginManager()
        ->getPlugin($completion_plugin_id);
    }
    else {
      $completion_plugin = NULL;
    }

    // Get the answer to the question.
    // @todo use server events etc. for a better UX.
    $data = $this->ochaAiChat->answer($question, $source_url, $source_limit, $completion_plugin);

    // Generate a list of references used to generate the answer.
    $references = [];
    foreach ($data['passages'] as $passage) {
      $reference_source_url = $passage['source']['url'];
      if (!isset($references[$reference_source_url])) {
        $references[$reference_source_url] = [
          'title' => $passage['source']['title'],
          'url' => $reference_source_url,
          'attachments' => [],
        ];
      }
      if (isset($passage['source']['attachment'])) {
        $attachment_url = $passage['source']['attachment']['url'];
        $attachment_page = $passage['source']['attachment']['page'];
        $references[$reference_source_url]['attachments'][$attachment_url][$attachment_page] = $attachment_page;
      }
    }

    // Update the chat history.
    $history = json_decode($form_state->getValue('history', ''), TRUE) ?? [];
    $history[] = [
      'question' => $question,
      'answer' => $data['answer'],
      'references' => $references,
    ];

    $form_state->setValue('history', json_encode($history));

    // Rebuild the form so that it is reloaded with the inputs from the user
    // as well as the AI answer.
    $form_state->setRebuild(TRUE);
  }

  /**
   * Format a list of references.
   *
   * @param array $references
   *   References.
   *
   * @return array
   *   Render array.
   */
  protected function formatReferences(array $references): array {
    if (empty($references)) {
      return [];
    }

    $link_options = [
      'attributes' => [
        'rel' => 'noreferrer noopener',
        'target' => '_blank',
      ],
    ];

    $items = [];
    foreach ($references as $reference) {
      $links = [];
      // Link to the document.
      $links[] = [
        'title' => $reference['title'],
        'url' => Url::fromUri($reference['url'], $link_options),
        'attributes' => [
          'class' => [
            'ocha-ai-chat-reference__link',
            'ocha-ai-chat-reference__link--document',
          ],
        ],
      ];
      // link(s) to the attachment(s).
      foreach ($reference['attachments'] ?? [] as $url => $pages) {
        $links[] = [
          'title' => $this->formatPlural(count($pages), 'attachment (p. @pages)', 'attachment (pp. @pages)', [
            '@pages' => implode(', ', $pages),
          ]),
          'url' => Url::fromUri($url, $link_options),
          'attributes' => [
            'class' => [
              'ocha-ai-chat-reference__link',
              'ocha-ai-chat-reference__link--attachment',
            ],
          ],
        ];
      }
      $items[] = [
        '#theme' => 'links',
        '#links' => $links,
        '#attributes' => [
          'class' => [
            'ocha-ai-chat-reference',
          ],
        ],
        '#wrapper_attributes' => [
          'class' => [
            'ocha-ai-chat-reference_list__item',
          ],
        ],
      ];
    }
    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#list_type' => 'ol',
      '#attributes' => [
        'class' => [
          'ocha-ai-chat-reference_list',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ocha_ai_chat_chat_form';
  }

}
