Migrations placed here will be imported into config upon module enable.  They
will be exported to site config upon configuration export (drush cex).  The
drawback is that if you are actively developing a migration you have to keep
importing the config and keep the changes in sync with site config.
The advantage is that these migrations show up in the migrate UI.
