# Release Notes for Abandoned Cart

## 1.1.6 - 2019-06-04
### Added
- Added support for `allowAdminChanges`, settings will be disabled by default

## 1.1.5 - 2019-05-30
### Changed
- New plugin icon 🎉

### Fixed
- Fixed the issue [#14] which involved abandoned carts with no email addresses. I've submitted a pull request to Craft Commerce to change how emails are stored when an order is first created.

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
