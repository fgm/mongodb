var dbs = db.adminCommand('listDatabases');
print(dbs);
print(db.getSiblingDB('drupal'));

//print(db.url_alias.find());
