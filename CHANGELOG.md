# Release Notes for Abandoned Cart

## 1.1.5 - 2019-05-30
### Changed
- New plugin icon 🎉
### Fixed [#14]
- Fixed an issue with abandoned carts with no emails. I've submitted a pull request to Craft Commerce to change how emails are stored when an order is first created.

[#14]: https://github.com/mediabeastnz/craft-commerce-abandoned-cart/issues/14

## 1.1.4 - 2019-05-19
### Fixed
- Resolved a date deprecation warning

## 1.1.3 - 2019-05-10
### Fixed
- Abandoned carts still display if original Order is missing

## 1.1.2.1 - 2019-04-10
### Fixed
- Patch for last version, fixes missing folder

## 1.1.2 - 2019-04-10
### Fixed issue [#4]
- Fixed a bug that was introduced in version 1.1.1

[#4]: https://github.com/mediabeastnz/craft-commerce-abandoned-cart/issues/4

### Added
- Added the ability to change recovery url to soemthing other than shop/cart

## 1.1.1 - 2019-03-23
### Added
- Added pagination to the dashboard to handle sites with large amounts of orders

## 1.1.0 - 2019-03-19
### Added
- You can now manually trigger the job that searches for abandoned carts! Useful if you don't have access to cron jobs
- Added additional documention/instructions to help with setup

## 1.0.1 - 2019-02-20
### Fixed
- Breadcrumb link

## 1.0.0 - 2019-02-16
### Added
- Initial release
