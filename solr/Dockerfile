FROM bitnami/solr:8

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=America/Detroit

USER root

# Perform updates
RUN \
    apt update && \
    apt install wget vim-nox htop rsyslog cron moreutils gettext-base jq -y && \
    rm -rf /var/lib/apt/lists/*

# Setup Timezone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && \
    echo $TZ > /etc/timezone

ARG VUFIND_VERSION

# Get solr configs and prepare for alphabetical browsing
RUN mkdir /solr_confs && \
    wget https://github.com/vufind-org/vufind/archive/refs/tags/v${VUFIND_VERSION}.tar.gz && \
    tar -xf v${VUFIND_VERSION}.tar.gz  && \
    cp -r /vufind-${VUFIND_VERSION}/solr/vufind/* /solr_confs && \
    cp /vufind-${VUFIND_VERSION}/index-alphabetic-browse.sh /solr_confs/ && \
    chmod ugo+x /solr_confs/index-alphabetic-browse.sh && \
    mkdir /solr_confs/import && \
    chown 1001 /solr_confs/import && \
    cp /vufind-${VUFIND_VERSION}/import/browse-indexing.jar /solr_confs/import/ && \
    rm v${VUFIND_VERSION}.tar.gz && \
    rm -rf /vufind-${VUFIND_VERSION} && \
    cp -r /opt/bitnami/solr/contrib/analysis-extras/lib/* /opt/bitnami/solr/server/solr-webapp/webapp/WEB-INF/lib/ && \
    cp -r /opt/bitnami/solr/contrib/analysis-extras/lucene-libs/* /opt/bitnami/solr/server/solr-webapp/webapp/WEB-INF/lib/

# Setup cron and logging for cron
COPY cron.d/ /etc/cron.d/
RUN chmod -R g-w,o-w /etc/cron.d/ && \
    sed -i "/imklog/d" /etc/rsyslog.conf && \
    sed -i "/PrivDrop/d" /etc/rsyslog.conf && \
    echo "*.*;auth,authpriv.none /proc/1/fd/1" >> /etc/rsyslog.d/50-default.conf

COPY startup.sh /startup.sh
COPY startup-cron.sh /startup-cron.sh
COPY healthcheck.sh /healthcheck.sh
COPY clusterhealth.sh /clusterhealth.sh
COPY cron-alphabrowse.sh /cron-alphabrowse.sh

HEALTHCHECK --interval=1m --timeout=10s --start-period=180s --retries=6 \
  CMD /healthcheck.sh

USER 1001

RUN cp -r /solr_confs/jars /opt/bitnami/solr/server/solr/jars

COPY browse-handler.jar /opt/bitnami/solr/server/solr/jars/browse-handler.jar
COPY alpha-browse.sh /alpha-browse.sh
COPY lock-state.sh /lock-state.sh

# Run start-up script
CMD ["/startup.sh", "/opt/bitnami/scripts/solr/run.sh"]
