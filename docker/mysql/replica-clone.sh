#!/bin/bash
set -e
PRIMARY=(mysql -h mysql -uroot -p"$MYSQL_ROOT_PASSWORD")
REPLICA=(mysql -uroot -p"$MYSQL_ROOT_PASSWORD")

"${PRIMARY[@]}" -e "INSTALL PLUGIN clone SONAME 'mysql_clone.so'" 2>/dev/null || true
"${PRIMARY[@]}" -e "CREATE USER IF NOT EXISTS 'repl'@'%' IDENTIFIED BY '$MYSQL_ROOT_PASSWORD'; GRANT REPLICATION SLAVE, BACKUP_ADMIN ON *.* TO 'repl'@'%';"

"${REPLICA[@]}" -e "STOP REPLICA; SET PERSIST super_read_only = OFF; SET PERSIST read_only = OFF;" 2>/dev/null || true
"${REPLICA[@]}" -e "INSTALL PLUGIN clone SONAME 'mysql_clone.so'" 2>/dev/null || true
"${REPLICA[@]}" -e "SET GLOBAL clone_valid_donor_list = 'mysql:3306'"

echo "Cloning the primary into the replica..."
"${REPLICA[@]}" -e "CLONE INSTANCE FROM 'repl'@'mysql':3306 IDENTIFIED BY '$MYSQL_ROOT_PASSWORD'" 2>/dev/null || true
echo "Clone finished, the replica restarts now."
