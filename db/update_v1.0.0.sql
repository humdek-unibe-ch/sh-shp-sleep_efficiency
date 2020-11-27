-- add plugin [sleepexpert] sleep efficiency
INSERT INTO lookups (type_code, lookup_code, lookup_value, lookup_description) values ('plugins', 'calc_sleep_efficiency', '[sleepexpert] Calculate sleep efficiency', 'Calculate sleep efficiency based on the input data');

-- add plugin entry in the plugin table
INSERT INTO plugins (name, version) 
VALUES ('sleepEfficiency', 'v1.0.0');