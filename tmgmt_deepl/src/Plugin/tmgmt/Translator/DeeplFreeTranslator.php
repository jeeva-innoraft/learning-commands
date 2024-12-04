<?php

namespace Drupal\tmgmt_deepl\Plugin\tmgmt\Translator;

use Drupal\tmgmt\Annotation\TranslatorPlugin;

/**
 * DeepL API Free translator plugin.
 *
 * @TranslatorPlugin(
 *   id = "deepl_free",
 *   label = @Translation("DeepL API Free"),
 *   description = @Translation("DeepL API Free Translator service."),
 *   ui = "Drupal\tmgmt_deepl\DeeplTranslatorUi",
 *   logo = "icons/deepl.svg",
 * )
 */
class DeeplFreeTranslator extends DeeplTranslator {

  /**
   * Translation service URL.
   *
   * @var string
   */
  protected string $translatorUrl = 'https://api-free.deepl.com/v2/translate';

  /**
   * Translation usage service URL.
   *
   * @var string
   */
  protected string $translatorUsageUrl = 'https://api-free.deepl.com/v2/usage';

  /**
   * Translation glossary service URL.
   *
   * @var string
   */
  protected string $translatorGlossaryUrl = 'https://api-free.deepl.com/v2/glossaries';

}
