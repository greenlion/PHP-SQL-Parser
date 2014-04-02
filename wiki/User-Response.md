#### Marco64Th (30-Aug-2012)

It is very useful in the project i was working on.

The project was about modifying an existing open source software package (over 2.000 PHP files, almost 500 MySQL tables). The goal was to use the software (single installation/database) for multiple clients, where the data of each client needed to be totally seperated from other clients (no data leaks).

In order to achieve this most tables had to get a new client_id column and all queries needed to be modified to use the client_id when inserting new data or in the WHERE clause when retrieving data, etc..

Doing this the old fashioned way by going over all sources and editing all queries would be undoable and would probable have led to missed queries and by that data leaks. Fortunately the software was using 1 (actually 2) low-level routines for all access to the MySQL database. So the solution was to intercept all queries before they get executed, parse them, modify them, rebuild them and then execute.

The PHP-SQL parser & Creator gave me a good starting point for that.

***

#### vincent.vatelot (10-Sep-2012)

Works fine with /tags/2012-07-03 version. I use it in CodeIgniter very easily. Good work! :)

***

#### nababx (3-Feb-2013)

Great job! It would be even better if you had it on packagist... :) 

***

