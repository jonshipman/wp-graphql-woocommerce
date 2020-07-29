############################################################################
# Container for running Codeception tests on a WooGraphQL Docker instance. #
############################################################################

# Using the 'DESIRED_' prefix to avoid confusion with environment variables of the same name.
FROM woographql-app:latest

LABEL author=kidunot89
LABEL author_uri=https://github.com/kidunot89

SHELL [ "/bin/bash", "-c" ]

# Redeclare ARGs and set as environmental variables for reuse.
ARG USE_PCOV
ENV USING_PCOV=${USE_PCOV}

# Install php extensions
RUN docker-php-ext-install pdo_mysql

RUN apt-get -y update \
	&& apt-get install -y libicu-dev \
	&& docker-php-ext-configure intl \
	&& docker-php-ext-install intl

# Install PCOV and XDebug
RUN if [[ -n "$USING_PCOV" ]]; then \
        apt-get install zip unzip -y && \
        pecl install pcov && \
        docker-php-ext-enable pcov && \
        rm -f /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
        echo "pcov.enabled=1" >> /usr/local/etc/php/php.ini;\
    else \
        yes | pecl install xdebug \
        && echo "zend_extension=$(find /usr/local/lib/php/extensions/ -name xdebug.so)" > /usr/local/etc/php/conf.d/xdebug.ini \
        && echo "xdebug.remote_enable=on" >> /usr/local/etc/php/conf.d/xdebug.ini \
        && echo "xdebug.remote_autostart=off" >> /usr/local/etc/php/conf.d/xdebug.ini;\
    fi

# Install composer
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN curl -sS https://getcomposer.org/installer | php -- --filename=composer --install-dir=/usr/local/bin

# Install composer plugin for parallel installs
RUN composer global require hirak/prestissimo

# Add composer global binaries to PATH
ENV PATH "$PATH:~/.composer/vendor/bin"

# Install parallel installation composer plugin by Hiraku NAKANO https://github.com/hirak/prestissimo
RUN composer global require hirak/prestissimo

# Configure php
RUN echo "date.timezone = UTC" >> /usr/local/etc/php/php.ini

# Remove exec statement from base entrypoint script.
RUN sed -i '$d' /usr/local/bin/app-entrypoint.sh

# Set up entrypoint
WORKDIR    /var/www/html/wp-content/plugins/wp-graphql-woocommerce
COPY       docker/testing.entrypoint.sh /usr/local/bin/testing-entrypoint.sh
RUN        chmod 755 /usr/local/bin/testing-entrypoint.sh
ENTRYPOINT ["testing-entrypoint.sh"]
