# httpd-analytics
Custom log format and insertion into database tables.  Support for apache.access.log and php_error.log

## Instructions
1. Enter your settings into `Configuration.php`
2. Run the `database-create.sql` file to create the schema and tables
3. Update your main apache .conf file or the domain specific httpd-vhosts.conf file, see `httpd-vhosts.conf`
4. Enable the `mod_logio` apache module
5. Run `send-apache-logs-to-db.php` and `send-php-errors-to-db.php` (every 5 minutes)
6. Run `rotate-logs.php` to copy PHP errors into log files with the date in the filename (every 5 minutes)
7. Read tables by querying `analytics.page_statistics` and `analytics.php_errors`

## How it works
