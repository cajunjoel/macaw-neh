-- Required Version: 1.4.0
CREATE TABLE collection (
	item_id int references item (id),
	collection varchar(32)
);

ALTER TABLE item ADD sponsor varchar(64);
