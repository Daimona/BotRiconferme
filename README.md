# BotRiconferme

[![Build Status](https://travis-ci.com/Daimona/BotRiconferme.svg?branch=master)](https://travis-ci.com/Daimona/BotRiconferme)
[![Maintainability](https://api.codeclimate.com/v1/badges/18075a20c88c92e8f909/maintainability)](https://codeclimate.com/github/Daimona/BotRiconferme/maintainability)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Daimona/BotRiconferme/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Daimona/BotRiconferme/?branch=master)

## Note: This tool is only usable on itwp since it's impossible to localize all the bureaucracy :-[

## Guida (IT)
Il bot prevede molte opzioni configurabili, facenti capo a 3 pagine diverse on-wiki:

### https://it.wikipedia.org/wiki/Utente:BotRiconferme/List.json
La pagina raccoglie dati su tutti gli admin, in particolare le date di elezione a sysop, burocrate e check user. L'aggiornamento è completamente automatico e non servirà mai di modificare la pagina per aggiornare le date. Tranne in un caso: oltre ai gruppi, per ogni utente sono disponibili due ulteriori opzioni:
  * `override`: Inserendo una data in questo campo, la prossima riconferma dell'utente sarà anticipata a tale data, anziché a quella prevista di default. Il parametro verrà rimosso automaticamente all'inizio della riconferma successiva.
  * `override-perm`: Come il precedente, ma resta in vigore "per sempre" e non verrà mai tolto in automatico.

### https://it.wikipedia.org/wiki/Utente:BotRiconferme/Config.json
La pagina raccoglie un insieme di opzioni di configurazione più "oggettivi", e poco soggetti a cambiamenti. Sono quasi tutti titoli delle pagine che il bot dovrà modificare o leggere, tranne i primi due:
 * `exclude-admins` contiene una lista di admin da non includere nella lista e quindi da ignorare completamente.
 * `bot-edits` serve a decidere se contrassegnare gli edit del bot come bot-edit o meno.
 
 ### https://it.wikipedia.org/wiki/Utente:BotRiconferme/Messages.json
 Questa è l'unica pagina che potrebbe aver bisogno di qualche aggiornamento. Contiene (quasi) tutti i messaggi utilizzati dal bot: sostanzialmente, testo da aggiungere alle pagine e campi oggetto da utilizzare. In alcuni messaggi sono presenti stringhe come `$num`. Queste rappresentano variabili sostituite al momento di utilizzare il messaggio. Inoltre, alcuni usano il costrutto `{{$plur|$num|a|e}}`. Esso funziona esattamente come il `{{PLURAL}}` di MediaWiki, e viene utilizzato nei campi oggetto.
 
 ## Bug e altre creature mitologiche
 ...Ce ne sono sicuramente! Se ne trovi uno, non esitare! Fammelo presente dove ti è più comodo, va bene sia on-wiki che aprendo un Issue qui su Github. Lo stesso vale per proposte di nuove funzionalità.
