<?php declare( strict_types=1 );

namespace BotRiconferme\TaskHelper;

enum RunMode: string {
	case FULL = 'full';
	case TASK = 'task';
	case SUBTASK = 'subtask';
}
