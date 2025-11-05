FROM php:8.2-apache

# Estensioni necessarie per PDO MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Imposta DocumentRoot su /var/www/public
ENV APACHE_DOCUMENT_ROOT=/var/www/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf

# Abilita mod_rewrite (utile poi)
RUN a2enmod rewrite

# Elimina l'avviso su ServerName
RUN bash -lc 'echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf' \
 && a2enconf servername

# Rimuovi la cartella html di default (non la usiamo)
RUN rm -rf /var/www/html

# Imposta la working directory su /var/www invece di /var/www/html 
WORKDIR /var/www

# Crea uno script di avvio personalizzato per eliminare html ad ogni avvio del container
RUN echo '#!/bin/bash\nrm -rf /var/www/html 2>/dev/null || true\nexec apache2-foreground "$@"' > /usr/local/bin/custom-entrypoint.sh \
 && chmod +x /usr/local/bin/custom-entrypoint.sh

CMD ["/usr/local/bin/custom-entrypoint.sh"]
