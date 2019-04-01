<?php

namespace BotRiconferme;

class Bot {
	public function run() {
		$manager = new TaskManager;
		$manager->run( TaskManager::MODE_COMPLETE );
	}
}
