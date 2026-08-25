FROM registry.gitlab.msu.edu/msu-libraries/catalog/galera:11.4.12

USER root
ENV DEBIAN_FRONTEND=noninteractive
RUN \
    apt-get update && \
    apt-get install netcat-openbsd wget gettext-base moreutils vim-nox htop -y && \
    apt-get upgrade -y && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

ARG VUFIND_VERSION

# Get the create-database SQL for the version of Vufind
COPY --chown=1001:0 --chmod=660 monitoring.sql /docker-entrypoint-initdb.d/monitoring.sql
RUN wget -O /docker-entrypoint-initdb.d/vufind.sql \
    https://raw.githubusercontent.com/vufind-org/vufind/v${VUFIND_VERSION}/module/VuFind/sql/mysql.sql && \
    sed -i '1s/^/use vufind;\n/' /docker-entrypoint-initdb.d/vufind.sql

USER 1001

COPY ./cloud-entrypoint.sh ./cloud-startup.sh ./healthcheck.sh /usr/local/bin/
COPY ./my_custom.cnf /opt/bitnami/mariadb/conf/
COPY ./connect.sh /usr/local/bin/connect
COPY ./db-upgrade.sh /usr/local/bin/db-upgrade

HEALTHCHECK --interval=30s --timeout=10s --start-period=45s --retries=12 \
  CMD /usr/local/bin/healthcheck.sh

ENTRYPOINT [ "/usr/local/bin/cloud-entrypoint.sh" ]
CMD [ "/usr/local/bin/cloud-startup.sh" ]
