#!/bin/sh
# Rebuilds a throwaway database `bcoem_scratch` as a copy of the dev stack's `bcoem`, then seeds
# cider/mead entries, a judging table and a judge's likes/dislikes that exercise the BJCP 2025/2026
# converters. NEVER modifies `bcoem`. Run from the repo root with the docker stack up:
#   sh tests/manual/reset-bjcp-scratch-db.sh
set -e
DB=bluebonnet-testing-db-1
docker exec $DB sh -c 'mariadb-dump -uroot -proot_password --routines --triggers bcoem 2>/dev/null' > /tmp/bcoem_scratch_snapshot.sql
docker exec $DB mariadb -uroot -proot_password -e "DROP DATABASE IF EXISTS bcoem_scratch; CREATE DATABASE bcoem_scratch; GRANT ALL ON bcoem_scratch.* TO 'bcoem'@'%'; FLUSH PRIVILEGES;" 2>/dev/null
docker exec -i $DB mariadb -uroot -proot_password bcoem_scratch < /tmp/bcoem_scratch_snapshot.sql 2>/dev/null
docker exec $DB mariadb -ubcoem -pbcoem_password bcoem_scratch 2>/dev/null -e "
INSERT INTO brewing (brewName,brewStyle,brewCategory,brewCategorySort,brewSubCategory,brewBrewerID,brewStyleType,brewPaid,brewReceived,brewConfirmed) VALUES
('t English Cider','English Cider','C1','C1','B','1',2,1,1,1),
('t NW Perry','New World Perry','C1','C1','D','1',2,1,1,1),
('t Cider Other Fruit','Cider with Other Fruit','C2','C2','B','1',2,1,1,1),
('t Dry Mead','Dry Mead','M1','M1','A','1',3,1,1,1),
('t Melomel','Melomel','M2','M2','E','1',3,1,1,1),
('t Fruit Spice Mead','Fruit and Spice Mead','M3','M3','A','1',3,1,1,1),
('t Spice Herb Veg','Spice, Herb, or Vegetable Mead','M3','M3','B','1',3,1,1,1),
('t Historical Mead','Historical Mead','M4','M4','B','1',3,1,1,1),
('t Experimental Mead','Experimental Mead','M4','M4','C','1',3,1,1,1);
-- ids below are BJCP2021 rows: 579 English Cider, 581 New World Perry, 594 Dry Mead, 601 Melomel, 602/603 M3A/M3B, 605 Historical Mead, 606 Experimental Mead
UPDATE judging_tables SET tableStyles='605,606,603,601,594,579,9999' WHERE id=116;
UPDATE brewer SET brewerJudgeLikes='603,602,579,9998', brewerJudgeDislikes='605,581' WHERE uid=1;"
echo "bcoem_scratch ready (bcoem untouched)."
