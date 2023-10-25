<?php

namespace Drupal\ocha_ai_chat\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Logs form for the Ocha AI Chat module.
 */
class OchaAiChatLogsForm extends FormBase {


  /**
   * The database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    Connection $database,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Expose the filters.
    $form['#method'] = 'GET';
    $form['#cache'] = ['max-age' => 0];
    $form['#after_build'] = ['::afterBuild'];

    $question = $this->getRequest()->get('question') ?? '';
    $answer = $this->getRequest()->get('answer') ?? '';
    $user = $this->getRequest()->get('user') ?? '';

    $form['filters'] = [
      '#type' => 'details',
      '#title' => $this->t('Filters'),
    ];

    $form['filters']['question'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Question'),
      '#default_value' => $question,
    ];

    $form['filters']['answer'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Answer'),
      '#default_value' => $answer,
    ];

    $form['filters']['user'] = [
      '#type' => 'select',
      '#title' => $this->t('User'),
      '#options' => [],
      '#default_value' => $user,
      '#empty_option' => $this->t('- Select -'),
    ];

    $user_ids = $this->database
      ->select('ocha_ai_chat_logs', 'ocha_ai_chat_logs')
      ->fields('ocha_ai_chat_logs', ['uid'])
      ->distinct()
      ->execute()
      ?->fetchCol() ?? [];

    $users = [];
    if (!empty($user_ids)) {
      $users = $this->entityTypeManager
        ->getStorage('user')
        ->loadMultiple($user_ids);

      $form['filters']['user']['#options'] = array_map(function ($user) {
        return $user->label();
      }, $users);
    }

    $form['filters']['submit'] = [
      '#type' => 'submit',
      // No name so it doesn't appear in the query parameters.
      '#name' => '',
      '#value' => $this->t('Filter'),
      '#button_type' => 'primary',
    ];

    $header = [
      'timestamp' => [
        'data' => $this->t('Timestamp'),
        'specifier' => 'timestamp',
        'sort' => 'desc',
      ],
      'source' => [
        'data' => $this->t('Source'),
      ],
      'question' => [
        'data' => $this->t('Question'),
      ],
      'answer' => [
        'data' => $this->t('Answer'),
      ],
      'context' => [
        'data' => $this->t('Context'),
      ],
      'status' => [
        'data' => $this->t('Status'),
      ],
      'duration' => [
        'data' => $this->t('Duration'),
      ],
      'user' => [
        'data' => $this->t('User'),
      ],
      'stats' => [
        'data' => $this->t('Stats'),
      ],
    ];

    // Retrieve the log records.
    $query = $this->database
      ->select('ocha_ai_chat_logs', 'ocha_ai_chat_logs')
      ->fields('ocha_ai_chat_logs')
      ->extend('\Drupal\Core\Database\Query\TableSortExtender')
      ->orderByHeader($header)
      ->extend('\Drupal\Core\Database\Query\PagerSelectExtender')
      ->limit(20);

    if (!empty($question)) {
      $query->condition('ocha_ai_chat_logs.question', '%' . $question . '%', 'LIKE');
    }
    if (!empty($answer)) {
      $query->condition('ocha_ai_chat_logs.question', '%' . $answer . '%', 'LIKE');
    }
    if (!empty($user)) {
      $query->condition('ocha_ai_chat_logs.uid', $user, '=');
    }

    $link_options = [
      'attributes' => [
        'rel' => 'noreferrer noopener',
        'target' => '_blank',
      ],
    ];

    $rows = [];
    foreach ($query->execute() ?? [] as $record) {
      $passages = json_decode($record->passages, TRUE);
      $stats = json_decode($record->stats, TRUE);

      $rows[] = [
        'timestamp' => gmdate('Y-m-d H:i:s', $record->timestamp),
        'source' => Link::fromTextAndUrl($this->t('Link'), Url::fromUri($record->source_url, $link_options)),
        'question' => $record->question,
        'answer' => $record->answer,
        'context' => [
          'data' => [
            '#type' => 'details',
            '#title' => $this->t('Context'),
            '#open' => FALSE,
            'passages' => $this->formatPassages($passages),
          ],
        ],
        'status' => $record->status,
        'duration' => $record->duration,
        'user' => isset($users[$record->uid]) ? $users[$record->uid]->label() : '',
        'stats' => [
          'data' => [
            '#type' => 'details',
            '#title' => $this->t('Stats'),
            '#open' => FALSE,
            'passages' => $this->formatStats($stats),
          ],
        ],
      ];
    }

    $form['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No content has been found.'),
    ];

    $form['pager'] = [
      '#type' => 'pager',
    ];

    $form['#attached']['library'][] = 'ocha_ai_chat/ocha_ai_chat_logs_form';

    return $form;
  }

  /**
   * Remove elements from being submitted as GET variables.
   *
   * @param array $element
   *   From element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return array
   *   The modified form element.
   */
  public function afterBuild(array $element, FormStateInterface $form_state): array {
    // Remove the form_token, form_build_id and form_id from the GET parameters.
    unset($element['form_token']);
    unset($element['form_build_id']);
    unset($element['form_id']);

    return $element;
  }

  /**
   * Format the stats.
   *
   * @param array $stats
   *   Stats.
   *
   * @return array
   *   Render array for the stats.
   */
  protected function formatStats(array $stats): array {
    $items = [];
    foreach ($stats as $key => $value) {
      $items[] = [
        '#type' => 'inline_template',
        '#template' => '<strong>{{ key }}:</strong> {{ value }}',
        '#context' => [
          'key' => $key,
          'value' => round($value, 3) . 's',
        ],
      ];
    }
    return [
      '#theme' => 'item_list',
      '#items' => $items,
    ];
  }

  /**
   * Format the text passages.
   *
   * @param array $passages
   *   Passages.
   *
   * @return array
   *   Render array for the passages.
   */
  protected function formatPassages(array $passages): array {
    $items = [];
    foreach ($passages as $passage) {
      $source_title = $passage['source']['title'];
      if (!empty($passage['source']['page'])) {
        $source_title .= ' (page ' . $passage['source']['page'] . ')';
      }
      $source_url = Url::fromUri($passage['source']['url']);

      $items[] = [
        '#type' => 'inline_template',
        '#template' => '{{ text }}<br><small>Source: {{ source }}</small>',
        '#context' => [
          'text' => $passage['text'],
          'source' => Link::fromTextAndUrl($source_title, $source_url),
        ],
      ];
    }
    return [
      '#theme' => 'item_list',
      '#items' => $items,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ocha_ai_chat_logs_form';
  }

}
