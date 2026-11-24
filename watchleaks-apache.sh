#!/usr/bin/env bash
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONF="$DIR/var/apache/apache2.conf"
SOCKET="$DIR/var/run/mysqld.sock"
PID_DB="$DIR/var/run/mysqld.pid"
PID_WEB="$DIR/var/apache/apache.pid"

db_up() { mariadb-admin --socket="$SOCKET" ping --silent >/dev/null 2>&1; }
web_up() { [ -f "$PID_WEB" ] && kill -0 "$(cat "$PID_WEB")" 2>/dev/null; }

start_db() {
    if db_up; then
        echo "MariaDB: deja actif"
        return
    fi
    setsid mariadbd --datadir="$DIR/var/db" --socket="$SOCKET" --port=3307 --bind-address=127.0.0.1 --pid-file="$PID_DB" --log-error="$DIR/var/log/mariadb.log" --skip-name-resolve </dev/null >/dev/null 2>&1 &
    for _ in $(seq 1 30); do
        db_up && break
        sleep 1
    done
    if db_up; then echo "MariaDB: demarre"; else echo "MariaDB: echec (voir var/log/mariadb.log)" >&2; exit 1; fi
}

start_web() {
    if web_up; then
        echo "Apache: deja actif"
        return
    fi
    apache2 -f "$CONF" -k start
    echo "Apache: demarre"
}

stop_web() {
    if web_up; then
        apache2 -f "$CONF" -k graceful-stop
        echo "Apache: arrete"
    else
        echo "Apache: deja arrete"
    fi
}

stop_db() {
    if db_up && [ -f "$PID_DB" ]; then
        kill "$(cat "$PID_DB")"
        echo "MariaDB: arrete"
    else
        echo "MariaDB: deja arrete"
    fi
}

status() {
    if db_up; then echo "MariaDB: actif (socket $SOCKET, port 3307)"; else echo "MariaDB: arrete"; fi
    if web_up; then echo "Apache: actif (http://localhost:8001)"; else echo "Apache: arrete"; fi
    echo "App:       http://localhost:8001/login"
    echo "phpMyAdmin: http://localhost:8001/phpmyadmin/"
}

case "${1:-}" in
    start) start_db; start_web ;;
    stop) stop_web; stop_db ;;
    restart) stop_web; stop_db; start_db; start_web ;;
    status) status ;;
    *) echo "Usage: $0 {start|stop|restart|status}"; exit 1 ;;
esac
