<?php

namespace BotRiconferme;

class ConfigItwiki extends Config {
	protected function __construct() {
		$defaults = [
			'url' => 'https://it.wikipedia.org/w/api.php',
			'username',###################################################
			'password',###################################################
			'list-title' => 'Utente:Daimona_Eaytoy/List.json',
			'list-update-summary' => 'Aggiornamento lista',
			'user-notice-msg' => '{{Avviso riconferma|$1}} ~~~~',###################################################
			'user-notice-title' => 'Riconferma amministratore',
			'user-notice-summary' => 'Aggiungo avviso riconferma',
			'ric-page-text' => "{{subst:Wikipedia:Amministratori/Riconferma annuale/Schema|{$Nome utente}|{$Data di attribuzione}|{{subst:#timel:j F Y}}|{{subst:LOCALTIME}}|{{subst:#timel:j F Y|+7 DAYS}}|${N° voti quorum}}}",###################################################
			'ric-page-summary' => 'Avvio procedura',
			'ric-page-prefix' => 'Wikipedia:Amministratori/Riconferma annuale',
			'ric-news-page' => 'Template:VotazioniRCnews',
			'ric-news-page-summary' => '+1 riconferma',
			'ric-main-page' => 'Wikipedia:Amministratori/Riconferma annuale',
			'ric-main-page-summary' => '+1 riconferma',
			'ric-vote-page' => 'Wikipedia:Wikipediano/Votazioni',
			'ric-vote-page-summary' => '+1 riconferma',
			'ric-base-page-text' => "# [[$title]]\n#: riconferma in corso",###################################################
			'ric-base-page-summary' => 'pagina di riepilogo',
			'ric-base-page-summary-update' => '+1 riconferma',
		];
		
		foreach ( $defaults as $opt => $val ) {
			$this->set( $opt, $val );
		}
	}
}
