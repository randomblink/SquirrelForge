#!/bin/sh
set -eu

php /app/bin/runtime-preflight.php

exec php -S 0.0.0.0:8080 /app/public/engine-api.php
