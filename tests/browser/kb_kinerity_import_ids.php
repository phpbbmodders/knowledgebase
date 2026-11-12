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
 * Pick the article ids tests/browser/kb_kinerity_import.js needs from a board
 * moved from kinerity/knowledgebase, using the kb_kinerity_* backup tables.
 *
 * Input:  the board root as the first argument.
 * Output: one line of JSON for the KB_IDS environment variable:
 *         approved, approved_number, cross, cross_second_category, waiting,
 *         deleted, special, special_title (new ids in this extension).
 * Run it with the board's own PHP, for example through seed-forum:
 *   python3 bin/board-php.py BOARD tests/browser/kb_kinerity_import_ids.php BOARD
 */

if (PHP_SAPI !== 'cli' || !is_file(rtrim($argv[1] ?? '', '/') . '/config.php'))
{
	fwrite(STDERR, "Usage: php kb_kinerity_import_ids.php BOARD_ROOT\n");
	exit(2);
}

$phpbb_root_path = rtrim($argv[1], '/') . '/';
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
$db = (new \phpbb\di\container_builder($phpbb_root_path, $phpEx))->with_config($phpbb_config_php_file)->get_container()->get('dbal.conn');
$p = $table_prefix;

/**
 * The first row of a query (limited to one).
 */
function first(string $sql): array
{
	global $db;
	$result = $db->sql_query_limit($sql, 1);
	$row = $db->sql_fetchrow($result) ?: [];
	$db->sql_freeresult($result);

	return $row;
}

// An approved article whose text has BBCode (parsed text with markup starts with <r>)
$approved = first('SELECT k.kb_new_id, k.article_title FROM ' . $p . "kb_kinerity_articles k
	WHERE k.article_visibility = 1 AND k.article_id > 1 AND k.article_text LIKE '<r>%' ORDER BY k.article_id");
$cross = first('SELECT article_id, category_id FROM ' . $p . 'kb_article_categories ORDER BY article_id');
$waiting = first('SELECT kb_new_id FROM ' . $p . 'kb_kinerity_articles WHERE article_visibility = 0 ORDER BY article_id');
$deleted = first('SELECT kb_new_id FROM ' . $p . 'kb_kinerity_articles WHERE article_visibility = 2 ORDER BY article_id');
$special = first('SELECT a.article_id, a.article_title FROM ' . $p . 'kb_kinerity_articles k JOIN ' . $p . "kb_articles a ON (a.article_id = k.kb_new_id)
	WHERE k.article_visibility = 1 AND k.article_title LIKE '%&amp;%' ORDER BY k.article_id");

echo json_encode([
	'approved'              => (int) ($approved['kb_new_id'] ?? 0),
	'approved_number'       => (int) preg_replace('/\D/', '', $approved['article_title'] ?? ''),
	'cross'                 => (int) ($cross['article_id'] ?? 0),
	'cross_second_category' => (int) ($cross['category_id'] ?? 0),
	'waiting'               => (int) ($waiting['kb_new_id'] ?? 0),
	'deleted'               => (int) ($deleted['kb_new_id'] ?? 0),
	'special'               => (int) ($special['article_id'] ?? 0),
	'special_title'         => html_entity_decode($special['article_title'] ?? '', ENT_QUOTES, 'UTF-8'),
]) . "\n";
