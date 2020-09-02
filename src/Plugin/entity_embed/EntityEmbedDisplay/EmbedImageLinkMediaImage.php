<?php

namespace Drupal\embed_image_link\Plugin\entity_embed\EntityEmbedDisplay;

use Drupal\lightning_media\Plugin\entity_embed\EntityEmbedDisplay\MediaImage;

/**
 * Renders a media item's image via the embed_image_link_image formatter.
 *
 * If the embedded media item has an image field as its source field, that image
 * is rendered through the ojp_media_image formatter. Otherwise, the media
 * item's thumbnail is used.
 *
 * @EntityEmbedDisplay(
 *   id = "embed_image_link_image",
 *   label = @Translation("Embed image link image"),
 *   entity_types = {"media"},
 *   field_type = "image",
 *   provider = "image"
 * )
 */
class EmbedImageLinkMediaImage extends MediaImage {

  /**
   * {@inheritdoc}
   */
  public function getFieldFormatterId() {
    return 'embed_image_link_image';
  }

}
