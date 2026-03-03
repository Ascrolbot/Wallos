# Testing Framework

## Getting Started

Install the dev dependencies with Composer, then run the test suite:

```bash
composer install
vendor/bin/phpunit --testdox

# Other useful commands:

vendor/bin/phpunit --testsuite Unit          # run unit tests only
vendor/bin/phpunit --testsuite Security      # run security tests only
vendor/bin/phpunit --coverage-text           # show line coverage
vendor/bin/phpstan analyse                   # static analysis (level 5)
```

## What This Covers

Before this work, Wallos had no automated tests. The goal was to add a practical testing foundation addressing the most at-risk areas of the application: financial calculations, currency conversion, input handling, and common patterns of security.

The current state of the test suite is 70 tests in five files:

HelpersTest (26 tests) -- tests getPricePerMonth(), the function that normalises subscription prices among billing cycles. Tests all four types of cycles, multi- frequency billing, boundary values, and the two bug fixes are explained below.

PriceConversionTest (10 tests) -- tests getPriceConverted(), which changes foreign currency prices to the user's main currency. Also cheques for the known duplicate function problem across code

InputValidationTest (19 tests) -- covers validate(), the input sanitisation function. Features eight XSS payloads, Unicode handling,
and records the double-encoding concern.

CsrfTokenTest (7 tests) -- checks the cryptographic properties of the CSRF token mechanism: entropy, uniqueness, format, and timing-safe comparison.

SqlInjectionTest (5 tests) -- scans the codebase for raw SQL concatenation patterns. Report the ratio of prepared statements to raw queries and lists specific files with findings.

## Scope and Limitation

This testing framework is aimed at the core functions of financial and security instead of the entire application Overall line coverage of the codebase is low since Wallos is a big PHP application without a test before infrastructure, much of the code is dependent on session state, database connections, and included files that make it difficult to do unit testing in isolation.

The focus was on creating a working foundation: a test runner, a CI pipeline, static analysis and a set of meaningful tests against the most critical functions. The known issues above are left as documented findings rather than fixes because there are changes across multiple files or architectural decisions that carry risk without broader refactoring.

Future work could extend this by adding integration tests with a full database, end-to-end test through the http layer, refactoring the copied functions into a module that is shared. These are tracked on the Kanban board, as backlog items.

As for the code coverage, the coverage reports are generated automatically by the CI/CD pipeline and available as downloadable artifacts on each workflow run.

## Bug Fixes

Two bugs were found and fixed in includes/stats_calculations.php:

1. Division by zero in getPricePerMonth()

When frequency was zero, every branch of the switch statement performed a division that threw a DivisionByZeroError. A guard clause was added to return 0.0 for invalid frequency values.

2. Missing default case in getPricePerMonth()

The switch statement had no default branch. If an unrecognised cycle value was passed, the function returned nothing (implicitly null),
leaving the caller with an undefined result. A default case was added that falls back to a monthly calculation and logs a warning.


### Known Issues (Documented, Not Fixed)

These were identified during testing but left unfixed because they involve changes across multiple files or architectural decisions that go beyond the scope of this work.

3. Duplicate getPriceConverted() implementations

The function exists in at least five files with two different signatures.
The version in stats_calculations.php accepts a userId parameter for tenant scoping, while the versions in list_subscriptions.php and the API files do not.

4. Missing user_id filtering in list_subscriptions.php

The getPriceConverted() in list_subscriptions.php queries the currencies table without filtering by user_id, which means it could return rates belonging to a different user in a multi-tenant setup.

5. Input validation design concern

The validate() function applies htmlspecialchars() at input time rather than at output time. This means data is stored in the database already HTML-encoded.

6. Double encoding risk

Because validate() encodes at input time, any template or output layer that also calls htmlspecialchars() will double-encode entities. For example, "&" becomes "&amp;" on input, then "&amp;amp;" on output.


## CI/CD Pipeline
The GitHub Actions workflow (quality-checks.yml) runs four gates on every push and pull request:

PHP syntax lint across includes/ and endpoints/
PHPStan static analysis at level 5
Full PHPUnit test suite
Code coverage report (uploaded as artifact)

