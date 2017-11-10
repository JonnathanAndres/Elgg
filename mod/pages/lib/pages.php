<?php
/**
 * Pages function library
 */

/**
 * Prepare the add/edit form variables
 *
 * @param ElggPage       $page        the page to edit
 * @param int            $parent_guid parrent page guid
 * @param ElggAnnotation $revision    revision
 *
 * @return array
 */
function pages_prepare_form_vars($page = null, $parent_guid = 0, $revision = null) {

	// input names => defaults
	$values = [
		'title' => '',
		'description' => '',
		'access_id' => ACCESS_DEFAULT,
		'write_access_id' => ACCESS_DEFAULT,
		'tags' => '',
		'container_guid' => elgg_get_page_owner_guid(),
		'guid' => null,
		'entity' => null,
		'parent_guid' => $parent_guid,
	];

	if ($page instanceof ElggPage) {
		foreach (array_keys($values) as $field) {
			if (isset($page->$field)) {
				$values[$field] = $page->$field;
			}
		}
		
		$values['entity'] = $page;
	}

	if (elgg_is_sticky_form('page')) {
		$sticky_values = elgg_get_sticky_values('page');
		foreach ($sticky_values as $key => $value) {
			$values[$key] = $value;
		}
		
		elgg_clear_sticky_form('page');
	}

	// load the revision annotation if requested
	if ($revision instanceof ElggAnnotation && $page instanceof ElggPage && $revision->entity_guid === $page->guid) {
		$values['description'] = $revision->value;
	}

	return $values;
}

/**
 * Recurses the page tree and adds the breadcrumbs for all ancestors
 *
 * @param ElggPage $page Page entity
 *
 * @return void
 */
function pages_prepare_parent_breadcrumbs($page) {
	$crumbs = [];

	while ($page instanceof ElggPage) {
		$crumbs[] = [
			'text' => $page->getDisplayName(),
			'href' => $page->getURL(),
		];
		$page = $page->getParentEntity();
	}

	array_shift($crumbs);
	$crumbs = array_reverse($crumbs);

	foreach ($crumbs as $crumb) {
		elgg_push_breadcrumb($crumb['text'], $crumb['href']);
	}
}

/**
 * Produce the navigation tree
 *
 * @param ElggEntity $container Container entity for the pages
 *
 * @return array
 */
function pages_get_navigation_tree($container) {
	if (!elgg_instanceof($container)) {
		return;
	}

	$top_pages = elgg_get_entities_from_metadata([
		'type' => 'object',
		'subtype' => 'page',
		'container_guid' => $container->guid,
		'limit' => false,
		'batch' => true,
		'metadata_name_value_pairs' => [
			'parent_guid' => 0,
		],
	]);

	$tree = [];
	$depths = [];

	/* @var $page ElggPage */
	foreach ($top_pages as $page) {
		$tree[] = [
			'guid' => $page->guid,
			'title' => $page->getDisplayName(),
			'url' => $page->getURL(),
			'depth' => 0,
		];
		$depths[$page->guid] = 0;

		$stack = [];
		array_push($stack, $page);
		while (count($stack) > 0) {
			$parent = array_pop($stack);
			$children = elgg_get_entities_from_metadata([
				'type' => 'object',
				'subtype' => 'page',
				'limit' => false,
				'batch' => true,
				'metadata_name_value_pairs' => [
					'parent_guid' => $parent->guid,
				],
			]);

			/* @var $child ElggPage */
			foreach ($children as $child) {
				$tree[] = [
					'guid' => $child->guid,
					'title' => $child->getDisplayName(),
					'url' => $child->getURL(),
					'parent_guid' => $parent->guid,
					'depth' => $depths[$parent->guid] + 1,
				];
				$depths[$child->guid] = $depths[$parent->guid] + 1;
				array_push($stack, $child);
			}
		}
	}

	return $tree;
}

/**
 * Register the navigation menu
 *
 * @param ElggEntity $container Container entity for the pages
 * @param ElggPage   $selected  Selected page
 *
 * @return void
 */
function pages_register_navigation_tree($container, $selected = null) {
	
	$pages = pages_get_navigation_tree($container);
	if (empty($pages)) {
		return;
	}

	foreach ($pages as $page) {
		elgg_register_menu_item('pages_nav', [
			'name' => $page['guid'],
			'text' => $page['title'],
			'href' => $page['url'],
			'parent_name' => elgg_extract('parent_guid', $page),
			'selected' => $selected instanceof ElggPage && $selected->guid === $page['guid'],
		]);
	}
}
