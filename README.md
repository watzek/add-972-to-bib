# add-972-to-bib

Temp bibs created in Summit borrowing do not have a 972 field, but default to 'ebooks' for print books, which is bad. These scripts use the Sets API to get members of a Set of these records, and use the Update Bibs API to fix 'em.

Requirements:
* A logical set in Alma
where (Library (Holdings) equals ((Watzek Library : aaSummit 3 Long Loan/aaSummit 3 Short Loan)) AND c.search.index_names.local_field_972 is empty)
* Alma API key with read/write for Configuration/Administration, and Bibliographic Records and Inventory
* The following files on a linux server: index.php, config.php, log.txt. Refer to config.example.php for config.php.
* A cron set up to run index.php daily. This will retrieve members of the set, and for each set member, the bib record will be retrieved, updated with the 972 field, and updated in Alma.






Example cron command (example below runs at 2pm every day):


0 14 * * * /path/to/file/index.php > /path/to/file/log.txt
