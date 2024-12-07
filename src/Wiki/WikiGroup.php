<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki;

/**
 * Relevant wikis in the wiki farm.
 */
readonly class WikiGroup {
	public function __construct(
		private Wiki $mainWiki,
		private Wiki $centralWiki,
		private Wiki $privateWiki
	) {
	}

	public function getMainWiki(): Wiki {
		return $this->mainWiki;
	}

	public function getCentralWiki(): Wiki {
		return $this->centralWiki;
	}

	public function getPrivateWiki(): Wiki {
		return $this->privateWiki;
	}
}
