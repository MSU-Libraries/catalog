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

# Get solr configs
# TODO copy the jar files as well
RUN mkdir /solr_confs && \
    wget https://github.com/vufind-org/vufind/archive/refs/tags/v${VUFIND_VERSION}.tar.gz && \
    tar -xf v${VUFIND_VERSION}.tar.gz  && \
    cp -r /vufind-${VUFIND_VERSION}/solr/vufind/* /solr_confs && \
    rm v${VUFIND_VERSION}.tar.gz && \
    rm -rf /vufind-${VUFIND_VERSION} && \
    mv /solr_confs/jars /opt/bitnami/solr/server/solr/jars && \
    mkdir -p /opt/bitnami/solr/server/vendor/contrib/analysis-extras/ && \
    ln -s /opt/bitnami/solr/contrib/analysis-extras/lib/ /opt/bitnami/solr/server/vendor/contrib/analysis-extras/ && \
    ln -s /opt/bitnami/solr/contrib/analysis-extras/lucene-libs/ /opt/bitnami/solr/server/vendor/contrib/analysis-extras/

COPY entrypoint.sh /entrypoint.sh
COPY startup.sh /startup.sh

USER 1001

# Run start-up script
ENTRYPOINT ["/entrypoint.sh", "/startup.sh", "/opt/bitnami/scripts/solr/run.sh"]
CMD ["/startup.sh", "/opt/bitnami/scripts/solr/run.sh"]
