FROM bitnami/solr:8

ENV VUFIND_VERSION=8.0.4
ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=America/Detroit

USER root

# Perform updates
RUN apt update && \
    apt install wget vim -y

# Setup Timezone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && \
    echo $TZ > /etc/timezone

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
    #mkdir -p /bitnami/solr/server && \
    #chown 1001 /bitnami/solr/server && \
    #ln -s /opt/bitnami/solr /bitnami/solr/server/vendor && \
    rm v${VUFIND_VERSION}.tar.gz && \
    rm -rf /vufind-${VUFIND_VERSION} && \
    cp -r /opt/bitnami/solr/contrib/analysis-extras/lib/* /opt/bitnami/solr/server/solr-webapp/webapp/WEB-INF/lib/ && \
    cp -r /opt/bitnami/solr/contrib/analysis-extras/lucene-libs/* /opt/bitnami/solr/server/solr-webapp/webapp/WEB-INF/lib/

COPY startup.sh /startup.sh

USER 1001

RUN cp -r /solr_confs/jars /opt/bitnami/solr/server/solr/jars

COPY browse-handler.jar /opt/bitnami/solr/server/solr/jars/browse-handler.jar

# Run start-up script
CMD ["/startup.sh", "/opt/bitnami/scripts/solr/run.sh"]
