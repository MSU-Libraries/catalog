FROM bitnami/mariadb-galera:10.6

USER root
ENV DEBIAN_FRONTEND=noninteractive
RUN \
    apt update && \
    apt install netcat-openbsd wget -y && \
    rm -rf /var/lib/apt/lists/*

ARG VUFIND_VERSION

# Get the create-database SQL for the version of Vufind
RUN wget -O /docker-entrypoint-initdb.d/vufind.sql \
    https://raw.githubusercontent.com/vufind-org/vufind/v${VUFIND_VERSION}/module/VuFind/sql/mysql.sql && \
    sed -i '1s/^/use vufind;\n/' /docker-entrypoint-initdb.d/vufind.sql

COPY monitoring.sql /docker-entrypoint-initdb.d/monitoring.sql

USER 1001

COPY ./cloud-entrypoint.sh /cloud-entrypoint.sh
COPY ./cloud-startup.sh /cloud-startup.sh
COPY ./my_custom.cnf /opt/bitnami/mariadb/conf/

HEALTHCHECK --interval=1m --timeout=10s --start-period=90s --retries=6 \
  CMD /opt/bitnami/scripts/mariadb-galera/healthcheck.sh

ENTRYPOINT [ "/cloud-entrypoint.sh" ]
CMD [ "/cloud-startup.sh" ]
