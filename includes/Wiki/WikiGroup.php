<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki;

/**
 *
 */
class WikiGroup {
	/** @var Wiki */
	private $mainWiki;
	/** @var Wiki */
	private $centralWiki;

	/**
	 * @param Wiki $mainWiki
	 * @param Wiki $centralWiki
	 */
	public function __construct( Wiki $mainWiki, Wiki $centralWiki ) {
		$this->mainWiki = $mainWiki;
		$this->centralWiki = $centralWiki;
	}

	/**
	 * @return Wiki
	 */
	public function getMainWiki() : Wiki {
		return $this->mainWiki;
	}

	/**
	 * @return Wiki
	 */
	public function getCentralWiki() : Wiki {
		return $this->centralWiki;
	}
}
