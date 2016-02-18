CREATE TABLE /*_*/external_user (
    eu_local_id int(10) unsigned NOT NULL PRIMARY KEY,
    eu_external_id varchar(255) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
    UNIQUE KEY eu_external_id (eu_external_id)
) /*wgDBTableOptions */;

