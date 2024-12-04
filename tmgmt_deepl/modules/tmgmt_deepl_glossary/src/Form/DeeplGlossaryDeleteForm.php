<?php

namespace Drupal\tmgmt_deepl_glossary\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt_deepl_glossary\DeeplGlossaryApiInterface;
use Drupal\tmgmt_deepl_glossary\DeeplGlossaryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting a deepl_glossary entity.
 *
 * @ingroup tmgmt_deepl_glossary
 */
class DeeplGlossaryDeleteForm extends ContentEntityConfirmFormBase {

  /**
   * The DeepL glossary API service.
   *
   * @var \Drupal\tmgmt_deepl_glossary\DeeplGlossaryApiInterface
   */
  protected DeeplGlossaryApiInterface $glossaryApi;

  /**
   * Constructs a DeeplGlossaryForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\tmgmt_deepl_glossary\DeeplGlossaryApiInterface $glossary_api
   *   The DeepL glossary API service.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, DeeplGlossaryApiInterface $glossary_api) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);

    $this->glossaryApi = $glossary_api;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    // @phpstan-ignore-next-line
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('tmgmt_deepl_glossary.api'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to delete the DeepL glossary "%title"?', ['%title' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   *
   * If the delete command is canceled, return to the liveblog_post page.
   */
  public function getCancelUrl(): Url {
    return $this->getEntity()->toUrl();
  }

  /**
   * {@inheritdoc}
   *
   * Delete the entity and log the event. logger() replaces the watchdog.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $entity = $this->getEntity();
    $entity->delete();

    // Delete glossary via DeepL API.
    /** @var \Drupal\tmgmt_deepl_glossary\DeeplGlossaryInterface $glossary */
    $glossary = $this->entity;

    $this->deleteDeeplGlossary($glossary);
  }

  /**
   * Delete glossary via DeepL API.
   *
   * @param \Drupal\tmgmt_deepl_glossary\DeeplGlossaryInterface $glossary
   *   The glossary entity to delete.
   */
  protected function deleteDeeplGlossary(DeeplGlossaryInterface $glossary): void {
    /** @var string $glossary_id */
    $glossary_id = $glossary->getGlossaryId();
    if (strlen($glossary_id) > 0) {
      $translator = $glossary->getTranslator();
      if ($translator instanceof TranslatorInterface) {
        $glossary_api = $this->glossaryApi;
        $glossary_api->setTranslator($translator);
        $glossary_api->deleteGlossary($glossary_id);
      }
    }
  }

}
