-- Required Version: 1.5.0

ALTER TABLE item ADD needs_qa boolean;

ALTER TABLE account ADD email varchar(128);

ALTER TABLE biblio ADD volume varchar(64);
