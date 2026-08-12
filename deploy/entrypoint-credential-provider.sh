#!/bin/sh
set -eu

php /app/bin/credential-provider-preflight.php

exec php -S 0.0.0.0:8080 /app/public/credential-provider.php
