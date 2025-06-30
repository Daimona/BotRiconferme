<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki;

enum Outcome: int {
	case OK = 0;
	case FAIL_VOTES = 1;
	case NO_QUORUM = 2;

	public function isFailure(): bool {
		return $this !== self::OK;
	}
}
