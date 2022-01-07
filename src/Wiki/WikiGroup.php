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
	/** @var Wiki */
	private $privateWiki;

	/**
	 * @param Wiki $mainWiki
	 * @param Wiki $centralWiki
	 * @param Wiki $privateWiki
	 */
	public function __construct( Wiki $mainWiki, Wiki $centralWiki, Wiki $privateWiki ) {
		$this->mainWiki = $mainWiki;
		$this->centralWiki = $centralWiki;
		$this->privateWiki = $privateWiki;
	}

	/**
	 * @return Wiki
	 */
	public function getMainWiki(): Wiki {
		return $this->mainWiki;
	}

	/**
	 * @return Wiki
	 */
	public function getCentralWiki(): Wiki {
		return $this->centralWiki;
	}

	/**
	 * @return Wiki
	 */
	public function getPrivateWiki(): Wiki {
		return $this->privateWiki;
	}
}
