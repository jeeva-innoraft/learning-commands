<?php

namespace Drupal\tmgmt_deepl_glossary\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\tmgmt_deepl_glossary\DeeplGlossaryApiInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for syncing deepl_glossary entries.
 *
 * @ingroup tmgmt_deepl_glossary
 */
class DeeplGlossarySyncForm extends ConfirmFormBase {

  /**
   * The DeepL glossary API service.
   *
   * @var \Drupal\tmgmt_deepl_glossary\DeeplGlossaryApiInterface
   */
  protected DeeplGlossaryApiInterface $glossaryApi;

  /**
   * Constructs a DeeplGlossarySyncForm object.
   *
   * @param \Drupal\tmgmt_deepl_glossary\DeeplGlossaryApiInterface $glossary_api
   *   The DeepL glossary API service.
   */
  public function __construct(DeeplGlossaryApiInterface $glossary_api) {
    $this->glossaryApi = $glossary_api;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    // @phpstan-ignore-next-line
    return new static(
      $container->get('tmgmt_deepl_glossary.api'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId(): string {
    return 'tmgmt_deepl_glossary_sync_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Sync DeepL glossaries');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    return $this->t('This action will sync all DeepL glossaries via the DeepL API.');
  }

  /**
   * {@inheritDoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Do you want to sync the latest DeepL glossaries via the DeepL API?');
  }

  /**
   * {@inheritDoc}
   */
  public function getCancelUrl(): Url {
    return new Url('entity.deepl_glossary.collection');
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Build sync batch.
    $this->glossaryApi->buildGlossariesSyncBatch();

    // Redirect to glossary overview.
    $form_state->setRedirect('entity.deepl_glossary.collection');
  }

}
