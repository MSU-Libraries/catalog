FROM ubuntu:22.04

ENV TZ=America/Detroit
ENV VUFIND_VERSION=8.0.4
ENV MYSQL_ROOT_PASSWORD=12345

ENV DEBIAN_FRONTEND=noninteractive
ENV VUFIND_LOCAL_DIR=/usr/local/vufind/local
ENV VUFIND_HOME=/usr/local/vufind
ENV JAVA_HOME=/usr/lib/jvm/default-java

# VuFind cache directory can't stay the default (/usr/local/vufind/local/cache) because we want to mount the repository
# at /usr/local/vufind on container startup. The volume mount process would set the complete folder tree to user/group
# "root", which finally would prevent the Apache from accessing it.
#ENV VUFIND_CACHE_DIR /tmp/vufind_cache

# Perform updates
RUN apt update && \
    apt install wget vim lsof apache2 xml-twig-tools curl -y

# Setup Timezone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && \
    echo $TZ > /etc/timezone

# Get the package
RUN wget https://github.com/vufind-org/vufind/releases/download/v${VUFIND_VERSION}/vufind_${VUFIND_VERSION}.deb

# Install the package
RUN service apache2 restart && \
    apt install ./vufind_${VUFIND_VERSION}.deb -y && \
    apt clean

# Build Arguments
ARG DEBUG=false
ARG FOLIO_URL
ARG FOLIO_USER
ARG FOLIO_PASS
ARG FOLIO_TENANT
ARG FOLIO_REC_ID
ARG FOLIO_CANCEL_ID
ARG OAI_URL
ARG EMAIL
ARG MAIL_HOST
ARG MAIL_PORT
ARG MAIL_USERNAME
ARG MAIL_PASSWORD
ARG SOLR_URL=http://solr:8983/solr

ENV DEBUG=$DEBUG \
    FOLIO_URL=$FOLIO_URL \
    FOLIO_USER=$FOLIO_USER \
    FOLIO_PASS=$FOLIO_PASS \
    FOLIO_TENANT=$FOLIO_TENANT \
    FOLIO_REC_ID=$FOLIO_REC_ID \
    FOLIO_CANCEL_ID=$FOLIO_CANCEL_ID \
    OAI_URL=$OAI_URL \
    EMAIL=$EMAIL \
    MAIL_HOST=$MAIL_HOST \
    MAIL_PORT=$MAIL_PORT \
    MAIL_USERNAME=$MAIL_USERNAME \
    MAIL_PASSWORD=$MAIL_PASSWORD \
    SOLR_URL=$SOLR_URL

# Setup Apache
RUN echo "ServerName catalog.lib.msu.edu" >> /etc/apache2/sites-available/000-default.conf && \
    echo "LogLevel warn" >> /etc/apache2/sites-enabled/000-default.conf && \
    echo "ErrorLog /proc/self/fd/2" >> /etc/apache2/sites-enabled/000-default.conf && \
    echo "CustomLog /proc/self/fd/1 combined" >> /etc/apache2/sites-enabled/000-default.conf

# Add file customizations
COPY local/ /usr/local/vufind/local
COPY startup.sh /startup.sh

WORKDIR /usr/local/vufind

# Replace environment variables in template ini files
RUN envsubst < local/config/vufind/Folio.template.ini > local/config/vufind/Folio.ini && \
    envsubst < local/config/vufind/config.template.ini > local/config/vufind/config.ini && \
    envsubst < local/harvest/oai.template.ini > local/harvest/oai.ini

# Enable debug mode
RUN if [ "$DEBUG" = 1 ] ; \
    then sed -i "/SetEnv VUFIND_ENV development/s/#//g" ${VUFIND_LOCAL_DIR}/httpd-vufind.conf; fi

# TODO: Hopefully can fix this in the Folio driver.
# Had to change the id from 1 to a real id in folio to get the getIlsStatus call working.
# We could override this function in the driver to switch the call it makes to something less
# dependent on an ID.
RUN sed -i "/getStatus/s/1/${FOLIO_REC_ID}/g" ${VUFIND_HOME}/module/VuFind/src/VuFind/ILS/Connection.php

# Set required environment variables
RUN echo export JAVA_HOME="$JAVA_HOME" > /etc/profile.d/vufind.sh && \
    echo export VUFIND_HOME="$VUFIND_HOME"  >> /etc/profile.d/vufind.sh && \
    echo export VUFIND_LOCAL_DIR="$VUFIND_LOCAL_DIR"  >> /etc/profile.d/vufind.sh

EXPOSE 80

CMD /startup.sh
