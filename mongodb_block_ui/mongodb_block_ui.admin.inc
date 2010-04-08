<?php
// $Id$

/**
 * @file
 * Admin page callbacks for the mongodb_block_ui module.
 */


/**
 * Menu callback for admin/structure/mongodb_block_ui/demo.
 */
function mongodb_block_ui_admin_demo($theme = NULL) {
  drupal_add_css(drupal_get_path('module', 'mongodb_block_ui') . '/mongodb_block_ui.css', array('preprocess' => FALSE));
  return '';
}

/**
 * Menu callback for admin/structure/mongodb_block_ui.
 *
 * @param $theme
 *   The theme to display the administration page for. If not provided, defaults
 *   to the currently used theme.
 */
function mongodb_block_ui_admin_display($theme = NULL) {
  global $theme_key;

  drupal_theme_initialize();

  if (!isset($theme)) {
    // If theme is not specifically set, rehash for the current theme.
    $theme = $theme_key;
  }

  // Fetch and sort mongodb_block_uis.
  $current_blocks = mongodb_block_rehash();
  $blocks = array();
  foreach ($current_blocks as $block) {
    $module = $block['module'];
    $delta = $block['delta'];
    $blocks[$module . '_' . $delta] = $block;
  }
  $compare_theme = &drupal_static('_mongodb_block_ui_compare:theme');
  $compare_theme = $theme;
  usort($blocks, '_mongodb_block_ui_compare');

  return drupal_get_form('mongodb_block_ui_admin_display_form', $blocks, $theme);
}

/**
 * Generate main mongodb_block_uis administration form.
 */
function mongodb_block_ui_admin_display_form($form, &$form_state, $blocks, $theme) {

  drupal_add_css(drupal_get_path('module', 'mongodb_block_ui') . '/mongodb_block_ui.css', array('preprocess' => FALSE));

  $blocks_regions = system_region_list($theme, REGIONS_VISIBLE) + array(BLOCK_REGION_NONE => '<' . t('none') . '>');

  // Weights range from -delta to +delta, so delta should be at least half
  // of the amount of mongodb_block_uis present. This makes sure all mongodb_block_uis in the same
  // region get an unique weight.
  $weight_delta = round(count($blocks) / 2);

  // Build the form tree.
  $form['edited_theme'] = array('#type' => 'value', '#value' => $theme);
  $form['#action'] = arg(4) ? url('admin/structure/mongodb_block/list/' . $theme) : url('admin/structure/mongodb_block');
  $form['#blocks'] = $blocks;
  $form['#tree'] = TRUE;

  foreach ($blocks as $key => $block) {
    $module = $block['module'];
    $delta = $block['delta'];
    $path_prefix = "admin/structure/mongodb_block/manage/$module/$delta";
    $form[$key]['module'] = array(
      '#type' => 'value',
      '#value' => $module,
    );
    $form[$key]['delta'] = array(
      '#type' => 'value',
      '#value' => $delta,
    );
    $form[$key]['info'] = array(
      '#markup' => check_plain($block['info']),
    );
    $form[$key]['theme'] = array(
      '#type' => 'hidden',
      '#value' => $theme,
    );
    $form[$key]['weight'] = array(
      '#type' => 'weight',
      '#default_value' => $block['weight'],
      '#delta' => $weight_delta,
    );
    $form[$key]['region'] = array(
      '#type' => 'select',
      '#default_value' => $block['region'],
      '#options' => $blocks_regions,
    );
    $form[$key]['configure'] = array(
      '#type' => 'link',
      '#title' => t('configure'),
      '#href' =>  "$path_prefix/configure",
    );
    if ($module == 'mongodb_block_ui') {
      $form[$key]['delete'] = array(
        '#type' => 'link',
        '#title' => t('delete'),
        '#href' => "$path_prefix/delete",
      );
    }
  }
  // Do not allow disabling the main system content mongodb_block_ui.
  unset($form['system_main']['region']['#options'][BLOCK_REGION_NONE]);

  $form['actions'] = array(
    '#tree' => FALSE,
    '#type' => 'container',
    '#attributes' => array('class' => array('form-actions')),
  );
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save blocks'),
  );

  return $form;
}

/**
 * Process main blocks administration form submissions.
 */
function mongodb_block_ui_admin_display_form_submit($form, &$form_state) {
  $collection = mongodb_collection('block');
  foreach ($form_state['values'] as $key => $block) {
    // Skip the buttons by checking for array and save only blocks that are not
    // disabled.
    if (is_array($block)) {
      if ($block['region'] != BLOCK_REGION_NONE) {
        $set = array(
          'weight' => (int) $block['weight'],
          'region' => $block['region'],
        );
        mongodb_block_save_block($collection, $set + $form['#blocks'][$key]);
      }
      elseif ($form['#blocks'][$key]['region'] != BLOCK_REGION_NONE) {
        $collection->remove(array('_id' => $form['#blocks'][$key]['_id']));
      }
    }
  }
  drupal_set_message(t('The block settings have been updated.'));
  cache_clear_all();
}

/**
 * Helper function for sorting mongodb_block_uis on admin/structure/mongodb_block_ui.
 *
 * Active mongodb_block_uis are sorted by region, then by weight.
 * Disabled mongodb_block_uis are sorted by name.
 */
function _mongodb_block_ui_compare($a, $b) {
  global $theme_key;

  // Theme should be set before calling this function, or the current theme
  // is being used.
  $theme = &drupal_static(__FUNCTION__ . ':theme');
  if (!isset($theme)) {
    $theme = $theme_key;
  }

  $regions = &drupal_static(__FUNCTION__ . ':regions');
  // We need the region list to correctly order by region.
  if (!isset($regions)) {
    $regions = array_flip(array_keys(system_region_list($theme)));
    $regions[BLOCK_REGION_NONE] = count($regions);
  }

  // Separate enabled from disabled.
  $status = $b['status'] - $a['status'];
  if ($status) {
    return $status;
  }
  // Sort by region (in the order defined by theme .info file).
  if ((!empty($a['region']) && !empty($b['region'])) && ($place = ($regions[$a['region']] - $regions[$b['region']]))) {
    return $place;
  }
  // Sort by weight.
  $weight = $a['weight'] - $b['weight'];
  if ($weight) {
    return $weight;
  }
  // Sort by title.
  return strcmp($a['info'], $b['info']);
}

/**
 * Menu callback; displays the mongodb_block_ui configuration form.
 */
function mongodb_block_ui_admin_configure($form, &$form_state, $module, $delta) {
  $block = mongodb_block_ui_load($module, $delta);
  $form['module'] = array(
    '#type' => 'value',
    '#value' => $block->module,
  );
  $form['delta'] = array(
    '#type' => 'value',
    '#value' => $block->delta,
  );

  // Get the block subject for the page title.
  $info = module_invoke($block->module, 'block_info');
  if (isset($info[$block->delta])) {
    drupal_set_title(t("'%name' block", array('%name' => $info[$block->delta]['info'])), PASS_THROUGH);
  }

  $form['settings']['title'] = array(
    '#type' => 'textfield',
    '#title' => t('Block title'),
    '#maxlength' => 64,
    '#description' => $block->module == 'mongodb_block_ui' ? t('The title of the block as shown to the user.') : t('Override the default title for the mongodb_block_ui. Use <em>!placeholder</em> to display no title, or leave blank to use the default block title.', array('!placeholder' => '&lt;none&gt;')),
    '#default_value' => isset($block->title) ? $block->title : '',
    '#weight' => -18,
  );

  // Module-specific mongodb_block_ui configuration.
  if ($settings = module_invoke($block->module, 'block_configure', $block->delta)) {
    foreach ($settings as $k => $v) {
      $form['settings'][$k] = $v;
    }
  }

  // Region settings.
  $form['regions'] = array(
    '#type' => 'fieldset',
    '#title' => t('Region settings'),
    '#collapsible' => FALSE,
    '#description' => t('Specify in which themes and regions this block is displayed.'),
    '#tree' => TRUE,
  );
//
//   $theme_default = variable_get('theme_default', 'garland');
//   foreach (list_themes() as $key => $theme) {
//     // Only display enabled themes
//     if ($theme->status) {
//       $region = db_query("SELECT region FROM {mongodb_block_ui} WHERE module = :module AND delta = :delta AND theme = :theme", array(
//         ':module' => $mongodb_block_ui->module,
//         ':delta' => $mongodb_block_ui->delta,
//         ':theme' => $key,
//       ))->fetchField();
//
//       $form['regions'][$key] = array(
//         '#type' => 'select',
//         '#title' => $theme->info['name'],
//         '#default_value' => (!empty($region) ? $region : BLOCK_REGION_NONE),
//         '#options' => array(BLOCK_REGION_NONE => t('<none>')) + system_region_list($key, REGIONS_VISIBLE),
//         '#expandable' => ($key !== $theme_default),
//         '#weight' => ($key == $theme_default ? 9 : 10),
//       );
//     }
//   }

  // Visibility settings.
/*
  $form['visibility_title'] = array(
    '#type' => 'item',
    '#title' => t('Visibility settings'),
  );
  $form['visibility'] = array(
    '#type' => 'vertical_tabs',
    '#attached' => array(
      'js' => array(drupal_get_path('module', 'mongodb_block_ui') . '/mongodb_block_ui.js'),
    ),
  );

  //Per-path visibility.
  $form['visibility']['path'] = array(
    '#type' => 'fieldset',
    '#title' => t('Pages'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#group' => 'visibility',
    '#weight' => 0,
  );

  $access = user_access('use PHP for settings');
  if (isset($block->visibility) && $block->visibility == 2 && !$access) {
    $form['visibility']['path']['visibility'] = array(
      '#type' => 'value',
      '#value' => 2,
    );
    $form['visibility']['path']['pages'] = array(
      '#type' => 'value',
      '#value' => isset($block->pages) ? $block->pages : '',
    );
  }
  else {
    $options = array(
      t('All pages except those listed'),
      t('Only the listed pages'),
    );
    $description = t("Specify pages by using their paths. Enter one path per line. The '%' character is a wildcard. Example paths are %blog for the blog page and %blog-wildcard for every personal blog. %front is the front page.", array('%blog' => 'blog', '%blog-wildcard' => 'blog/%', '%front' => '<front>'));

    if (module_exists('php') && $access) {
      $options[] = t('Pages on which this PHP code returns <code>TRUE</code> (experts only)');
      $title = t('Pages or PHP code');
      $description .= ' ' . t('If the PHP option is chosen, enter PHP code between %php. Note that executing incorrect PHP code can break your Drupal site.', array('%php' => '<?php ?>'));
    }
    else {
      $title = t('Pages');
    }
    $form['visibility']['path']['visibility'] = array(
      '#type' => 'radios',
      '#title' => t('Show block on specific pages'),
      '#options' => $options,
      '#default_value' => isset($block->visibility) ? $block->visibility : 0,
    );
        */
    $form['visibility']['path']['pages'] = array(
      '#type' => 'textarea',
      '#title' => '<span class="element-invisible">' . $title . '</span>',
      '#default_value' => isset($block->pages) ? $block->pages : '',
      '#description' => $description,
    );
  //}

  /*

  // Per-role visibility.
  $default_role_options = db_query("SELECT rid FROM {mongodb_block_ui_role} WHERE module = :module AND delta = :delta", array(
    ':module' => $mongodb_block_ui->module,
    ':delta' => $mongodb_block_ui->delta,
  ))->fetchCol();
  $role_options = db_query('SELECT rid, name FROM {role} ORDER BY name')->fetchAllKeyed();
  $form['visibility']['role'] = array(
    '#type' => 'fieldset',
    '#title' => t('Roles'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#group' => 'visibility',
    '#weight' => 10,
  );
  $form['visibility']['role']['roles'] = array(
    '#type' => 'checkboxes',
    '#title' => t('Show mongodb_block_ui for specific roles'),
    '#default_value' => $default_role_options,
    '#options' => $role_options,
    '#description' => t('Show this mongodb_block_ui only for the selected role(s). If you select no roles, the mongodb_block_ui will be visible to all users.'),
  );

  // Per-user visibility.
  $form['visibility']['user'] = array(
    '#type' => 'fieldset',
    '#title' => t('Users'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#group' => 'visibility',
    '#weight' => 20,
  );
  $form['visibility']['user']['custom'] = array(
    '#type' => 'radios',
    '#title' => t('Customizable per user'),
    '#options' => array(
      t('Not customizable'),
      t('Customizable, visible by default'),
      t('Customizable, hidden by default'),
    ),
    '#description' => t('Allow individual users to customize the visibility of this mongodb_block_ui in their account settings.'),
    '#default_value' => isset($mongodb_block_ui->custom) ? $mongodb_block_ui->custom : 0,
  );*/

  $form['actions'] = array('#type' => 'container', '#attributes' => array('class' => array('form-actions')));
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save block'),
  );

  return $form;
}

function mongodb_block_ui_admin_configure_validate($form, &$form_state) {
  if ($form_state['values']['module'] == 'mongodb_block_ui') {
    $collection = mongodb_collection('block_custom');
    $custom_block_exists = (bool) $collection->findOne(array(
      'bid' => array('ne' => $form_state['values']['delta']),
      'info' => $form_state['values']['info']));

    if (empty($form_state['values']['info']) || $custom_block_exists) {
      form_set_error('info', t('Ensure that each block description is unique.'));
    }
  }
}

function mongodb_block_ui_admin_configure_submit($form, &$form_state) {
  // FIXME: this stores the visiblity settings, the title of the block etc.

  $collection = mongodb_collection('block');
  $query = array(
    '_id' => array(
      'module' => $form_state['values']['module'],
      'delta' => $form_state['values']['delta'],
    ),
  );
  $pages = array_map('trim', explode("\n", $form_state['values']['pages']));
  if (!$pages) {
    $pages = array('%');
  }
  $set = array(
    'pages' => $pages,
    'title' => $form_state['values']['title'],
  );
  $collection->update($query, array('$set' => $set));
  var_dump($collection->findOne($query));
  /*
  db_delete('block_role')
    ->condition('module', $form_state['values']['module'])
    ->condition('delta', $form_state['values']['delta'])
    ->execute();
  $query = db_insert('block_role')->fields(array('rid', 'module', 'delta'));
  foreach (array_filter($form_state['values']['roles']) as $rid) {
    $query->values(array(
      'rid' => $rid,
      'module' => $form_state['values']['module'],
      'delta' => $form_state['values']['delta'],
    ));
  }
  $query->execute();
  */
  // TODO: convert this.
/*
  // Store regions per theme for this block
  foreach ($form_state['values']['regions'] as $theme => $region) {
    db_merge('block')
      ->key(array('theme' => $theme, 'delta' => $form_state['values']['delta'], 'module' => $form_state['values']['module']))
      ->fields(array(
        'region' => ($region == BLOCK_REGION_NONE ? '' : $region),
        'pages' => trim($form_state['values']['pages']),
        'status' => (int) ($region != BLOCK_REGION_NONE),
      ))
      ->execute();
  }
  */
  module_invoke($form_state['values']['module'], 'block_save', $form_state['values']['delta'], $form_state['values']);
  drupal_set_message(t('The block configuration has been saved.'));
  cache_clear_all();
  $form_state['redirect'] = 'admin/structure/mongodb_block/manage/'. $form_state['values']['module'] .'/'. $form_state['values']['delta'];
}

/**
 * Menu callback: display the custom mongodb_block_ui addition form.
 */
function mongodb_block_ui_add_block_form($form, &$form_state) {
  return mongodb_block_ui_admin_configure($form, $form_state, 'mongodb_block_ui', NULL);
}

function mongodb_block_ui_add_block_form_validate($form, &$form_state) {
  $collection = mongodb_collection('block_custom');
  $custom_block_exists = (bool) $collection->findOne(array('info' => $form_state['values']['info']));

  if (empty($form_state['values']['info']) || $custom_block_exists) {
    form_set_error('info', t('Ensure that each block description is unique.'));
  }
}

/**
 * Save the new custom block.
 */
function mongodb_block_ui_add_block_form_submit($form, &$form_state) {
  $collection = mongodb_collection('block_custom');
  $delta = mongodb_next_id('block_custom');
  $collection->insert(array(
    'body' => $form_state['values']['body'],
    'info' => $form_state['values']['info'],
    'format' => (int) $form_state['values']['format'],
    'bid' => (int) $delta,
  ));
  // Store block delta to allow other modules to work with new mongodb_block_ui.
  $form_state['values']['delta'] = $delta;

  $block_collection = mongodb_collection('block');
  foreach (list_themes() as $key => $theme) {
    if ($theme->status) {
      $block = array(
        'visibility' => (int) $form_state['values']['visibility'],
        'pages' => trim($form_state['values']['pages']),
        'custom' => (int) $form_state['values']['custom'],
        'title' => $form_state['values']['title'],
        'module' => $form_state['values']['module'],
        'theme' => $theme->name,
        'status' => 0,
        'weight' => 0,
        'delta' => $delta,
        'cache' => DRUPAL_NO_CACHE,
      );
      $block_collection->save($block);
    }
  }
/*
  $query = db_insert('mongodb_block_ui_role')->fields(array('rid', 'module', 'delta'));
  foreach (array_filter($form_state['values']['roles']) as $rid) {
    $query->values(array(
      'rid' => $rid,
      'module' => $form_state['values']['module'],
      'delta' => $delta,
    ));
  }
  $query->execute();

  // TODO convert his to mongodb
  // Store regions per theme for this block
  foreach ($form_state['values']['regions'] as $theme => $region) {
    db_merge('mongodb_block_ui')
      ->key(array('theme' => $theme, 'delta' => $delta, 'module' => $form_state['values']['module']))
      ->fields(array(
        'region' => ($region == BLOCK_REGION_NONE ? '' : $region),
        'pages' => trim($form_state['values']['pages']),
        'status' => (int) ($region != BLOCK_REGION_NONE),
      ))
      ->execute();
  }*/

  drupal_set_message(t('The block has been created.'));
  cache_clear_all();
  $form_state['redirect'] = 'admin/structure/mongodb_block/manage/mongodb_block_ui/'. $delta;
}

/**
 * Menu callback; confirm deletion of custom block.
 */
function mongodb_block_ui_custom_block_delete($form, &$form_state, $module, $delta) {
  $block = mongodb_block_ui_load($module, $delta);
  $custom_block = mongodb_block_ui_custom_block_get($block->delta);
  $form['info'] = array('#type' => 'hidden', '#value' => $custom_block['info'] ? $custom_block['info'] : $custom_block['title']);
  $form['bid'] = array('#type' => 'hidden', '#value' => $block->delta);

  return confirm_form($form, t('Are you sure you want to delete the block %name?', array('%name' => $custom_block['info'])), 'admin/structure/block', '', t('Delete'), t('Cancel'));
}

/**
 * Deletion of custom mongodb_block_uis.
 */
function mongodb_block_ui_custom_mongodb_block_ui_delete_submit($form, &$form_state) {
$collection = mongodb_collection('block_custom');
  $collection->delete(array('bid' => $form_state['values']['bid']));

  $collection = mongodb_collection('block');
  $collection->delete(array('module' => 'block', 'delta' => $form_state['values']['bid']));
//   db_delete('mongodb_block_ui_role')
//     ->condition('module', 'mongodb_block_ui')
//     ->condition('delta', $form_state['values']['bid'])
//     ->execute();
  drupal_set_message(t('The block %name has been removed.', array('%name' => $form_state['values']['info'])));
  cache_clear_all();
  $form_state['redirect'] = 'admin/structure/mongodb_block';
  return;
}

/**
 * Process variables for mongodb_block_ui-admin-display.tpl.php.
 *
 * The $variables array contains the following arguments:
 * - $form
 *
 * @see mongodb_block_ui-admin-display.tpl.php
 * @see theme_mongodb_block_ui_admin_display()
 */
function template_preprocess_mongodb_block_ui_admin_display_form(&$variables) {

  $block_regions = system_region_list($variables['form']['edited_theme']['#value'], REGIONS_VISIBLE);
  $variables['block_regions'] = $block_regions + array(BLOCK_REGION_NONE => t('Disabled'));

  foreach ($block_regions as $key => $value) {
    // Initialize an empty array for the region.
    $variables['block_listing'][$key] = array();
  }

  // Initialize disabled mongodb_block_uis array.
  $variables['block_listing'][BLOCK_REGION_NONE] = array();

  // Set up to track previous region in loop.
  $last_region = '';
  foreach (element_children($variables['form']) as $i) {
    $block = &$variables['form'][$i];

    // Only take form elements that are mongodb_block_uis.
    if (isset($block['info'])) {
      // Fetch region for current mongodb_block_ui.
      $region = $block['region']['#default_value'];

      // Set special classes needed for table drag and drop.
      $variables['form'][$i]['region']['#attributes']['class'] = array('block-region-select', 'block-region-' . $region);
      $variables['form'][$i]['weight']['#attributes']['class'] = array('block-weight', 'block-weight-' . $region);

      $variables['block_listing'][$region][$i] = new stdClass();
      $variables['block_listing'][$region][$i]->row_class = !empty($block['#attributes']['class']) ? implode(' ', $block['#attributes']['class']) : '';
      $variables['block_listing'][$region][$i]->block_modified = !empty($block['#attributes']['class']) && in_array('block-modified', $block['#attributes']['class']);
      $variables['block_listing'][$region][$i]->block_title =  drupal_render($block['info']);
      $variables['block_listing'][$region][$i]->region_select = drupal_render($block['region']) . drupal_render($block['theme']);
      $variables['block_listing'][$region][$i]->weight_select = drupal_render($block['weight']);
      $variables['block_listing'][$region][$i]->configure_link = drupal_render($block['configure']);
      $variables['block_listing'][$region][$i]->delete_link = !empty($block['delete']) ? drupal_render($block['delete']) : '';
      $variables['block_listing'][$region][$i]->printed = FALSE;

      $last_region = $region;
    }
  }

  $variables['form_submit'] = drupal_render_children($variables['form']);
}
