CREATE TABLE /*_*/phab_user (
    eu_local_id int(10) unsigned NOT NULL PRIMARY KEY,
    eu_external_id varchar(255),
    eu_username varchar(255) binary NOT NULL,
    eu_email varchar(255) binary NOT NULL,
    eu_token varchar(255),
    eu_timestamp binary(14) not null default '',
    UNIQUE KEY eu_external_id (eu_external_id)
) /*wgDBTableOptions */;
