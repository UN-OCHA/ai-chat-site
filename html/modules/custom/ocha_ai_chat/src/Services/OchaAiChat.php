<?php

namespace Drupal\ocha_ai_chat\Services;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\ocha_ai_chat\Plugin\CompletionPluginInterface;
use Drupal\ocha_ai_chat\Plugin\CompletionPluginManagerInterface;
use Drupal\ocha_ai_chat\Plugin\EmbeddingPluginInterface;
use Drupal\ocha_ai_chat\Plugin\EmbeddingPluginManagerInterface;
use Drupal\ocha_ai_chat\Plugin\SourcePluginInterface;
use Drupal\ocha_ai_chat\Plugin\SourcePluginManagerInterface;
use Drupal\ocha_ai_chat\Plugin\TextExtractorPluginInterface;
use Drupal\ocha_ai_chat\Plugin\TextExtractorPluginManagerInterface;
use Drupal\ocha_ai_chat\Plugin\TextSplitterPluginInterface;
use Drupal\ocha_ai_chat\Plugin\TextSplitterPluginManagerInterface;
use Drupal\ocha_ai_chat\Plugin\VectorStorePluginInterface;
use Drupal\ocha_ai_chat\Plugin\VectorStorePluginManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * OCHA AI Chat service.
 */
class OchaAiChat {

  /**
   * OCHA AI Chat config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * Completion plugin manager.
   *
   * @var \Drupal\ocha_ai_chat\Plugin\CompletionPluginManagerInterface
   */
  protected CompletionPluginManagerInterface $completionPluginManager;

  /**
   * Embedding plugin manager.
   *
   * @var \Drupal\ocha_ai_chat\Plugin\EmbeddingPluginManagerInterface
   */
  protected EmbeddingPluginManagerInterface $embeddingPluginManager;

  /**
   * Source plugin manager.
   *
   * @var \Drupal\ocha_ai_chat\Plugin\SourcePluginManagerInterface
   */
  protected SourcePluginManagerInterface $sourcePluginManager;

  /**
   * Text extractor plugin manager.
   *
   * @var \Drupal\ocha_ai_chat\Plugin\TextExtractorPluginManagerInterface
   */
  protected TextExtractorPluginManagerInterface $textExtractorPluginManager;

  /**
   * Text splitter plugin manager.
   *
   * @var \Drupal\ocha_ai_chat\Plugin\TextSplitterPluginManagerInterface
   */
  protected TextSplitterPluginManagerInterface $textSplitterPluginManager;

  /**
   * Vector store manager.
   *
   * @var \Drupal\ocha_ai_chat\Plugin\VectorStorePluginManagerInterface
   */
  protected VectorStorePluginManagerInterface $vectorStorePluginManager;

  /**
   * Static cache for the settings.
   *
   * @var array
   */
  protected array $settings;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\ocha_ai_chat\Plugin\CompletionPluginManagerInterface $completion_plugin_manager
   *   The completion plugin manager.
   * @param \Drupal\ocha_ai_chat\Plugin\EmbeddingPluginManagerInterface $embedding_plugin_manager
   *   The embedding plugin manager.
   * @param \Drupal\ocha_ai_chat\Plugin\SourcePluginManagerInterface $source_plugin_manager
   *   The source plugin manager.
   * @param \Drupal\ocha_ai_chat\Plugin\TextExtractorPluginManagerInterface $text_extractor_plugin_manager
   *   The text extractor plugin manager.
   * @param \Drupal\ocha_ai_chat\Plugin\TextSplitterPluginManagerInterface $text_splitter_plugin_manager
   *   The text splitter plugin manager.
   * @param \Drupal\ocha_ai_chat\Plugin\VectorStorePluginManagerInterface $vector_store_plugin_manager
   *   The vector store plugin manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
    StateInterface $state,
    CompletionPluginManagerInterface $completion_plugin_manager,
    EmbeddingPluginManagerInterface $embedding_plugin_manager,
    SourcePluginManagerInterface $source_plugin_manager,
    TextExtractorPluginManagerInterface $text_extractor_plugin_manager,
    TextSplitterPluginManagerInterface $text_splitter_plugin_manager,
    VectorStorePluginManagerInterface $vector_store_plugin_manager
  ) {
    $this->config = $config_factory->get('ocha_ai_chat.settings');
    $this->logger = $logger_factory->get('ocha_ai_chat');
    $this->state = $state;
    $this->completionPluginManager = $completion_plugin_manager;
    $this->embeddingPluginManager = $embedding_plugin_manager;
    $this->sourcePluginManager = $source_plugin_manager;
    $this->textExtractorPluginManager = $text_extractor_plugin_manager;
    $this->textSplitterPluginManager = $text_splitter_plugin_manager;
    $this->vectorStorePluginManager = $vector_store_plugin_manager;
  }

  /**
   * Answer the question against the ReliefWeb documents from the river URL.
   *
   * 1. Retrieve the documents from the ReliefWeb API URL.
   * 2. Embed the documents if not already.
   * 3. Generate the embedding for the question
   * 4. Find the documents relevant to the question.
   * 5. Generate the prompt context.
   * 6. Answer the question.
   *
   * @param string $question
   *   Question.
   * @param string $url
   *   Document source URL.
   * @param int $limit
   *   Number of documents to retrieve.
   * @param bool $reset
   *   If TRUE, delete the index. This is mostly for development.
   * @param \Drupal\ocha_ai_chat\Plugin\CompletionPluginInterface $plugin
   *   Optional completion plugin override.
   *
   * @return string
   *   Answer.
   *
   * @todo return the statistics?
   */
  public function answer(string $question, string $url, int $limit = 10, bool $reset = FALSE, ?CompletionPluginInterface $plugin = NULL): string {
    // Stats to record the time of each operation.
    // @todo either store the stats elsewhere (log etc.) or remove.
    $stats = [];

    // Retrieve the source documents matching the document source URL.
    $time = microtime(TRUE);
    ['index' => $index, 'documents' => $documents] = $this->getSourceDocuments($url, $limit);
    $stats['Get source documents'] = microtime(TRUE) - $time;

    // If there are no documents to query, then no need to ask the AI.
    if (empty($documents)) {
      return 'Sorry, no documents were found containing the answer to your question.';
    }

    // @todo remove, this is mostly for development and/or only remove the
    // documents from the index.
    if (!empty($reset)) {
      $time = microtime(TRUE);
      $this->getVectorStorePlugin()->deleteIndex($index);
      $stats['Delete the index'] = microtime(TRUE) - $time;
    }

    // Maybe that should be done outside of the answer pipeline.
    $time = microtime(TRUE);
    if (!$this->embedDocuments($index, $documents)) {
      return 'Sorry, there was an error trying to retrieve the documents to the answer to your question.';
    }
    $stats['Embed documents'] = microtime(TRUE) - $time;

    $ids = array_keys($documents);

    // Generate the embedding for the question.
    $time = microtime(TRUE);
    $embedding = $this->getEmbeddingPlugin()->generateEmbedding($question);
    $stats['Get question embedding'] = microtime(TRUE) - $time;

    // Find document passages relevant to the question.
    $time = microtime(TRUE);
    $passages = $this->getVectorStorePlugin()->getRelevantPassages($index, $ids, $question, $embedding);
    if (empty($passages)) {
      return 'Sorry, no documents were found containing the answer to your question.';
    }
    $stats['Get relevant passages'] = microtime(TRUE) - $time;

    // Get the completion plugin.
    $plugin = $plugin ?? $this->getCompletionPlugin();

    // Generate the context to answer the question based on the relevant
    // passages.
    $context = $plugin->generateContext($question, $passages);

    // @todo parse the answer and enrich it with the sources.
    $time = microtime(TRUE);
    $answer = $plugin->answer($question, $context);
    $stats['Get answer'] = microtime(TRUE) - $time;

    // @todo better logging.
    $this->logger->info(print_r($stats, TRUE));

    // The answer is empty for example if there was an error during the request.
    if (empty($answer)) {
      return 'Sorry, I was unable to answer to your question.';
    }

    return $answer;
  }

  /**
   * Get a list of source documents for the given document source URL.
   *
   * @param string $url
   *   Document source URL.
   * @param int $limit
   *   Number of documents to retrieve.
   *
   * @return array
   *   Associative array with the index corresponding to the type of
   *   documents and the list of source documents for the source URL.
   */
  protected function getSourceDocuments(string $url, int $limit): array {
    $plugin = $this->getSourcePlugin();

    $documents = $plugin->getDocuments($url, $limit);

    // @todo allow multiple indices.
    $resource = key($documents);
    $documents = $documents[$resource] ?? [];

    return [
      'index' => $plugin->getPluginId() . '_' . $resource,
      'documents' => $documents,
    ];
  }

  /**
   * Embed the documents retrieved from a ReliefWeb river URL.
   *
   * 1. Call RW API client to retrieve API data from RW river URL.
   * 2. Extract the text from the body + attachments.
   * 3. Split the text into passages.
   * 4. Generate embeddings for the text passages.
   * 5. Store the data in the vector database.
   *
   * @param string $index
   *   Index name.
   * @param array|null $documents
   *   Optional documents to embed. If not defined it uses the give river URL.
   *
   * @return bool
   *   TRUE if the embedding succeeded.
   *
   * @todo we may want to use a queue and do that asynchronously at some point.
   */
  public function embedDocuments(string $index, array $documents): bool {
    if (empty($documents)) {
      $this->logger->notice(strtr('No documents found for the ReliefWeb river URL: @url', [
        '@url' => $url,
      ]));
      return FALSE;
    }

    // Skip documents which were already processed.
    $existing = $this->getVectorStorePlugin()->getDocuments($index, array_keys($documents));
    $documents = array_diff_key($documents, $existing);

    // Process the documents, extracting embeddings and preparing for indexing.
    $documents = $this->processDocuments($documents);

    // Retrieve the dimensions of the embedding vectors.
    $dimensions = $this->getEmbeddingPlugin()->getDimensions();

    // Index the documents.
    return $this->getVectorStorePlugin()->indexDocuments($index, $documents, $dimensions);
  }

  /**
   * Process (download, extract text, split) documents before embedding.
   *
   * @param array $documents
   *   List of dcuments (associative arrays) with the type of document, property
   *   to get the document text chunks and metadata.
   *
   * @return array
   *   List of passages (chunk of text) with their embeddings and source.
   */
  protected function processDocuments(array $documents): array {
    foreach ($documents as $id => $document) {
      $contents = $document['contents'];

      foreach ($contents as $key => $content) {
        switch ($content['type']) {
          case 'markdown':
            $contents[$key]['pages'] = $this->processMarkdown($content['content']);
            break;

          case 'file':
            $contents[$key]['pages'] = $this->processFile($content['url'], $content['mimetype']);
            break;
        }
      }

      $documents[$id]['contents'] = $contents;
    }

    return $documents;
  }

  /**
   * Process a markdown text to make it easier to split.
   *
   * @param string $text
   *   Markdown text.
   *
   * @return array
   *   List of pages with their page number and list of passages. Each passage
   *   has a text and corresponding embedding.
   */
  protected function processMarkdown(string $text): array {
    $replacements = [
      // Headings.
      '/^#{1,6}\s*([^#]+)$/um' => "$1\n\n",
      // Headings or horizontal lines or code blocks.
      '/^[=*`-]{2,}$/um' => "\n",
    ];

    $text = trim(preg_replace(array_keys($replacements), $replacements, $text));

    return [$this->processPage($text)];
  }

  /**
   * Process a file, returning a list of its pages with extracted passages.
   *
   * @param string $uri
   *   File URI.
   * @param string $mimetype
   *   MIME type of the file.
   *
   * @return array
   *   List of pages with their page number and list of passages. Each passage
   *   has a text and corresponding embedding.
   */
  protected function processFile(string $uri, string $mimetype): array {
    if (!$this->isSupportedFileType($mimetype)) {
      return [];
    }

    // Record time to execute the different steps.
    // @todo either store the stats elsewhere (log etc.) or remove.
    $stats = [];

    // Create a temporary file to download to.
    $file = tmpfile();
    if ($file === FALSE) {
      $this->logger->error('Unable to create temporary file');
      return [];
    }

    // Download the file.
    $time = microtime(TRUE);
    if (stream_copy_to_stream(fopen($uri, 'r'), $file) === FALSE) {
      $this->logger->error("Unable to download the file $uri.");
      return [];
    }
    $stats['Download'] = microtime(TRUE) - $time;

    // Extract the content of each page.
    $time = microtime(TRUE);
    $path = realpath(stream_get_meta_data($file)['uri']);
    $page_texts = $this->getTextExtractorPlugin($mimetype)->getPageTexts($path);
    $stats['Extraction'] = microtime(TRUE) - $time;

    // Process each page.
    $time = microtime(TRUE);
    $pages = [];
    foreach ($page_texts as $page_number => $page_text) {
      $pages[] = $this->processPage($page_text, $page_number + 1);
    }
    $stats['Processing'] = microtime(TRUE) - $time;

    // @todo better logging.
    $this->logger->info(print_r($stats, TRUE));

    fclose($file);

    return $pages;
  }

  /**
   * Process the text of a document page, extracting passages.
   *
   * @param string $content
   *   Page content.
   * @param int $page
   *   Page number (0 if irrelevant).
   *
   * @return array
   *   Associative array with the page number and passages. Each passage
   *   has a text and corresponding embedding.
   */
  protected function processPage(string $content, int $page = 0): array {
    $texts = $this->splitText($content);
    $embeddings = $this->getEmbeddingPlugin()->generateEmbeddings($texts);

    // @todo handle cases where the embedding fails. Maybe we should not store
    // the document in that case.
    $passages = [];
    foreach ($texts as $index => $text) {
      if (!empty($embeddings[$index])) {
        $passages[] = [
          'text' => $text,
          'embedding' => $embeddings[$index],
        ];
      }
    }

    return [
      'page' => $page,
      'passages' => $passages,
    ];
  }

  /**
   * Check if a file mimetype is supported.
   *
   * @param string $mimetype
   *   MIME type of the file.
   *
   * @return bool
   *   TRUE if the file type is supported.
   */
  protected function isSupportedFileType(string $mimetype): bool {
    $plugin_id = $this->getSetting([
      'plugins',
      'text_extractor',
      $mimetype,
      'plugin_id',
    ]);
    return isset($plugin_id);
  }

  /**
   * Get the completion plugin manager.
   *
   * @return \Drupal\ocha_ai_chat\Plugin\CompletionPluginManagerInterface
   *   Completion plugin manager.
   */
  public function getCompletionPluginManager(): CompletionPluginManagerInterface {
    return $this->completionPluginManager;
  }

  /**
   * Get the embedding plugin manager.
   *
   * @return \Drupal\ocha_ai_chat\Plugin\EmbeddingPluginManagerInterface
   *   Embedding plugin.
   */
  public function getEmbeddingPluginManager(): EmbeddingPluginManagerInterface {
    return $this->embeddingPluginManager;
  }

  /**
   * Get the source plugin manager.
   *
   * @return \Drupal\ocha_ai_chat\Plugin\SourcePluginManagerInterface
   *   Source plugin.
   */
  public function getSourcePluginManager(): SourcePluginManagerInterface {
    return $this->sourcePluginManager;
  }

  /**
   * Get the text extractor plugin manager.
   *
   * @return \Drupal\ocha_ai_chat\Plugin\TextExtractorPluginManagerInterface
   *   Text extractor plugin.
   */
  public function getTextExtractorPluginManager(): TextExtractorPluginManagerInterface {
    return $this->textExtractorPluginManager;
  }

  /**
   * Get the text splitter plugin manager.
   *
   * @return \Drupal\ocha_ai_chat\Plugin\TextSplitterPluginManagerInterface
   *   Text splitter plugin.
   */
  public function getTextSplitterPluginManager(): TextSplitterPluginManagerInterface {
    return $this->textSplitterPluginManager;
  }

  /**
   * Get the vector store plugin manager.
   *
   * @return \Drupal\ocha_ai_chat\Plugin\VectorStorePluginManagerInterface
   *   Vector store plugin manager.
   */
  protected function getVectorStorePluginManager(): VectorStorePluginManagerInterface {
    return $this->vectorStorePluginManager;
  }

  /**
   * Get the completion plugin.
   *
   * @return \Drupal\ocha_ai_chat\Plugin\CompletionPluginInterface
   *   Completion plugin.
   */
  protected function getCompletionPlugin(): CompletionPluginInterface {
    $plugin_id = $this->getSetting(['plugins', 'completion', 'plugin_id']);
    return $this->getCompletionPluginManager()->getPlugin($plugin_id);
  }

  /**
   * Get the embedding plugin.
   *
   * @return \Drupal\ocha_ai_chat\Plugin\EmbeddingPluginInterface
   *   Embedding plugin.
   */
  protected function getEmbeddingPlugin(): EmbeddingPluginInterface {
    $plugin_id = $this->getSetting(['plugins', 'embedding', 'plugin_id']);
    return $this->getEmbeddingPluginManager()->getPlugin($plugin_id);
  }

  /**
   * Get the source plugin.
   *
   * @return \Drupal\ocha_ai_chat\Plugin\SourcePluginInterface
   *   Source plugin.
   */
  protected function getSourcePlugin(): SourcePluginInterface {
    $plugin_id = $this->getSetting(['plugins', 'source', 'plugin_id']);
    return $this->getSourcePluginManager()->getPlugin($plugin_id);
  }

  /**
   * Get the text extractor plugin for the given file mimetype.
   *
   * @return \Drupal\ocha_ai_chat\Plugin\TextExtractorPluginInterface
   *   Text extractor plugin.
   */
  protected function getTextExtractorPlugin(string $mimetype): TextExtractorPluginInterface {
    $plugin_id = $this->getSetting([
      'plugins',
      'text_extractor',
      $mimetype,
      'plugin_id',
    ]);
    return $this->getTextExtractorPluginManager()->getPlugin($plugin_id);
  }

  /**
   * Get the text splitter plugin.
   *
   * @return \Drupal\ocha_ai_chat\Plugin\TextSplitterPluginInterface
   *   Text splitter plugin.
   */
  protected function getTextSplitterPlugin(): TextSplitterPluginInterface {
    $plugin_id = $this->getSetting(['plugins', 'text_splitter', 'plugin_id']);
    return $this->getTextSplitterPluginManager()->getPlugin($plugin_id);
  }

  /**
   * Get the vector store plugin.
   *
   * @return \Drupal\ocha_ai_chat\Plugin\VectorStorePluginInterface
   *   Vector store plugin.
   */
  protected function getVectorStorePlugin(): VectorStorePluginInterface {
    $plugin_id = $this->getSetting(['plugins', 'vector_store', 'plugin_id']);
    return $this->getVectorStorePluginManager()->getPlugin($plugin_id);
  }

  /**
   * Split a text into passages.
   *
   * @param string $text
   *   Text to split.
   *
   * @return array
   *   List of text passages.
   */
  protected function splitText(string $text): array {
    return $this->getTextSplitterPlugin()->splitText($text);
  }

  /**
   * Get the default settings for the OCHA AI Chat.
   *
   * @return array
   *   The OCHA AI Chat settings.
   */
  public function getSettings(): array {
    if (!isset($this->settings)) {
      $config_defaults = $this->config->get('defaults') ?? [];

      $state_defaults = $this->state->get('ocha_ai_chat.default_settings', []);

      $this->settings = array_replace_recursive($config_defaults, $state_defaults);
    }
    return $this->settings;
  }

  /**
   * Get a setting for the OCHA AI Chat.
   *
   * @param array $keys
   *   Setting keys.
   * @param mixed $default
   *   Default.
   * @param bool $throw_if_null
   *   If TRUE and both the setting and default are NULL then an exception
   *   is thrown. Use this for example for mandatory settings.
   *
   * @return mixed
   *   The setting value for the keys or the provided default.
   *
   * @throws \Exception
   *   Throws an exception if no setting could be found (= NULL).
   */
  protected function getSetting(array $keys, mixed $default = NULL, bool $throw_if_null = TRUE): mixed {
    $settings = $this->getSettings();
    $setting = NestedArray::getValue($settings, $keys) ?? $default;
    if (is_null($setting) && $throw_if_null) {
      throw new \Exception(strtr('Missing setting @keys', [
        '@keys' => implode('.', $keys),
      ]));
    }
    return $setting;
  }

}
