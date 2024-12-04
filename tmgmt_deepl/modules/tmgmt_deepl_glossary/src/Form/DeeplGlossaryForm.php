<?php

namespace Drupal\tmgmt_deepl_glossary\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\tmgmt\Entity\Translator;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt_deepl_glossary\DeeplGlossaryApiInterface;
use Drupal\tmgmt_deepl_glossary\DeeplGlossaryInterface;
use Drupal\tmgmt_deepl_glossary\Entity\DeeplGlossary;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for deepl_glossary edit forms.
 *
 * @ingroup tmgmt_deepl_glossary
 */
class DeeplGlossaryForm extends ContentEntityForm {

  /**
   * The DeepL glossary API service.
   *
   * @var \Drupal\tmgmt_deepl_glossary\DeeplGlossaryApiInterface
   */
  protected DeeplGlossaryApiInterface $glossaryApi;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

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
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, DeeplGlossaryApiInterface $glossary_api, AccountInterface $account) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);

    $this->glossaryApi = $glossary_api;
    $this->account = $account;
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
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    /** @var \Drupal\Core\Entity\EntityFormInterface $form_object */
    $form_object = $form_state->getFormObject();
    $deepl_glossary = $form_object->getEntity();

    // While updating existing entities, we disable field for translator.
    if (!$deepl_glossary->isNew()) {
      $form['tmgmt_translator']['widget']['#attributes'] = ['disabled' => 'disabled'];
      $form['tmgmt_translator']['widget']['#description'] = $this->t('The translator cannot be changed for existing glossaries.');

      // In case user has permission 'edit deepl_glossary glossary entries' and
      // not 'edit deepl_glossary entities', we disable the following fields:
      // Name, Source language, Target language.
      if ($this->account->hasPermission('edit deepl_glossary glossary entries') && !$this->account->hasPermission('edit deepl_glossary entities')) {
        $form['label']['widget'][0]['value']['#attributes'] = ['disabled' => TRUE];
        $form['source_lang']['widget']['#attributes'] = ['disabled' => TRUE];
        $form['target_lang']['widget']['#attributes'] = ['disabled' => TRUE];
      }
    }

    // Cancel link.
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#attributes' => ['class' => ['button']],
      '#url' => Url::fromRoute('entity.deepl_glossary.collection'),
      '#weight' => 8,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): ContentEntityInterface {
    parent::validateForm($form, $form_state);
    /** @var \Drupal\tmgmt_deepl_glossary\DeeplGlossaryInterface $entity */
    $entity = $this->buildEntity($form, $form_state);

    // Validate matching source, target language.
    $this->validateSourceTargetLanguage($form, $form_state);

    // Validate unique entries.
    $this->validateUniqueEntries($form, $form_state);

    // Validate unique glossary for source/ target language combination.
    $this->validateUniqueGlossary($form, $form_state, $entity);

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $status = $this->entity->save();
    $label = $this->entity->label();
    /** @var \Drupal\tmgmt_deepl_glossary\DeeplGlossaryInterface $glossary */
    $glossary = $this->entity;

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label DeepL glossary.', ['%label' => $label]));
        $this->saveDeeplGlossary($glossary, $status);
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label DeepL glossary.', ['%label' => $label]));
        $this->saveDeeplGlossary($glossary, $status);
    }

    $form_state->setRedirect('entity.deepl_glossary.collection');
    return $status;
  }

  /**
   * Save DeepL glossary to DeepL API.
   *
   * @param \Drupal\tmgmt_deepl_glossary\DeeplGlossaryInterface $glossary
   *   The DeepL glossary entity object.
   * @param int $status
   *   The save status (indicator for new or existing entities)
   */
  protected function saveDeeplGlossary(DeeplGlossaryInterface $glossary, int $status): void {
    $translator = $glossary->getTranslator();
    $glossary_api = $this->glossaryApi;
    if ($translator instanceof TranslatorInterface) {
      $glossary_api->setTranslator($translator);
    }

    // By updating and existing entry we need to delete DeepL glossary first.
    if ($status === SAVED_UPDATED && strval($glossary->getGlossaryId()) !== '') {
      $glossary_api->deleteGlossary(strval($glossary->getGlossaryId()));
    }

    // Create glossary with DeepL API.
    $result = $glossary_api->createGlossary(strval($glossary->label()), $glossary->getSourceLanguage(), $glossary->getTargetLanguage(), $glossary->getEntriesString());

    // Save DeepL internal glossary_id to entity.
    if (isset($result['glossary_id']) && (isset($result['ready']) && $result['ready'] === TRUE)) {
      $glossary->set('glossary_id', $result['glossary_id']);
      $glossary->set('ready', TRUE);
      $glossary->set('entry_count', $result['entry_count']);
      $glossary->save();
    }
  }

  /**
   * Validate valid source/ target language pair.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function validateSourceTargetLanguage(array &$form, FormStateInterface $form_state): void {
    $user_input = $form_state->getValues();
    $source_lang = $user_input['source_lang'][0]['value'] ?? '';
    $target_lang = $user_input['target_lang'][0]['value'] ?? '';

    // Define valid language pairs.
    $valid_language_pairs = DeeplGlossary::getValidSourceTargetLanguageCombinations();

    // Get valid match for source/ target language..
    $match = FALSE;
    foreach ($valid_language_pairs as $valid_language_pair) {
      if (isset($valid_language_pair[$source_lang]) && ($valid_language_pair[$source_lang] == $target_lang)) {
        $match = TRUE;
      }
    }

    // If we don't find a valid math, set error to fields.
    if (!$match) {
      $message = $this->t('Select a valid source/ target language.');
      $form_state->setErrorByName('source_lang', $message);
      $form_state->setErrorByName('target_lang', $message);
    }
  }

  /**
   * Validate unique entries.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function validateUniqueEntries(array &$form, FormStateInterface $form_state): void {
    $user_input = $form_state->getUserInput();
    $entries = $user_input['entries'] ?? '';
    $subjects = [];
    foreach ($entries as $entry) {
      if (isset($entry['subject']) && $entry['subject'] !== '') {
        $subjects[] = $entry['subject'];
      }
    }

    // Duplicate check.
    $unique_subjects = array_unique($subjects);
    $duplicates = array_diff_assoc($subjects, $unique_subjects);
    if (count($duplicates) > 0) {
      foreach (array_keys($duplicates) as $key) {
        $form_state->setErrorByName('entries][' . $key . '][subject', $this->t('Please check your glossary entries, the subjects should be unique.'));
      }
    }
  }

  /**
   * Validate unique glossary for source/ target language combination.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\tmgmt_deepl_glossary\DeeplGlossaryInterface $entity
   *   The DeeplGlossary entity object.
   */
  protected function validateUniqueGlossary(array &$form, FormStateInterface $form_state, DeeplGlossaryInterface $entity): void {
    $user_input = $form_state->getUserInput();
    $translator = $user_input['tmgmt_translator'] ?? '';
    $source_lang = $user_input['source_lang'] ?? '';
    $target_lang = $user_input['target_lang'] ?? '';
    $translator = Translator::load($translator);

    // Check tmgmt_deepl_glossary settings.
    /** @var array $tmgmt_deepl_glossary_settings */
    $tmgmt_deepl_glossary_settings = ($translator instanceof TranslatorInterface) ? $translator->getSetting('tmgmt_deepl_glossary') : [];
    if (isset($tmgmt_deepl_glossary_settings['allow_multiple']) && $tmgmt_deepl_glossary_settings['allow_multiple'] === 0) {
      assert($translator instanceof TranslatorInterface);
      $existing_glossaries = $this->entityTypeManager->getStorage('deepl_glossary')->loadByProperties([
        'tmgmt_translator' => $translator->id(),
        'source_lang' => $source_lang,
        'target_lang' => $target_lang,
      ]);

      // In case saving existing entity, we need to remove entity before
      // checking for duplicates.
      if (!$entity->isNew() && isset($existing_glossaries[$entity->id()])) {
        unset($existing_glossaries[$entity->id()]);
      }

      // Show error, if we find existing glossary for source/ target language.
      if (count($existing_glossaries) >= 1) {
        $message = $this->t('You cannot add more than one glossary for the selected source/ target language combination.');
        $form_state->setErrorByName('source_lang', $message);
        $form_state->setErrorByName('target_lang', $message);
      }
    }
  }

}
