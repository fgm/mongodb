<?php

/**
 * @file
 * Default theme implementation to configure mongodb_block_uis.
 *
 * Available variables:
 * - $mongodb_block_ui_regions: An array of regions. Keyed by name with the
 *   title as value.
 * - $mongodb_block_ui_listing: An array of mongodb_block_uis keyed by region
 *   and then delta.
 * - $form_submit: Form submit button.
 *
 * Each $mongodb_block_ui_listing[$region] contains an array of
 * mongodb_block_uis for that region.
 *
 * Each $data in $mongodb_block_ui_listing[$region] contains:
 * - $data->region_title: Region title for the listed mongodb_block_ui.
 * - $data->mongodb_block_ui_title: Block title.
 * - $data->region_select: Drop-down menu for assigning a region.
 * - $data->weight_select: Drop-down menu for setting weights.
 * - $data->configure_link: Block configuration link.
 * - $data->delete_link: For deleting user added mongodb_block_uis.
 *
 * @see template_preprocess_mongodb_block_ui_admin_display_form()
 * @see theme_mongodb_block_ui_admin_display()
 */
?>
<?php
  // Add table javascript.
  drupal_add_js('misc/tableheader.js');
  drupal_add_js(drupal_get_path('module', 'mongodb_block_ui') . '/mongodb_block_ui.js');
  foreach ($block_regions as $region => $title):
    drupal_add_tabledrag('blocks', 'match', 'sibling', 'block-region-select', 'block-region-' . $region, NULL, FALSE);
    drupal_add_tabledrag('blocks', 'order', 'sibling', 'block-weight', 'block-weight-' . $region);
  endforeach;
?>
<table id="blocks" class="sticky-enabled">
  <thead>
    <tr>
      <th><?php print t('Block'); ?></th>
      <th><?php print t('Region'); ?></th>
      <th><?php print t('Weight'); ?></th>
      <th colspan="2"><?php print t('Operations'); ?></th>
    </tr>
  </thead>
  <tbody>
    <?php $row = 0; ?>
    <?php foreach ($block_regions as $region => $title): ?>
      <tr class="region-title region-title-<?php print $region?>">
        <td colspan="5"><?php print $title; ?></td>
      </tr>
      <tr class="region-message region-<?php print $region?>-message <?php print empty($block_listing[$region]) ? 'region-empty' : 'region-populated'; ?>">
        <td colspan="5"><em><?php print t('No blocks in this region'); ?></em></td>
      </tr>
      <?php foreach ($block_listing[$region] as $delta => $data): ?>
      <tr class="draggable <?php print $row % 2 == 0 ? 'odd' : 'even'; ?><?php print $data->row_class ? ' ' . $data->row_class : ''; ?>">
        <td class="block"><?php print $data->block_title; ?></td>
        <td><?php print $data->region_select; ?></td>
        <td><?php print $data->weight_select; ?></td>
        <td><?php print $data->configure_link; ?></td>
        <td><?php print $data->delete_link; ?></td>
      </tr>
      <?php $row++; ?>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </tbody>
</table>

<?php print $form_submit; ?>
