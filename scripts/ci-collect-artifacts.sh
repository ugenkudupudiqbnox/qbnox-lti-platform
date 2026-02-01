
#!/usr/bin/env bash
set -e
OUT=ci-artifacts
mkdir -p $OUT
docker logs moodle > $OUT/moodle.log || true
docker logs pressbooks > $OUT/pressbooks.log || true
MOODLE_CONTAINER=$(docker ps --filter "name=moodle" --format "{{.ID}}")
docker exec "$MOODLE_CONTAINER" bash -c "mysqldump -uroot -proot moodle > /tmp/moodle.sql"
docker cp "$MOODLE_CONTAINER:/tmp/moodle.sql" $OUT/moodle.sql
echo "Artifacts collected"
