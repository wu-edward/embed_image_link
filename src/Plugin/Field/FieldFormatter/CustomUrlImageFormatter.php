<?php

namespace Drupal\embed_image_link\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Plugin implementation of the 'embed_image_link_image' formatter.
 *
 * This provides field formatter for images that allows configuration to be set
 * to specify a custom URL to link to.
 *
 * @FieldFormatter(
 *   id = "embed_image_link_image",
 *   label = @Translation("Image with custom URL link option"),
 *   field_types = {
 *     "image"
 *   },
 *   quickedit = {
 *     "editor" = "image"
 *   }
 * )
 */
class CustomUrlImageFormatter extends ImageFormatter {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'image_link_custom_url' => '',
      'rel' => '',
      'target' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->setStringTranslation($container->get('string_translation'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    $element['image_link']['#options']['custom_url'] = $this->t('Specify a URL');

    // Add field to specify a URL to link to. This field effectively works the
    // same as the link field widget (external + internal URLs all allowed.)
    // @see \Drupal\link\Plugin\Field\FieldWidget\LinkWidget
    $element['image_link_custom_url'] = [
      '#title' => $this->t('Specify a URL to link to'),
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#attributes' => [
        'data-autocomplete-first-character-blacklist' => '/#?',
      ],
      '#element_validate' => [[static::class, 'validateUriElement']],
      '#process_default_value' => FALSE,
      '#description' => $this->t('Start typing the title of a piece of content to select it.<br />You can also enter an internal path such as %add-node or an external URL such as %url.<br /> Enter %front to link to the front page.', [
        '%front' => '<front>',
        '%add-node' => '/node/add',
        '%url' => 'http://example.com',
      ]),
      '#default_value' => $this->getSetting('image_link_custom_url') ? $this->getUriAsDisplayableString($this->getSetting('image_link_custom_url')) : '',
      '#maxlength' => 2048,
    ];

    // Add rel "nofollow" and target "_blank" option fields.
    // @see Drupal\link\Plugin\Field\FieldFormatter\LinkFormatter
    $element['rel'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add rel="nofollow" to links'),
      '#return_value' => 'nofollow',
      '#default_value' => $this->getSetting('rel'),
    ];
    $element['target'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open link in new window'),
      '#return_value' => '_blank',
      '#default_value' => $this->getSetting('target'),
    ];

    // Add a form element process callback to #states properties after the
    // element name attribute has been set.
    $element['#process'][] = [static::class, 'addStatesForCustomUrlFields'];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $settings = $this->getSettings();
    if ($settings['image_link'] === 'custom_url') {
      $summary[] = $this->t('Linked to @custom_url', [
        '@custom_url' => $this->getSetting('image_link_custom_url'),
      ]);
      if (!empty($settings['rel'])) {
        $summary[] = $this->t('Add rel="@rel"', ['@rel' => $settings['rel']]);
      }
      if (!empty($settings['target'])) {
        $summary[] = $this->t('Open link in new window');
      }
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    if ($this->getSetting('image_link') !== 'custom_url' ||
        !$this->getSetting('image_link_custom_url')) {
      return $elements;
    }

    $settings = $this->getSettings();
    $uri = $settings['image_link_custom_url'];
    $url = Url::fromUri($uri);
    $options = [];
    // Add optional 'rel' attribute to link options.
    if (!empty($settings['rel'])) {
      $options['attributes']['rel'] = $settings['rel'];
    }
    // Add optional 'target' attribute to link options.
    if (!empty($settings['target'])) {
      $options['attributes']['target'] = $settings['target'];
    }
    if ($options) {
      $url->setOptions($options);
    }
    foreach ($elements as $delta => $element) {
      $elements[$delta]['#url'] = $url;
    }

    return $elements;
  }

  /**
   * Gets the URI without the 'internal:' or 'entity:' scheme.
   *
   * The following two forms of URIs are transformed:
   * - 'entity:' URIs: to entity autocomplete ("label (entity id)") strings;
   * - 'internal:' URIs: the scheme is stripped.
   *
   * This method is the inverse of ::getUserEnteredStringAsUri().
   *
   * This was copied from \Drupal\link\Plugin\Field\FieldWidget\LinkWidget.
   *
   * @param string $uri
   *   The URI to get the displayable string for.
   *
   * @return string
   *   The URI without URI without the 'internal:' or 'entity:' scheme.
   *
   * @see static::getUserEnteredStringAsUri()
   */
  protected static function getUriAsDisplayableString($uri) {
    $scheme = parse_url($uri, PHP_URL_SCHEME);

    // By default, the displayable string is the URI.
    $displayable_string = $uri;

    // A different displayable string may be chosen in case of the 'internal:'
    // or 'entity:' built-in schemes.
    if ($scheme === 'internal') {
      $uri_reference = explode(':', $uri, 2)[1];

      // @todo '<front>' is valid input for BC reasons, may be removed by
      //   https://www.drupal.org/node/2421941
      $path = parse_url($uri, PHP_URL_PATH);
      if ($path === '/') {
        $uri_reference = '<front>' . substr($uri_reference, 1);
      }

      $displayable_string = $uri_reference;
    }
    elseif ($scheme === 'entity') {
      list($entity_type, $entity_id) = explode('/', substr($uri, 7), 2);
      // Show the 'entity:' URI as the entity autocomplete would.
      // @todo Support entity types other than 'node'. Will be fixed in
      //   https://www.drupal.org/node/2423093.
      if ($entity_type == 'node' && $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id)) {
        $displayable_string = EntityAutocomplete::getEntityLabels([$entity]);
      }
    }
    elseif ($scheme === 'route') {
      $displayable_string = ltrim($displayable_string, 'route:');
    }

    return $displayable_string;
  }

  /**
   * Gets the user-entered string as a URI.
   *
   * The following two forms of input are mapped to URIs:
   * - entity autocomplete ("label (entity id)") strings: to 'entity:' URIs;
   * - strings without a detectable scheme: to 'internal:' URIs.
   *
   * This method is the inverse of ::getUriAsDisplayableString().
   *
   * This was copied from \Drupal\link\Plugin\Field\FieldWidget\LinkWidget.
   *
   * @param string $string
   *   The user-entered string.
   *
   * @return string
   *   The URI, if a non-empty $uri was passed.
   *
   * @see static::getUriAsDisplayableString()
   */
  protected static function getUserEnteredStringAsUri($string) {
    // By default, assume the entered string is an URI.
    $uri = trim($string);

    // Detect entity autocomplete string, map to 'entity:' URI.
    $entity_id = EntityAutocomplete::extractEntityIdFromAutocompleteInput($string);
    if ($entity_id !== NULL) {
      // @todo Support entity types other than 'node'. Will be fixed in
      //   https://www.drupal.org/node/2423093.
      $uri = 'entity:node/' . $entity_id;
    }
    // Support linking to nothing.
    elseif (in_array($string, ['<nolink>', '<none>'], TRUE)) {
      $uri = 'route:' . $string;
    }
    // Detect a schemeless string, map to 'internal:' URI.
    elseif (!empty($string) && parse_url($string, PHP_URL_SCHEME) === NULL) {
      // @todo '<front>' is valid input for BC reasons, may be removed by
      //   https://www.drupal.org/node/2421941
      // - '<front>' -> '/'
      // - '<front>#foo' -> '/#foo'
      if (strpos($string, '<front>') === 0) {
        $string = '/' . substr($string, strlen('<front>'));
      }
      $uri = 'internal:' . $string;
    }

    return $uri;
  }

  /**
   * Form element validation handler for the 'uri' element.
   *
   * Disallows saving inaccessible or untrusted URLs.
   *
   * This was mostly a copy of \Drupal\link\Plugin\Field\FieldWidget\LinkWidget.
   */
  public static function validateUriElement($element, FormStateInterface $form_state, $form) {
    $uri = static::getUserEnteredStringAsUri($element['#value']);
    $form_state->setValueForElement($element, $uri);

    // Only validate the custom URL field if set to use it.
    $image_link_value_key = $element['#parents'];
    array_pop($image_link_value_key);
    array_push($image_link_value_key, 'image_link');
    $image_link_value = $form_state->getValue($image_link_value_key);
    if ($image_link_value !== 'custom_url') {
      return;
    }

    // If getUserEnteredStringAsUri() mapped the entered value to a 'internal:'
    // URI , ensure the raw value begins with '/', '?' or '#'.
    // @todo '<front>' is valid input for BC reasons, may be removed by
    //   https://www.drupal.org/node/2421941
    if (parse_url($uri, PHP_URL_SCHEME) === 'internal' &&
      !in_array($element['#value'][0], ['/', '?', '#'], TRUE) &&
      substr($element['#value'], 0, 7) !== '<front>') {
      $form_state->setError($element, t('Manually entered paths should start with one of the following characters: / ? #'));
      return;
    }

    // Add validation constraints similar to those for Link field item.
    try {
      // Try to resolve the given URI to a URL. It may fail if it's schemeless.
      // @see \Drupal\link\Plugin\Validation\Constraint\LinkTypeConstraintValidator
      $url = Url::fromUri($uri);
    }
    catch (\InvalidArgumentException $e) {
      $form_state->setError($element, t('The path @uri is invalid.', [
        '@uri' => $uri,
      ]));
      return;
    }

    $uri_is_invalid = FALSE;
    // Disallow external URLs using untrusted protocols.
    // @see \Drupal\link\Plugin\Validation\Constraint\LinkExternalProtocolsConstraint
    if ($url->isExternal() && !in_array(parse_url($url->getUri(), PHP_URL_SCHEME), UrlHelper::getAllowedProtocols())) {
      $uri_is_invalid = TRUE;
    }
    elseif ($url->isRouted()) {
      try {
        $url->toString(TRUE);
      }
        // The following exceptions are all possible during URL generation, and
        // should be considered as disallowed URLs.
        // @see \Drupal\link\Plugin\Validation\Constraint\LinkNotExistingInternalConstraintValidator
      catch (RouteNotFoundException|InvalidParameterException|MissingMandatoryParametersException $e) {
        $uri_is_invalid = TRUE;
      }
    }

    // Disallow URLs if the current user doesn't have the 'link to any page'
    // permission nor can access this URI.
    // @see \Drupal\link\Plugin\Validation\Constraint\LinkAccessConstraintValidator
    if (!$uri_is_invalid &&
      !(\Drupal::currentUser()->hasPermission('link to any page') || $url->access())) {
      $form_state->setError($element, t('The path @uri is inaccessible.', [
        '@uri' => $uri,
      ]));
      return;
    }

    if ($uri_is_invalid) {
      $form_state->setError($element, t('The path @uri is invalid.', [
        '@uri' => $uri,
      ]));
    }
  }

  /**
   * Form element process callback.
   *
   * Adds #states properties to hide/show and enable/ disable custom URL
   * settings fields based on whether a image_link is set to "custom_url".
   */
  public static function addStatesForCustomUrlFields($element, FormStateInterface $form_state, $form) {
    // Recreate the image_link name attribute from the array parents.
    // @see \Drupal\Core\Form\FormBuilder::handleInputElement()
    $parents = $element['#parents'];
    $image_link_name = array_shift($parents);
    if (count($element['#parents'])) {
      $image_link_name .= '[' . implode('][', $parents) . ']';
    }
    $image_link_name .= '[image_link]';

    // Control visibility and whether the custom URL fields are enabled based on
    // whether the image_link field is set to "custom_url".
    $custom_url_fields = [
      'image_link_custom_url',
      'rel',
      'target',
    ];
    foreach ($custom_url_fields as $custom_url_field) {
      $element[$custom_url_field]['#states'] = [
        'visible' => [
          ':input[name="' . $image_link_name . '"]' => [
            'value' => 'custom_url',
          ],
        ],
        'enabled' => [
          ':input[name="' . $image_link_name . '"]' => [
            'value' => 'custom_url',
          ],
        ],
      ];
    }

    return $element;
  }

}
