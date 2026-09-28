# BotRiconferme

Bot per le riconferme degli amministratori di Wikipedia in italiano (itwiki).

<p align="left">
  <!-- Stato progetto & qualità -->
  <a href="https://github.com/Daimona/BotRiconferme/actions/workflows/main.yml"><img src="https://github.com/Daimona/BotRiconferme/actions/workflows/main.yml/badge.svg?branch=master" alt="CI Status"></a>
  <a href="LICENSE.md"><img src="https://img.shields.io/badge/license-AGPL--3.0--or--later-blue?style=flat-square" alt="License: AGPL-3.0-or-later"></a>
  <a href="https://codeclimate.com/github/Daimona/BotRiconferme/maintainability"><img src="https://api.codeclimate.com/v1/badges/18075a20c88c92e8f909/maintainability" alt="Maintainability"></a>
  <a href="https://scrutinizer-ci.com/g/Daimona/BotRiconferme/?branch=master"><img src="https://scrutinizer-ci.com/g/Daimona/BotRiconferme/badges/quality-score.png?b=master" alt="Scrutinizer Code Quality"></a>
</p>

<p align="left">
  <!-- Software Heritage -->
  <a href="https://archive.softwareheritage.org/browse/origin/?origin_url=https://github.com/Daimona/BotRiconferme"><img src="https://archive.softwareheritage.org/badge/origin/https://github.com/Daimona/BotRiconferme/" alt="Software Heritage origin"></a>
  <a href="https://archive.softwareheritage.org/swh:1:dir:dcb9cabd8ecd3830d37188c3f0c108d2d1fefad9;origin=https://github.com/Daimona/BotRiconferme;visit=swh:1:snp:e18e6f2202fb4f827f8446c4e0876dfeb65034d5;anchor=swh:1:rev:998f3355ab84731b79ba45a1fe007c7ac6f1ad59"><img src="https://archive.softwareheritage.org/badge/swh:1:dir:dcb9cabd8ecd3830d37188c3f0c108d2d1fefad9/" alt="Software Heritage directory"></a>
</p>

<p align="left">
  <!-- Stack tecnologico -->
  <a href="https://www.php.net/"><img src="https://img.shields.io/badge/PHP-8.4+-777bb4?logo=php&logoColor=white" alt="PHP 8.4+"></a>
  <a href="https://getcomposer.org/"><img src="https://img.shields.io/badge/Composer-885630?logo=composer&logoColor=white" alt="Composer"></a>
  <a href="https://phpunit.de/"><img src="https://img.shields.io/badge/PHPUnit-tests-6db33f" alt="PHPUnit"></a>
  <a href="https://github.com/phan/phan"><img src="https://img.shields.io/badge/Phan-static_analysis-4b8bbe" alt="Phan"></a>
  <a href="https://www.mediawiki.org/wiki/Manual:Coding_conventions/PHP"><img src="https://img.shields.io/badge/code_style-MediaWiki-36c" alt="Code style: MediaWiki"></a>
  <a href="https://github.com/features/actions"><img src="https://img.shields.io/badge/GitHub_Actions-CI-2088FF?logo=githubactions&logoColor=white" alt="GitHub Actions"></a>
</p>

## Note: This tool is only usable on itwp since it's impossible to localize all the bureaucracy :-[

## Guida (IT)
Il bot prevede molte opzioni configurabili, facenti capo a 3 pagine diverse on-wiki:

### https://it.wikipedia.org/wiki/Utente:BotRiconferme/List.json
La pagina raccoglie dati su tutti gli admin, in particolare le date di elezione a sysop, burocrate e check user. L'aggiornamento è completamente automatico e non servirà mai di modificare la pagina per aggiornare le date. Tranne in un caso: oltre ai gruppi, per ogni utente sono disponibili due ulteriori opzioni:
  * `override`: Inserendo una data (formato dd/mm/yyyy) in questo campo, la prossima riconferma dell'utente sarà anticipata a tale data, anziché a quella prevista di default. Il parametro verrà rimosso automaticamente all'inizio della riconferma successiva.
  * `override-perm`: Come il precedente, ma vanno specificati solo giorno e mese. Resta in vigore "per sempre" e non verrà mai tolto in automatico.

**Nota**: È fondamentale includere gli zeri di padding (ovvero, 07/05 va bene, 7/05, 7/5 e 07/5 no).

### https://it.wikipedia.org/wiki/Utente:BotRiconferme/Config.json
La pagina raccoglie un insieme di opzioni di configurazione più "oggettivi", e poco soggetti a cambiamenti. Sono quasi tutti titoli delle pagine che il bot dovrà modificare o leggere, tranne il primo:
 * `exclude-admins` contiene una lista di admin da non includere nella lista e quindi da ignorare completamente.
 
### https://it.wikipedia.org/wiki/Utente:BotRiconferme/Messages.json
Questa è l'unica pagina che potrebbe aver bisogno di qualche aggiornamento. Contiene (quasi) tutti i messaggi utilizzati dal bot: sostanzialmente, testo da aggiungere alle pagine e campi oggetto da utilizzare. In alcuni messaggi sono presenti stringhe come `$num`. Queste rappresentano variabili sostituite al momento di utilizzare il messaggio. Inoltre, alcuni usano il costrutto `{{$plur|$num|a|e}}`. Esso funziona esattamente come il `{{PLURAL}}` di MediaWiki, e viene utilizzato nei campi oggetto.

## Struttura del progetto

```
BotRiconferme/
├── src/                        # Codice sorgente (namespace BotRiconferme\)
│   ├── Bot.php                 # Orchestratore principale del bot
│   ├── CLI.php                 # Parsing delle opzioni da riga di comando
│   ├── Clock.php               # Wrapper mockabile sulle funzioni di data/ora
│   ├── Config.php              # Configurazione utente (singleton)
│   ├── ContextSource.php       # Accesso condiviso a config, logger e wiki
│   ├── TaskManager.php         # Selezione ed esecuzione dei task
│   ├── Exception/              # Eccezioni di configurazione
│   ├── Logger/                 # Logging: console, on-wiki e multi-logger
│   ├── Message/                # Messaggi on-wiki e relativa gestione
│   ├── Request/                # Client HTTP verso l'API MediaWiki (cURL/nativo)
│   ├── Task/                   # Task di alto livello
│   │   └── Subtask/            # Passi elementari (crea/chiudi/archivia pagine, ...)
│   ├── TaskHelper/             # Stato, risultati e data provider dei task
│   ├── Utils/                  # Utility varie (es. regex)
│   └── Wiki/                   # Modello del dominio wiki (utenti, gruppi, ...)
│       └── Page/               # Pagine di riconferma e bot-list
├── tests/phpunit/              # Suite di test PHPUnit
├── .github/workflows/main.yml  # CI GitHub Actions (lint, phpcs, phan, phpunit)
├── .phan/config.php            # Configurazione analisi statica Phan
├── run.php                     # Entry point eseguibile del bot
├── composer.json               # Dipendenze e script (test, coverage, ...)
├── phpunit.xml                 # Configurazione PHPUnit
├── .phpcs.xml                  # Regole PHP CodeSniffer (standard MediaWiki)
├── infection.json5             # Configurazione mutation testing (Infection)
├── codemeta.json               # Metadati software (CodeMeta)
├── CITATION.cff                # Metadati di citazione
└── LICENSE.md                  # Licenza AGPL-3.0-or-later
```

## Sviluppo

Installa le dipendenze ed esegui la suite di controlli (parallel-lint, PHP CodeSniffer, Phan e PHPUnit):

```bash
composer install
composer test
```

Per correggere automaticamente lo stile del codice:

```bash
composer fix
```

## Bug e altre creature mitologiche
...Ce ne sono sicuramente! Se ne trovi uno, non esitare! Fammelo presente dove ti è più comodo, va bene sia on-wiki che aprendo un Issue qui su Github. Lo stesso vale per proposte di nuove funzionalità.

## TODO
Decoupling decoupling decoupling!

## Licenza
Distribuito con licenza [GNU Affero General Public License v3.0 o successiva](LICENSE.md) (AGPL-3.0-or-later).
