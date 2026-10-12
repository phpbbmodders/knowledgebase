<?php

/**
 *
 * Knowledge Base extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\knowledgebase\controller;

use phpbb\request\request;
use phpbbmodders\knowledgebase\inc\alternative_styles;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a custom alternative stylesheet from the board's store folder.
 *
 * store/ is not reachable by URL, so the stylesheet an administrator picked
 * in the ACP is read and sent from here. Only a file the alternative_styles
 * service accepts is ever served: a strictly named .css file directly inside
 * the custom folder, not a symbolic link, not oversized. Anything else is a
 * plain 404, so no path outside the folder can be asked for.
 */
class stylesheet
{
	/** @var alternative_styles */
	protected $alt_styles;

	/** @var request */
	protected $request;

	public function __construct(alternative_styles $alt_styles, request $request)
	{
		$this->alt_styles = $alt_styles;
		$this->request = $request;
	}

	/**
	 * The stylesheet named by the "f" query parameter.
	 *
	 * @return Response The CSS, a 304 when the browser's copy is current, or a 404
	 */
	public function serve(): Response
	{
		$path = $this->alt_styles->custom_file_path($this->request->variable('f', ''));
		if ($path === null)
		{
			return new Response('Not found', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
		}

		$modified = (int) filemtime($path);
		$etag = '"' . md5($path . '|' . $modified . '|' . filesize($path)) . '"';
		$headers = [
			'Content-Type'           => 'text/css; charset=UTF-8',
			'X-Content-Type-Options' => 'nosniff',
			'Cache-Control'          => 'public, max-age=300',
			'ETag'                   => $etag,
			'Last-Modified'          => gmdate('D, d M Y H:i:s', $modified) . ' GMT',
		];

		// request::server() HTML-escapes what it returns, which turns the
		// quotes around an entity tag into &quot;, so decode them back first.
		$if_none_match = htmlspecialchars_decode((string) $this->request->server('HTTP_IF_NONE_MATCH', ''), ENT_COMPAT);
		if (preg_replace('/^W\//', '', trim($if_none_match)) === $etag)
		{
			return new Response('', 304, $headers);
		}

		$css = file_get_contents($path);

		return $css === false
			? new Response('Not found', 404, ['Content-Type' => 'text/plain; charset=UTF-8'])
			: new Response($css, 200, $headers);
	}
}
