<?php

namespace Drupal\tmgmt_deepl_glossary\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt_deepl_glossary\DeeplGlossaryInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the DeepL glossary entity.
 *
 * @ContentEntityType(
 *   id = "deepl_glossary",
 *   label = @Translation("DeepL glossary"),
 *   label_singular = @Translation("DeepL glossary"),
 *   label_plural = @Translation("DeepL glossaries"),
 *   handlers = {
 *     "access" = "Drupal\tmgmt_deepl_glossary\AccessControlHandler",
 *     "list_builder" = "Drupal\tmgmt_deepl_glossary\Controller\DeeplGlossaryListBuilder",
 *     "views_data" = "Drupal\tmgmt_deepl_glossary\Entity\ViewsData\DeeplGlossaryViewsData",
 *     "form" = {
 *       "default" = "Drupal\tmgmt_deepl_glossary\Form\DeeplGlossaryForm",
 *       "add" = "Drupal\tmgmt_deepl_glossary\Form\DeeplGlossaryForm",
 *       "edit" = "Drupal\tmgmt_deepl_glossary\Form\DeeplGlossaryForm",
 *       "delete" = "Drupal\tmgmt_deepl_glossary\Form\DeeplGlossaryDeleteForm",
 *     },
 *     "route_provider" = {
 *        "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "tmgmt_deepl_glossary",
 *   translatable = FALSE,
 *   admin_permission = "administer deepl_glossary entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "ready" = "ready",
 *     "uid" = "uid",
 *   },
 *   links = {
 *     "add-form" = "/admin/tmgmt/deepl_glossaries/add",
 *     "edit-form" = "/admin/tmgmt/deepl_glossaries/manage/{deepl_glossary}/edit",
 *     "delete-form" = "/admin/tmgmt/deepl_glossaries/manage/{deepl_glossary}/delete",
 *     "collection" = "/admin/tmgmt/deepl_glossaries",
 *   }
 * )
 */
class DeeplGlossary extends ContentEntityBase implements DeeplGlossaryInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Name associated with the glossary.
    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the glossary.'))
      ->setRequired(TRUE)
      ->addConstraint('UniqueField')
      ->setDisplayOptions('form', [
        'type' => 'string',
      ])
      ->setDisplayConfigurable('form', TRUE);

    // The language in which the target texts in the glossary are specified.
    $fields['source_lang'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Source language'))
      ->setDescription(t('The language in which the source texts in the glossary are specified.'))
      ->setSetting('allowed_values_function', static::class . '::getAllowedLanguages')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'options_select',
      ])
      ->setDisplayConfigurable('form', TRUE);

    // The language in which the source texts in the glossary are specified.
    $fields['target_lang'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Target language'))
      ->setDescription(t('The language in which the target texts in the glossary are specified.'))
      ->setSetting('allowed_values_function', static::class . '::getAllowedLanguages')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'options_select',
      ])
      ->setDisplayConfigurable('form', TRUE);

    // The user id of the current user.
    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setDescription(t('The author of the glossary entry.'))
      ->setSetting('target_type', 'user')
      ->setReadOnly(TRUE);

    // The machine name of the translator.
    $fields['tmgmt_translator'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Translator'))
      ->setDescription(t('The tmgmt translator.'))
      ->setSetting('allowed_values_function', static::class . '::getAllowedTranslators')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'options_select',
      ])
      ->setDisplayConfigurable('form', TRUE);

    // A unique ID assigned to the glossary (values is retrieved by DeepL API)
    $fields['glossary_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Glossary Id'))
      ->setDescription(t('The glossary id.'));

    // A boolean that indicates if the newly created glossary can already be
    // used in translate requests.
    $fields['ready'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Ready state'))
      ->setDescription(t('A boolean that indicates if the newly created glossary can already be used in translate requests.'))
      ->setDefaultValue(FALSE);

    // The time that the entity was created.
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    // The number of entries in the glossary.
    $fields['entry_count'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Entry count'))
      ->setDescription(t('The number of entries in the glossary.'))
      ->setReadOnly(TRUE);

    // The format in which the glossary entries are provided.
    $fields['entries_format'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Entries format'))
      ->setDescription(t('The format in which the glossary entries are provided.'))
      ->setSetting('allowed_values', [['tsv' => 'text/tab-separated-values']])
      ->setReadOnly(TRUE)
      ->setRequired(TRUE)
      ->setDefaultValue('tsv');

    // The entries of the glossary.
    $fields['entries'] = BaseFieldDefinition::create('deepl_glossary_item')
      ->setLabel(t('Entries'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDescription(t('The entries of the glossary.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'deepl_glossary_item',
      ]);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getGlossaryId(): ?string {
    /** @var string $glossary_id */
    $glossary_id = $this->get('glossary_id')->value ?? NULL;
    return $glossary_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceLanguage(): string {
    /** @var string $source_lang */
    $source_lang = $this->get('source_lang')->value;
    return $source_lang;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetLanguage(): string {
    /** @var string $target_lang */
    $target_lang = $this->get('target_lang')->value;
    return $target_lang;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntryCount(): ?int {
    /** @var int $entry_count */
    $entry_count = is_int($this->get('entry_count')->value) ? $this->get('entry_count')->value : NULL;
    return $entry_count;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntriesString(): string {
    $entries = $this->get('entries');

    $entries_string = '';
    // We need internal count to set line break characters.
    $cnt = 1;
    foreach ($entries as $entry) {
      if (isset($entry->subject) && isset($entry->definition)) {
        $entries_string .= trim($entry->subject) . "\t" . trim($entry->definition);
        // Add linebreak.
        if ($cnt < count($entries)) {
          $entries_string .= "\r\n";
        }
        $cnt++;
      }
    }

    return $entries_string;
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslator(): ?TranslatorInterface {
    $tmgmt_translator_storage = $this->entityTypeManager()->getStorage('tmgmt_translator');
    $translator = $this->get('tmgmt_translator')->value;
    /** @var \Drupal\tmgmt\TranslatorInterface $translator */
    $translator = $tmgmt_translator_storage->load($translator);
    return $translator;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);

    // Set uid to current user.
    $this->set('uid', self::getDefaultEntityOwner());
  }

  /**
   * {@inheritdoc}
   */
  public static function getMatchingGlossaries(string $translator, string $source_lang, string $target_lang): array {
    $deepl_glossary_storage = \Drupal::entityTypeManager()->getStorage('deepl_glossary');
    // Fix language mappings for complex language codes.
    $source_lang = self::fixLanguageMappings($source_lang);
    $target_lang = self::fixLanguageMappings($target_lang);

    return $deepl_glossary_storage->loadByProperties([
      'tmgmt_translator' => $translator,
      'source_lang' => $source_lang,
      'target_lang' => $target_lang,
    ]);
  }

  /**
   * {@inheritDoc}
   */
  public static function getAllowedLanguages(): array {
    $allowed_languages = [
      'DE' => t('German'),
      'EN' => t('English'),
      'ES' => t('Spanish'),
      'FR' => t('French'),
      'IT' => t('Italian'),
      'JA' => t('Japanese'),
      'NL' => t('Dutch'),
      'PL' => t('Polish'),
      'PT' => t('Portuguese'),
      'RU' => t('Russian'),
      'ZH' => t('Chinese'),
    ];

    // Allow alteration of allowed languages.
    \Drupal::moduleHandler()->alter('tmgmt_deepl_glossary_allowed_languages', $allowed_languages);

    return $allowed_languages;
  }

  /**
   * {@inheritDoc}
   */
  public static function getAllowedTranslators(): array {
    $tmgmt_translator_storage = \Drupal::entityTypeManager()->getStorage('tmgmt_translator');
    $deepl_translators = $tmgmt_translator_storage->loadByProperties([
      'plugin' => [
        'deepl_pro',
        'deepl_free',
      ],
    ]);

    $options = [];
    foreach ($deepl_translators as $key => $deepl_translator) {
      $options[$key] = $deepl_translator->label();
    }
    return $options;
  }

  /**
   * {@inheritDoc}
   */
  public static function getValidSourceTargetLanguageCombinations(): array {
    $languages = array_keys(self::getAllowedLanguages());
    $combinations = [];
    foreach ($languages as $lang1) {
      foreach ($languages as $lang2) {
        // Avoid duplicate pairs.
        if ($lang1 !== $lang2) {
          $combinations[] = [$lang1 => $lang2];
        }
      }
    }

    return $combinations;
  }

  /**
   * {@inheritdoc}
   */
  public static function fixLanguageMappings(string $langcode): string {
    // DeepL glossary can only handle 2 character language codes.
    $langcode = substr($langcode, 0, 2);
    return strtoupper($langcode);
  }

}
