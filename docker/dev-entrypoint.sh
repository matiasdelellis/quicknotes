#!/bin/sh
# Dev entrypoint: forces `occ maintenance:install` on first boot (the
# upstream entrypoint skips it when the dev tree's version.php matches
# the image's) and then execs the upstream entrypoint.
set -eu

if [ ! -f /var/www/html/config/config.php ]; then
    echo "[dev] No config.php found, running occ maintenance:install..."

    install_opts="-n --admin-user \"${NEXTCLOUD_ADMIN_USER:-admin}\" --admin-pass \"${NEXTCLOUD_ADMIN_PASSWORD:-admin}\""

    if [ -n "${SQLITE_DATABASE+x}" ]; then
        install_opts="$install_opts --database-name \"${SQLITE_DATABASE}\""
    elif [ -n "${MYSQL_HOST+x}" ] && [ -n "${MYSQL_DATABASE+x}" ] \
         && [ -n "${MYSQL_USER+x}" ] && [ -n "${MYSQL_PASSWORD+x}" ]; then
        install_opts="$install_opts --database mysql --database-host \"${MYSQL_HOST}\" --database-name \"${MYSQL_DATABASE}\" --database-user \"${MYSQL_USER}\" --database-pass \"${MYSQL_PASSWORD}\""
    fi

    # shellcheck disable=SC2086
    su -p www-data -s /bin/sh -c "php /var/www/html/occ maintenance:install $install_opts"

    if [ -n "${NEXTCLOUD_TRUSTED_DOMAINS+x}" ]; then
        set -f
        idx=1
        for d in ${NEXTCLOUD_TRUSTED_DOMAINS}; do
            d=$(echo "$d" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')
            su -p www-data -s /bin/sh -c "php /var/www/html/occ config:system:set trusted_domains $idx --value=\"$d\""
            idx=$((idx+1))
        done
        set +f
    fi
fi

exec /entrypoint.sh "$@"
