# Changelog

All notable changes to `lalalili/subscription-core` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0] - 2026-06-27

### Added

- **定期定額（recurring）續期支援**：`merchant_subscriptions` 新增 `is_recurring / gwsr /
  total_success_times / failed_charge_count / recurring_period_type / recurring_exec_times`、
  `subscription_orders` 新增 `period_sequence`。
- `SubscriptionService`：`subscribe()` 支援 recurring 旗標與 period 參數；新增
  `recordRecurringCharge()`（以 `period_sequence` 冪等、每期續展 `expires_at`）、
  `recordRecurringFailure()`（累計連續失敗、達門檻終止）、`markPastDue()`；扣款成功重置失敗計數。
- `expireSubscriptions()` 排除 `is_recurring`（續期／失效改由金流 webhook 推進）。
- `Contracts\SubscriptionCanceller` 取消接縫；config `recurring.failure_termination_threshold`（預設 6）。

### Added (infra)

- CI (PHP 8.3/8.4) and tag-triggered release workflows; baseline release
  discipline (pest + phpstan via `composer test` / `composer analyse`).

## [0.1.0]

- Initial subscription domain core (consumed by `aitehub`).
