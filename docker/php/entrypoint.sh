#!/bin/bash
set -o nounset
set -o errexit
#set -eux

TARGET_UID="${UID:-0}"
TARGET_GID="${GID:-0}"
TARGET_USER="www-data"
TARGET_GROUP="www-data"

# TimeZone для всей системы
ln -snf /usr/share/zoneinfo/${TZ} /etc/localtime || true
echo ${TZ} > /etc/timezone
echo "date.timezone=${TZ}" > $PHP_INI_DIR/conf.d/php.timezone.ini

echo
echo "Current user: $(whoami) , UID: $(id -u) , GID: $(id -g), Path: $HOME"
echo

# --- Блок GID ---
if [ "${TARGET_GID}" != "0" ]; then
    # Убеждаемся, что целевой GID не используется другой группой
    if getent group "$TARGET_GID" >/dev/null; then
        EXISTING_GROUP_NAME=$(getent group "$TARGET_GID" | cut -d: -f1)

        # ДОБАВЛЕНО: Проверяем, что занявшая GID группа — это НЕ www-data
        if [ "$EXISTING_GROUP_NAME" != "$TARGET_GROUP" ]; then
            echo "Предупреждение: GID $TARGET_GID уже используется группой '$EXISTING_GROUP_NAME'. Переименовываем..."
            groupmod -n "old-gid-${TARGET_GID}-${EXISTING_GROUP_NAME}" "$EXISTING_GROUP_NAME"
        fi
    fi
    # Используем -o для разрешения неуникального GID, если необходимо
    groupmod -o -g "$TARGET_GID" "$TARGET_GROUP"
fi

# --- Блок UID ---
if [ "${TARGET_UID}" != "0" ]; then
    # Убеждаемся, что целевой UID не используется другим пользователем
    if getent passwd "$TARGET_UID" >/dev/null; then
        EXISTING_USER_NAME=$(getent passwd "$TARGET_UID" | cut -d: -f1)

        # ДОБАВЛЕНО: Проверяем, что занявший UID пользователь — это НЕ www-data
        if [ "$EXISTING_USER_NAME" != "$TARGET_USER" ]; then
            echo "Предупреждение: UID $TARGET_UID уже используется пользователем '$EXISTING_USER_NAME'. Переименовываем..."
            usermod -l "old-uid-${TARGET_UID}-${EXISTING_USER_NAME}" "$EXISTING_USER_NAME"
        fi
    fi
    # Используем -o для разрешения неуникального UID
    usermod -o -u "$TARGET_UID" "$TARGET_USER"
fi

# --- Блок выполнения команд ---
if [[ $# -gt 0 ]]; then
  echo "Run command: $*"
  # Запускаем команду от имени www-data
  runuser -u ${TARGET_USER} -- "$@"
  echo
fi

echo "Run supervisord"
chmod 644 /etc/supervisord.conf
/usr/bin/supervisord -c /etc/supervisord.conf &

echo "Run php-fpm"
docker-php-entrypoint php-fpm
