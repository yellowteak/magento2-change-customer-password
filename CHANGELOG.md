# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Added
- A password change now also unlocks a customer account that was locked because of failed login attempts.
- English (`en_US`) and Dutch (`nl_NL`) translations.
- Unit tests and a GitHub Actions workflow that runs them together with the Magento coding standard.

### Changed
- The password is now changed with a dedicated `Change Password` button in the tab, instead of together with the customer save.
- Changing a password requires the new `Customers > All Customers > Actions > Change password` permission. Roles with custom resource access have to be granted this permission.
- The tab is no longer shown while creating a new customer.

### Security
- The admin password check now counts failed attempts and locks the admin account, the same as the login page.
- All active sessions of the customer are ended after a password change.
- The new password is validated against the full customer password policy (maximum length, leading or trailing spaces, required character classes, different from the email address).

### Fixed
- Declared the missing Composer and module dependencies.

## [1.0.1] - 2026-03-18
### Fixed
- Composer fixes.

## [1.0.0] - 2026-03-18
### Added
- Initial release.
