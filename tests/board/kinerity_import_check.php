<?php
/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

/**
 * Check a board that was moved from kinerity/knowledgebase to this extension.
 *
 * Compares the kb_kinerity_* backup tables, row by row, with what the import
 * wrote: every category and article converted once, the category tree, each
 * article's primary and cross-listed categories, state, text and counters,
 * the same permissions on every category, and kinerity's permissions,
 * modules, settings, notification types and extension entry gone.
 *
 * Input:  the board root as the first argument; optionally --expect-member
 *         NAME:OPTION:SETTING (repeatable), e.g. forum_drew:kb_u_add:0, and
 *         --expect-group NAME:OPTION:SETTING, to check a holder's permission
 *         is on every category.
 * Output: one line per check (PASS/FAIL with a detail) and a summary.
 *         Exit status 0 when every check passes, 1 otherwise, 2 on bad input.
 *
 * Run it with the board's own PHP, for example through seed-forum:
 *   python3 bin/board-php.py BOARD tests/board/kinerity_import_check.php BOARD
 */

if (PHP_SAPI !== 'cli')
{
	exit(2);
}

$phpbb_root_path = rtrim($argv[1] ?? '', '/') . '/';
if (!is_file($phpbb_root_path . 'config.php'))
{
	fwrite(STDERR, "Usage: php kinerity_import_check.php BOARD_ROOT [--expect-member NAME:OPTION:SETTING] [--expect-group NAME:OPTION:SETTING]\n");
	exit(2);
}

$expectations = ['member' => [], 'group' => []];
for ($i = 2; $i < count($argv); $i += 2)
{
	if (!in_array($argv[$i], ['--expect-member', '--expect-group'], true) || !isset($argv[$i + 1]) || substr_count($argv[$i + 1], ':') !== 2)
	{
		fwrite(STDERR, "Unknown or incomplete argument: {$argv[$i]}\n");
		exit(2);
	}
	[$name, $option, $setting] = explode(':', $argv[$i + 1]);
	$expectations[$argv[$i] === '--expect-member' ? 'member' : 'group'][] = [$name, $option, (int) $setting];
}

$phpEx = 'php';
define('IN_PHPBB', true);
chdir($phpbb_root_path);
require($phpbb_root_path . 'includes/startup.php');
require($phpbb_root_path . 'phpbb/class_loader.php');
(new \phpbb\class_loader('phpbb\\', "{$phpbb_root_path}phpbb/", $phpEx))->register();
$phpbb_config_php_file = new \phpbb\config_php_file($phpbb_root_path, $phpEx);
extract($phpbb_config_php_file->get_all());
if (!defined('PHPBB_ENVIRONMENT'))
{
	define('PHPBB_ENVIRONMENT', 'production');
}
require($phpbb_root_path . 'includes/constants.php');
require($phpbb_root_path . 'includes/functions.php');
require($phpbb_root_path . 'includes/functions_content.php');
require($phpbb_root_path . 'includes/utf/utf_tools.php');
(new \phpbb\class_loader('\\', "{$phpbb_root_path}ext/", $phpEx))->register();
$phpbb_container = (new \phpbb\di\container_builder($phpbb_root_path, $phpEx))->with_config($phpbb_config_php_file)->get_container();

use phpbbmodders\knowledgebase\inc\functions_kb;
use phpbbmodders\knowledgebase\inc\kinerity_import;

/** @var \phpbb\db\driver\driver_interface $db */
$db = $phpbb_container->get('dbal.conn');
$tools = $phpbb_container->get('dbal.tools');
$p = $table_prefix;

$failures = 0;
/**
 * Print one check's result.
 */
function check(string $name, bool $passed, string $detail = ''): void
{
	global $failures;
	if (!$passed)
	{
		$failures++;
	}
	echo ($passed ? 'PASS ' : 'FAIL ') . $name . ($detail !== '' ? ' - ' . $detail : '') . "\n";
}

/**
 * All rows of a query, streamed into an array keyed by $key when given.
 */
function rows(string $sql, ?string $key = null): array
{
	global $db;
	$result = $db->sql_query($sql);
	$rows = [];
	while ($row = $db->sql_fetchrow($result))
	{
		if ($key === null)
		{
			$rows[] = $row;
		}
		else
		{
			$rows[(int) $row[$key]] = $row;
		}
	}
	$db->sql_freeresult($result);

	return $rows;
}

// Tables
check('kinerity tables moved aside', !$tools->sql_table_exists($p . 'kb_article_category')
	&& !$tools->sql_column_exists($p . 'kb_articles', 'article_poster_id')
	&& !$tools->sql_column_exists($p . 'kb_categories', 'category_description'));
check('backup tables kept', $tools->sql_table_exists($p . 'kb_kinerity_articles') && $tools->sql_table_exists($p . 'kb_kinerity_categories') && $tools->sql_table_exists($p . 'kb_kinerity_article_category'));
if (!$tools->sql_table_exists($p . 'kb_kinerity_articles'))
{
	echo "No backup tables; nothing more to compare.\n";
	exit(1);
}

$unfinished = rows('SELECT migration_name FROM ' . MIGRATIONS_TABLE . "
	WHERE migration_name LIKE '%phpbbmodders%knowledgebase%' AND (migration_schema_done = 0 OR migration_data_done = 0)");
check('every Knowledge Base migration finished', !$unfinished, $unfinished ? implode(', ', array_column($unfinished, 'migration_name')) : '');

// Categories
$backup_categories = rows('SELECT * FROM ' . $p . 'kb_kinerity_categories', 'category_id');
$categories = rows('SELECT * FROM ' . $p . 'kb_categories', 'category_id');
$map = [];
foreach ($backup_categories as $old_id => $row)
{
	$map[$old_id] = (int) $row['kb_new_id'];
}
check('every kinerity category converted once', !in_array(0, $map, true) && count(array_unique($map)) === count($map) && !array_diff($map, array_keys($categories)),
	count($backup_categories) . ' kinerity, ' . count($categories) . ' now');

$fallback = array_keys(array_filter($categories, fn($row) => $row['category_name'] === kinerity_import::FALLBACK_CATEGORY_NAME && !in_array((int) $row['category_id'], $map, true)));
$extra = array_diff(array_keys($categories), $map, $fallback);
check('no unexpected categories', !$extra, $extra ? 'ids ' . implode(', ', array_slice($extra, 0, 10)) : (count($fallback) . ' fallback category'));

// Parents from the kinerity tree, as the import works them out
$sorted = $backup_categories;
uasort($sorted, fn($a, $b) => [(int) $a['left_id'], (int) $a['category_id']] <=> [(int) $b['left_id'], (int) $b['category_id']]);
$stack = [];
$tree_errors = $text_errors = [];
foreach ($sorted as $old_id => $row)
{
	while ($stack && (int) end($stack)['right_id'] < (int) $row['left_id'])
	{
		array_pop($stack);
	}
	$expected_parent = $stack ? $map[(int) end($stack)['category_id']] : 0;
	$stack[] = $row;

	$new = $categories[$map[$old_id]];
	if ((int) $new['parent_id'] !== $expected_parent || (int) $new['left_id'] !== (int) $row['left_id'] || (int) $new['right_id'] !== (int) $row['right_id'])
	{
		$tree_errors[] = $old_id;
	}
	$expected_details = kinerity_import::category_details((string) $row['category_description']);
	if ($new['category_name'] !== kinerity_import::fit_escaped((string) $row['category_name'], 255) || $new['category_details'] !== $expected_details)
	{
		$text_errors[] = $old_id;
	}
}
check('category tree (parent, left, right) as in kinerity', !$tree_errors, $tree_errors ? 'kinerity ids ' . implode(', ', array_slice($tree_errors, 0, 10)) : '');
check('category names and plain-text details', !$text_errors, $text_errors ? 'kinerity ids ' . implode(', ', array_slice($text_errors, 0, 10)) : '');

$nested_errors = [];
foreach ($categories as $id => $row)
{
	if ((int) $row['left_id'] >= (int) $row['right_id'])
	{
		$nested_errors[] = $id;
		continue;
	}
	if ((int) $row['parent_id'])
	{
		$parent = $categories[(int) $row['parent_id']] ?? null;
		if (!$parent || (int) $row['left_id'] <= (int) $parent['left_id'] || (int) $row['right_id'] >= (int) $parent['right_id'])
		{
			$nested_errors[] = $id;
		}
	}
}
check('nested set consistent', !$nested_errors, $nested_errors ? 'ids ' . implode(', ', array_slice($nested_errors, 0, 10)) : '');
$long = array_filter($categories, fn($row) => mb_strlen($row['category_details']) > 255 || mb_strlen($row['category_name']) > 255);
check('category text fits its columns', !$long);

// Articles
$links = [];
$result = $db->sql_query('SELECT article_id, category_id FROM ' . $p . 'kb_kinerity_article_category');
while ($row = $db->sql_fetchrow($result))
{
	if (!empty($map[(int) $row['category_id']]))
	{
		$links[(int) $row['article_id']][] = (int) $row['category_id'];
	}
}
$db->sql_freeresult($result);

$cross = [];
$result = $db->sql_query('SELECT article_id, category_id FROM ' . $p . 'kb_article_categories');
while ($row = $db->sql_fetchrow($result))
{
	$cross[(int) $row['article_id']][] = (int) $row['category_id'];
}
$db->sql_freeresult($result);

$backup_count = (int) rows('SELECT COUNT(*) AS total FROM ' . $p . 'kb_kinerity_articles')[0]['total'];
$article_count = (int) rows('SELECT COUNT(*) AS total FROM ' . $p . 'kb_articles')[0]['total'];
check('article count matches', $backup_count === $article_count, $backup_count . ' kinerity, ' . $article_count . ' now');

$errors = ['unconverted' => [], 'duplicate' => [], 'category' => [], 'cross-listing' => [], 'state' => [], 'content' => []];
$seen = [];
$primary_counts = [];
$sql = 'SELECT k.*, a.article_id AS new_id, a.article_category_id, a.approved, a.status, a.article_title AS new_title,
		a.article_description AS new_description, a.article_body, a.bbcode_uid AS new_uid, a.article_date, a.edit_date,
		a.author_id, a.author, a.author_type, a.views
	FROM ' . $p . 'kb_kinerity_articles k
	LEFT JOIN ' . $p . 'kb_articles a ON (a.article_id = k.kb_new_id)
	ORDER BY k.article_id';
$result = $db->sql_query($sql);
while ($row = $db->sql_fetchrow($result))
{
	$old_id = (int) $row['article_id'];
	if (!(int) $row['kb_new_id'] || $row['new_id'] === null)
	{
		$errors['unconverted'][] = $old_id;
		continue;
	}
	$new_id = (int) $row['new_id'];
	if (isset($seen[$new_id]))
	{
		$errors['duplicate'][] = $old_id;
	}
	$seen[$new_id] = true;

	$old_categories = array_values(array_unique(array_map(fn($id) => $map[$id], $links[$old_id] ?? [])));
	sort($old_categories);
	// The lowest kinerity category id, which is not always the lowest new id
	$by_old = $links[$old_id] ?? [];
	sort($by_old);
	$expected_primary = $by_old ? $map[$by_old[0]] : ($fallback[0] ?? -1);
	if ((int) $row['article_category_id'] !== $expected_primary)
	{
		$errors['category'][] = $old_id;
	}
	$expected_cross = array_values(array_diff($old_categories, [$expected_primary]));
	$actual_cross = $cross[$new_id] ?? [];
	sort($actual_cross);
	if ($expected_cross !== $actual_cross)
	{
		$errors['cross-listing'][] = $old_id;
	}

	[$approved, $status] = kinerity_import::map_visibility((int) $row['article_visibility']);
	if ((int) $row['approved'] !== $approved || (int) $row['status'] !== $status)
	{
		$errors['state'][] = $old_id;
	}

	if ($row['new_title'] !== kinerity_import::fit_escaped((string) $row['article_title'], 255)
		|| $row['new_description'] !== kinerity_import::fit_escaped((string) $row['article_description'], 255)
		|| $row['article_body'] !== $row['article_text']
		|| $row['new_uid'] !== (string) $row['bbcode_uid']
		|| (int) $row['article_date'] !== (int) $row['article_time']
		|| (int) $row['edit_date'] !== (int) $row['article_edit_time']
		|| (int) $row['author_id'] !== (int) $row['article_poster_id']
		|| $row['author'] !== $row['article_poster_name']
		|| (int) $row['author_type'] !== functions_kb::AUTHOR_TYPE_USER
		|| (int) $row['views'] !== (int) $row['article_views'])
	{
		$errors['content'][] = $old_id;
	}

	$primary_counts[(int) $row['article_category_id']] = ($primary_counts[(int) $row['article_category_id']] ?? 0) + 1;
}
$db->sql_freeresult($result);

foreach ($errors as $label => $ids)
{
	check('articles: ' . $label, !$ids, $ids ? count($ids) . ', kinerity ids ' . implode(', ', array_slice($ids, 0, 10)) : '');
}
$stray = array_diff_key($cross, $seen);
check('no cross-listings for articles that were not imported', !$stray);

$count_errors = [];
foreach ($categories as $id => $row)
{
	if ((int) $row['number_articles'] !== min($primary_counts[$id] ?? 0, kinerity_import::MAX_ARTICLE_COUNT))
	{
		$count_errors[] = $id . ' (' . $row['number_articles'] . ' vs ' . ($primary_counts[$id] ?? 0) . ')';
	}
}
check('article count per category', !$count_errors, $count_errors ? implode(', ', array_slice($count_errors, 0, 10)) : '');

// Permissions: every category gets the same rows
foreach (['groups' => 'group_id', 'users' => 'user_id'] as $type => $column)
{
	$sets = [];
	$result = $db->sql_query('SELECT ' . $column . ' AS holder, category_id, auth_option_id, auth_setting FROM ' . $p . 'kb_' . $type);
	while ($row = $db->sql_fetchrow($result))
	{
		$sets[(int) $row['category_id']][] = $row['holder'] . ':' . $row['auth_option_id'] . ':' . $row['auth_setting'];
	}
	$db->sql_freeresult($result);
	$signatures = [];
	foreach (array_keys($categories) as $id)
	{
		$set = $sets[$id] ?? [];
		sort($set);
		$signatures[implode(',', $set)] = true;
	}
	check("$type permissions identical on every category", count($signatures) === 1, count($signatures) . ' distinct set(s), ' . count($sets[array_key_first($categories)] ?? []) . ' rows each');
}

$options = [];
foreach (rows('SELECT auth_option_id, auth_option FROM ' . $p . 'kb_options') as $row)
{
	$options[$row['auth_option']] = (int) $row['auth_option_id'];
}
foreach (['member' => ['kb_users', 'user_id', USERS_TABLE, 'username_clean', 'user_id'], 'group' => ['kb_groups', 'group_id', GROUPS_TABLE, 'group_name', 'group_id']] as $kind => [$table, $column, $holder_table, $name_column, $id_column])
{
	foreach ($expectations[$kind] as [$name, $option, $setting])
	{
		$holder = rows('SELECT ' . $id_column . ' AS id FROM ' . $holder_table . " WHERE " . $name_column . " = '" . $db->sql_escape($kind === 'member' ? utf8_clean_string($name) : $name) . "'");
		$count = 0;
		if ($holder && isset($options[$option]))
		{
			$count = (int) rows('SELECT COUNT(*) AS total FROM ' . $p . $table . ' WHERE ' . $column . ' = ' . (int) $holder[0]['id'] . '
				AND auth_option_id = ' . $options[$option] . ' AND auth_setting = ' . $setting)[0]['total'];
		}
		check("$kind $name has $option = $setting on every category", $count === count($categories), $count . ' of ' . count($categories));
	}
}

// kinerity unregistered
$kinerity_options = rows('SELECT auth_option FROM ' . ACL_OPTIONS_TABLE . ' WHERE ' . $db->sql_in_set('auth_option', kinerity_import::KINERITY_PERMISSIONS));
check('kinerity permissions removed', !$kinerity_options, implode(', ', array_column($kinerity_options, 'auth_option')));
$orphans = 0;
foreach ([ACL_GROUPS_TABLE, ACL_USERS_TABLE, ACL_ROLES_DATA_TABLE] as $table)
{
	$orphans += (int) rows('SELECT COUNT(*) AS total FROM ' . $table . ' t LEFT JOIN ' . ACL_OPTIONS_TABLE . ' o ON (o.auth_option_id = t.auth_option_id)
		WHERE t.auth_option_id <> 0 AND o.auth_option_id IS NULL')[0]['total'];
}
check('no permission settings left for removed options', $orphans === 0, (string) $orphans);
foreach (['u_kb_view', 'a_manage_kb'] as $option)
{
	$id = rows('SELECT auth_option_id FROM ' . ACL_OPTIONS_TABLE . " WHERE auth_option = '" . $option . "'");
	$granted = $id ? (int) rows('SELECT COUNT(*) AS total FROM ' . ACL_ROLES_DATA_TABLE . ' WHERE auth_setting = 1 AND auth_option_id = ' . (int) $id[0]['auth_option_id'])[0]['total']
		+ (int) rows('SELECT COUNT(*) AS total FROM ' . ACL_GROUPS_TABLE . ' WHERE auth_setting = 1 AND auth_option_id = ' . (int) $id[0]['auth_option_id'])[0]['total'] : 0;
	check("$option granted somewhere", $granted > 0, $granted . ' role/group setting(s)');
}

$modules = rows('SELECT module_langname, module_basename FROM ' . MODULES_TABLE . "
	WHERE module_langname IN ('ACP_KNOWLEDGEBASE', 'ACP_KNOWLEDGEBASE_SETTINGS', 'ACP_KNOWLEDGEBASE_MANAGE') OR module_basename LIKE '%kinerity%'");
check('kinerity ACP modules removed', !$modules, implode(', ', array_column($modules, 'module_langname')));
$ours = (int) rows('SELECT COUNT(*) AS total FROM ' . MODULES_TABLE . " WHERE module_basename LIKE '%phpbbmodders%knowledgebase%'")[0]['total'];
check('Knowledge Base ACP modules present', $ours > 0, $ours . ' modules');

check('kinerity extension entry removed', !rows('SELECT ext_name FROM ' . EXT_TABLE . " WHERE ext_name = 'kinerity/knowledgebase'"));
check('kinerity migration records kept', (bool) rows('SELECT migration_name FROM ' . MIGRATIONS_TABLE . " WHERE migration_name LIKE '%kinerity%'"));
$settings = rows('SELECT config_name FROM ' . CONFIG_TABLE . ' WHERE ' . $db->sql_in_set('config_name', kinerity_import::KINERITY_CONFIG));
check('kinerity settings removed', !$settings, implode(', ', array_column($settings, 'config_name')));
$types = rows('SELECT notification_type_name FROM ' . NOTIFICATION_TYPES_TABLE . " WHERE notification_type_name LIKE 'kinerity.%'");
$user_settings = rows('SELECT item_type FROM ' . USER_NOTIFICATIONS_TABLE . " WHERE item_type LIKE 'kinerity.%'");
check('kinerity notification types and settings removed', !$types && !$user_settings);

echo $failures ? "{$failures} check(s) failed.\n" : "All checks passed.\n";
exit($failures ? 1 : 0);
