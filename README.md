# CakePHP Workflow Plugin

This branch targets CakePHP 6 and PHP 8.5 or later. Use the `cake6` branches of companion CakePHP plugins during development.

Composer applications consuming this development branch must set `minimum-stability` to `dev` and `prefer-stable` to `true`, then require this plugin as `dev-cake6`. Companion plugins also use their `cake6` branches. The published documentation website covers stable CakePHP 5 releases; use this branch's `docs/` directory for the upgrade documentation.

[![CI](https://github.com/dereuromark/cakephp-workflow/actions/workflows/ci.yml/badge.svg?branch=cake6)](https://github.com/dereuromark/cakephp-workflow/actions/workflows/ci.yml?query=branch%3Amaster)
[![codecov](https://img.shields.io/codecov/c/github/dereuromark/cakephp-workflow/master.svg)](https://codecov.io/gh/dereuromark/cakephp-workflow)
[![Latest Stable Version](https://poser.pugx.org/dereuromark/cakephp-workflow/v/stable.svg)](https://packagist.org/packages/dereuromark/cakephp-workflow)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%208.2-8892BF.svg)](https://php.net/)
[![License](https://poser.pugx.org/dereuromark/cakephp-workflow/license.svg)](LICENSE)
[![Coding Standards](https://img.shields.io/badge/cs-PhpCollective-purple.svg?style=flat-square)](https://github.com/php-collective/code-sniffer)

See the [version map](https://github.com/dereuromark/cakephp-workflow/wiki#cakephp-version-map) for other CakePHP branches.

State machine and workflow engine for CakePHP with PHP 8 Attributes, YAML/NEON config support, and admin UI.

> [!TIP]
> Try the live demo: <https://sandbox.dereuromark.de/workflow-sandbox>
>
> There is also a [demo app](https://github.com/dereuromark/cakephp-workflow-demo) with more details to experiment with

## Documentation

Follow [Installation](https://dereuromark.github.io/cakephp-workflow/guide/installation)
and [Getting started](https://dereuromark.github.io/cakephp-workflow/guide/quick-start)
for a persisted workflow on an existing table.

The website covers attribute and configuration-file definitions, guards, transition
commands, ORM integration, logging, locking, timeouts, diagrams and the optional
admin interface. Server-side SVG and PNG diagram exports require Graphviz; the
in-page Mermaid widget does not.

The admin interface denies access until an application configures
`Workflow.adminAccess`. Review authorization before exposing workflow routes.

## Contributing

Run the relevant PHPUnit tests, `composer cs-check` and `composer stan` before
submitting source changes. Documentation lives in `docs/`; run `npm ci` and
`npm run docs:build` from that directory.
