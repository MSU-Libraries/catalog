FROM bitnami/mariadb-galera:10.6

USER root
ENV DEBIAN_FRONTEND=noninteractive
RUN \
    apt update && \
    apt install netcat-openbsd wget gettext-base moreutils -y && \
    rm -rf /var/lib/apt/lists/*

ARG VUFIND_VERSION

# Get the create-database SQL for the version of Vufind
COPY --chown=1001:0 --chmod=660 monitoring.sql /docker-entrypoint-initdb.d/monitoring.sql
RUN wget -O /docker-entrypoint-initdb.d/vufind.sql \
    https://raw.githubusercontent.com/vufind-org/vufind/v${VUFIND_VERSION}/module/VuFind/sql/mysql.sql && \
    sed -i '1s/^/use vufind;\n/' /docker-entrypoint-initdb.d/vufind.sql

USER 1001

COPY ./cloud-entrypoint.sh ./cloud-startup.sh ./healthcheck.sh /
COPY ./my_custom.cnf /opt/bitnami/mariadb/conf/

HEALTHCHECK --interval=30s --timeout=10s --start-period=45s --retries=12 \
  CMD /healthcheck.sh

ENTRYPOINT [ "/cloud-entrypoint.sh" ]
CMD [ "/cloud-startup.sh" ]
