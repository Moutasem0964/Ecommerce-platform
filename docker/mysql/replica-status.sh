#!/bin/bash
mysqladmin ping -uroot -p"$MYSQL_ROOT_PASSWORD" --silent 2>/dev/null || { echo "down"; exit 2; }

if [ -z "$(mysql -N -s -uroot -p"$MYSQL_ROOT_PASSWORD" -e "SHOW DATABASES LIKE 'ecommerce'" 2>/dev/null)" ]; then
    echo "up, empty (needs a clone)"
    exit 3
fi

status=$(mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "SHOW REPLICA STATUS\G" 2>/dev/null)
io=$(echo "$status" | awk '/ Replica_IO_Running:/ {print $2}')
sql=$(echo "$status" | awk '/ Replica_SQL_Running:/ {print $2}')
lag=$(echo "$status" | awk '/ Seconds_Behind_Source:/ {print $2}')

if [ "$io" = "Yes" ] && [ "$sql" = "Yes" ]; then
    echo "replicating, lag ${lag}s"
    exit 0
fi

echo "up, not replicating (IO=${io:-none} SQL=${sql:-none})"
exit 1
