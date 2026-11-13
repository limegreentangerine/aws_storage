# Changelog

Notable changes to AWS Storage are documented here.

## [1.1.1]

- Add CloudFront cache invalidation workflow to clear cached content after file operations.
- Improve IAM policy configuration for better security and reliability.
- Refactor invalidations helper for improved maintainability.

## [1.1.0]

- Add AWS API integration to automatically create S3 buckets, IAM users, and policies from the Concrete CMS dashboard.
- Add CloudFront distribution creation and configuration for CDN support.
- Add logging for AWS API operations for better troubleshooting.
- Improve storage configuration workflow with enhanced setup experience.
- Add custom Flysystem AWS adapter wrapper for compatibility.

## [1.0.2]

- Add package versioning and release management improvements.

## [1.0.1]

- Internal package fixes and improvements.

## [1.0.0]

- Add an S3 storage location type for Concrete CMS, with configurable bucket, AWS credentials, region, and API version.
- Support an optional CloudFront base URL for public file links.
- Add a dashboard workflow for queueing file moves between storage locations.
