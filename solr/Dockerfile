FROM bitnami/solr:8

ENV VUFIND_VERSION=8.0.4
ENV DEBIAN_FRONTEND=noninteractive

USER root

# Perform updates
RUN apt update && \
    apt install wget vim -y

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
    ln -s  /opt/bitnami/solr/contrib/analysis-extras/lib/ /opt/bitnami/solr/server/vendor/contrib/analysis-extras/ && \
    ln -s  /opt/bitnami/solr/contrib/analysis-extras/lucene-libs/ /opt/bitnami/solr/server/vendor/contrib/analysis-extras/

COPY startup.sh /startup.sh

USER 1001

# Run start-up script
CMD /startup.sh
