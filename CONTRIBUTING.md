# Contributing to BotRiconferme

Thank you for your interest in contributing! Bug reports, fixes and feature
proposals are all welcome. This bot is specific to the Italian Wikipedia
(itwiki) administrator re-confirmation process, so please keep that scope in
mind when proposing changes.

## Development setup

You will need **PHP >= 8.4** and [Composer](https://getcomposer.org/).

1. **Clone the repository**:
   ```bash
   git clone https://github.com/Daimona/BotRiconferme.git
   cd BotRiconferme
   ```

2. **Install dependencies**:
   ```bash
   composer install
   ```

> The static analysis step (Phan) additionally requires the
> [`ast`](https://pecl.php.net/package/ast) PHP extension.

## Testing and quality

Before opening a pull request, please make sure the full check suite passes:

```bash
composer test
```

This runs, in order:

- `parallel-lint` — PHP syntax check;
- `phpcs` — coding style ([MediaWiki PHP coding conventions](https://www.mediawiki.org/wiki/Manual:Coding_conventions/PHP));
- `phan` — static analysis;
- `phpunit` — unit tests.

Useful individual commands:

| Command | Purpose |
| --- | --- |
| `composer fix` | Auto-fix coding-style issues (`phpcbf`) |
| `composer phpunit` | Run the unit tests only |
| `composer phan` | Run static analysis only |
| `composer coverage` | Generate an HTML coverage report |

## Coding conventions

- Follow the MediaWiki PHP coding conventions (enforced by `phpcs`).
- Every file declares `declare( strict_types=1 )`.
- Add or update tests for any behavioural change; new code should keep the
  static-analysis and style checks green.

## Submitting changes

1. Create a topic branch for your change.
2. Make sure `composer test` passes locally.
3. Open a pull request against the `master` branch, describing what changed and
   why (see the pull-request template).

## Reporting bugs

Please use the GitHub issue templates. You can also report issues on-wiki,
wherever is most convenient for you.
