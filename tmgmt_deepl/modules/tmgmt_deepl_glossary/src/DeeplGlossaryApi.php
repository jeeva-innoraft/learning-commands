<?php

namespace Drupal\tmgmt_deepl_glossary;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplGlossary;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;

/**
 * A service for managing DeepL glossary API calls.
 */
class DeeplGlossaryApi implements DeeplGlossaryApiInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The guzzle HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * The translator.
   *
   * @var \Drupal\tmgmt\TranslatorInterface
   */
  protected TranslatorInterface $translator;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * Constructs a new DeeplGlossaryApi.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The guzzle HTTP client.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The guzzle HTTP client.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ClientInterface $http_client, MessengerInterface $messenger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->httpClient = $http_client;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public function setTranslator(TranslatorInterface $translator): void {
    $this->translator = $translator;
  }

  /**
   * {@inheritdoc}
   */
  public function createGlossary(string $name, string $source_lang, string $target_lang, string $entries, string $entries_format = 'tsv'): array {
    // Get url to glossary endpoint.
    /** @var \Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslator $deepl_translator */
    $deepl_translator = $this->translator->getPlugin();
    $url = $deepl_translator->getGlossaryUrl();

    // Build query params.
    $query_params = [];
    $query_params['name'] = trim($name);
    $query_params['source_lang'] = $source_lang;
    $query_params['target_lang'] = $target_lang;
    $query_params['entries'] = $entries;
    $query_params['entries_format'] = $entries_format;

    // Add header.
    $headers = [];
    $headers['Content-Type'] = 'application/x-www-form-urlencoded';
    $response = $this->doRequest($url, 'POST', $query_params, $headers);

    return $response['content'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function doRequest(string $url, string $method = 'GET', array $query_params = [], array $headers = []): ?array {
    // Default authorization header.
    $headers['Authorization'] = 'DeepL-Auth-Key ' . $this->translator->getSetting('auth_key');

    // Build query string.
    $query_string = http_build_query($query_params);

    // Build request object.
    $request = new Request($method, $url, $headers, $query_string);

    // Send the request with the query.
    try {
      $response = $this->httpClient->send($request);
      // Get response body.
      $response_content = $response->getBody()->getContents();

      // Check if content is of type json.
      if ($this->isJsonString($response_content)) {
        return [
          'content' => json_decode($response_content, TRUE),
        ];
      }
      else {
        return [
          'content' => $response_content,
        ];
      }
    }
    catch (RequestException $e) {
      $this->processRequestError($e);
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getGlossaries(): array {
    // Get url to glossary endpoint.
    /** @var \Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslator $deepl_translator */
    $deepl_translator = $this->translator->getPlugin();
    $url = $deepl_translator->getGlossaryUrl();

    // Get all glossaries.
    $response = $this->doRequest($url);
    if (isset($response['content']) && is_array($response['content']['glossaries'])) {
      return $response['content']['glossaries'];
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function deleteGlossary(string $glossary_id): void {
    // Get url to glossary endpoint.
    /** @var \Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslator $deepl_translator */
    $deepl_translator = $this->translator->getPlugin();
    $url = $deepl_translator->getGlossaryUrl();
    // Add glossary_id to $url.
    $url .= '/' . $glossary_id;

    // Perform delete request.
    $this->doRequest($url, 'DELETE');
  }

  /**
   * {@inheritDoc}
   */
  public function buildGlossariesSyncBatch(): void {
    $deepl_translators = DeeplGlossary::getAllowedTranslators();
    if (count($deepl_translators) > 0) {
      // Build batch operations.
      $operations = [];
      // Get all available glossaries (by translator).
      foreach (array_keys($deepl_translators) as $deepl_translator) {
        /** @var \Drupal\tmgmt\TranslatorInterface $translator */
        $translator = $this->entityTypeManager->getStorage('tmgmt_translator')->load($deepl_translator);
        // Set active translator.
        $this->setTranslator($translator);
        // Get all glossaries.
        $glossaries = $this->getGlossaries();

        /** @var array $glossary */
        foreach ($glossaries as $glossary) {
          $glossary_entries = $this->getGlossaryEntries($glossary['glossary_id']);
          if ($glossary['ready'] && $glossary['entry_count'] > 0) {
            $operations[] = [
              '\Drupal\tmgmt_deepl_glossary\DeeplGlossaryApiBatch::syncGlossary',
              [
                $translator,
                $glossary,
                $glossary_entries,
              ],
            ];
          }
        }

        // Cleanup up obsolete deepl_glossary entities.
        $operations[] = [
          '\Drupal\tmgmt_deepl_glossary\DeeplGlossaryApiBatch::cleanUpDeeplGlossaryEntities',
          [
            $glossaries,
            $translator->id(),
          ],
        ];
      }

      // Define batch job.
      $batch = [
        'title' => $this->t('Syncing glossaries'),
        'operations' => $operations,
        'finished' => '\Drupal\tmgmt_deepl_glossary\DeeplGlossaryApiBatch::syncGlossariesFinishedCallback',
      ];

      batch_set($batch);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getGlossaryEntries(string $glossary_id): array {
    // Get url to glossary endpoint.
    /** @var \Drupal\tmgmt_deepl\Plugin\tmgmt\Translator\DeeplTranslator $deepl_translator */
    $deepl_translator = $this->translator->getPlugin();
    $url = $deepl_translator->getGlossaryUrl();
    // Add glossary_id to $url.
    $url .= '/' . $glossary_id . '/entries';

    // Perform request.
    $headers = [];
    $headers['Accept'] = 'text/tab-separated-values';
    /** @var array $entries */
    $entries = $this->doRequest($url, 'GET', [], $headers);

    // Check for entries.
    if (isset($entries['content']) && strlen($entries['content']) > 0) {
      // Build array of glossary entries.
      $lines = explode(PHP_EOL, $entries['content']);
      $glossary_entries = [];
      foreach ($lines as $line) {
        $glossary_entries[] = explode("\t", $line);
      }
      return $glossary_entries;
    }

    return [];
  }

  /**
   * Validate string with json content.
   *
   * @param string $string
   *   The string containing json or normal text.
   *
   * @return bool
   *   Wheter string is of type json.
   */
  protected function isJsonString(string $string): bool {
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
  }

  /**
   * Process possible request errors.
   *
   * @param \GuzzleHttp\Exception\RequestException $e
   *   The request exception.
   */
  protected function processRequestError(RequestException $e): void {
    $message = '';
    if ($e->hasResponse()) {
      $response = $e->getResponse();
      if ($response instanceof ResponseInterface) {
        // Get response body.
        $response_content = $response->getBody()->getContents();

        if ($this->isJsonString($response_content)) {
          /** @var object $response_content */
          $response_content = json_decode($response_content);
          $message = $response_content->message ?? '';
          $detail = $response_content->detail ?? '';
          $message = $this->t('DeepL API service returned an error: @message @detail', [
            '@message' => $message,
            '@detail' => $detail,
          ]);
        }
        else {
          $message = $this->t('DeepL API service returned following error: @error', ['@error' => $response->getReasonPhrase()]);
        }
      }
    }
    else {
      $response = $e->getHandlerContext();
      $error = $response['error'] ?? 'Unknown error';
      $message = $this->t('DeepL API service returned following error: @error', ['@error' => $error]);
    }
    $this->messenger->addError($message);
  }

}
